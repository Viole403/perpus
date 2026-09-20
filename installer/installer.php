<?php
/**
 * Perpus Auto-Installer — INLISLite V3 + SLiMS 9 Bulian, satu file.
 *
 * Cara pakai (cPanel, tanpa SSH):
 *  1. Upload file ini + artefak build (inlis.zip, slims.zip, sql-*.sql)
 *     ke satu folder di hosting (mis. public_html/installer/ atau docroot subdomain).
 *  2. Buka di browser: https://domain/id/installer/installer.php
 *  3. Isi form (mode, target dir, kredensial 2 database, URL, admin) → Install.
 *  4. Setelah selesai: centang "hapus installer + artefak" lalu Finish.
 *     Hapus juga folder slims/install/ (dilakukan otomatis oleh installer).
 *
 * Mode:
 *  - both  : satu hosting, dua app (inlis/ + slims/).
 *  - inlis : hosting ini hanya INLISLite (docroot diarahkan ke <target>/public).
 *  - slims : hosting ini hanya SLiMS (docroot = <target>).
 *
 * Artefak yang dicari di direktori yang sama dengan file ini:
 *  inlis.zip, slims.zip, sql-inlis.sql, sql-slims-schema.sql, sql-slims-sample.sql
 * Part-split didukung: inlis.zip.001, inlis.zip.002, ... (digabung otomatis).
 *
 * Kebutuhan server: PHP >= 8.2, ext mysqli+zip, Max 512MB+ disarankan.
 * DB dibuat manual dulu via cPanel (installer hanya import + tulis config).
 */

// ponytail: single-file agar cukup upload via File Manager. Untuk SSH gunakan install.sh.

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
@set_time_limit(0);
@ignore_user_abort(true);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

define('INST_VER', '1.0.0');
define('INST_DIR', __DIR__);
define('STATE_FILE', INST_DIR . '/.install-state.json');

header('X-Content-Type-Options: nosniff');

/* ---------------- helpers ---------------- */

function jout($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function state_load() {
    if (!file_exists(STATE_FILE)) return [];
    $s = json_decode(@file_get_contents(STATE_FILE), true);
    return is_array($s) ? $s : [];
}

function state_save($s) {
    @file_put_contents(STATE_FILE, json_encode($s), LOCK_EX);
}

function csrf_token() {
    if (empty($_SESSION['perpus_csrf'])) {
        $_SESSION['perpus_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['perpus_csrf'];
}

function csrf_check($t) {
    return isset($_SESSION['perpus_csrf']) && hash_equals($_SESSION['perpus_csrf'], (string)$t);
}

function clean_dir($p) {
    $p = trim(str_replace(chr(0), '', $p));
    $p = preg_replace('#/+#', '/', $p);
    if ($p === '' || $p === '/' || $p === '.' || strpos($p, '..') !== false) return null;
    if ($p[0] === '/') return null; // relatif saja dari INST_DIR
    return rtrim($p, '/');
}

function join_parts($base, $log) {
    // Gabung base.001, base.002, ... menjadi $base. Return path hasil.
    if (file_exists(INST_DIR . '/' . $base)) return $base;
    $parts = glob(INST_DIR . '/' . $base . '.[0-9][0-9][0-9]*');
    natsort($parts);
    if (!$parts) return null;
    $out = INST_DIR . '/' . $base;
    $fh = fopen($out, 'wb');
    if (!$fh) return null;
    foreach ($parts as $pt) {
        $in = fopen($pt, 'rb');
        if (!$in) { fclose($fh); return null; }
        stream_copy_to_stream($in, $fh);
        fclose($in);
        $log[] = "gabung " . basename($pt);
    }
    fclose($fh);
    return $base;
}

function zip_extract($zipPath, $dest) {
    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) return "gagal buka $zipPath";
    if (!is_dir($dest) && !@mkdir($dest, 0755, true)) return "gagal buat dir $dest";
    if (!$z->extractTo($dest)) { $z->close(); return "gagal ekstrak ke $dest"; }
    $z->close();
    return null;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/**
 * Eksekusi file SQL besar secara chunked.
 * State: ['offset'=>int (posisi byte absolut konsumsi), 'cur'=>'',
 *   'stmt'=>int, 'errors'=>[]].
 * - $cur (statement parsial incl. quote/comment state) PERSISTEN antar
 *   request: byte-nya belum dikonsumsi, offset tidak maju melewatinya.
 * - Tidak ada lagi field 'buf': tidak ada sisa buffer tak-terhitung.
 * - Offset selalu absolut; fseek() dicek, gagal -> error + stop.
 * Aman untuk dump tanpa DELIMITER (ketiga file SQL tidak mengandungnya).
 */
function sql_import_chunk($mysqli, $file, &$st, $maxStmt = 150, $maxSec = 20) {
    $t0 = microtime(true);
    $st['offset'] = isset($st['offset']) ? (int)$st['offset'] : 0;
    $cur = isset($st['cur']) ? $st['cur'] : '';
    $fh = fopen($file, 'rb');
    if (!$fh) { $st['errors'][] = "tidak bisa baca $file"; return true; }
    if (fseek($fh, $st['offset']) !== 0) {
        $st['errors'][] = "fseek gagal ke offset {$st['offset']} di $file";
        fclose($fh);
        return true;
    }
    $done = 0;
    $inS = $inD = $inB = $inL = $inC = false; // ' " ` -- # /* */
    $base = $st['offset']; // byte absolut awal request ini

    $emit = function ($sql) use ($mysqli, &$st) {
        $sql = trim($sql);
        if ($sql === '') return true;
        if (!$mysqli->query($sql)) {
            $st['errors'][] = 'MySQL ' . $mysqli->errno . ': ' . $mysqli->error
                . ' | ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 160);
            return false;
        }
        $st['stmt']++;
        return true;
    };

    $buf = $cur . fread($fh, 1048576);
    if ($buf === false) $buf = '';
    $consumedNew = strlen($buf) - strlen($cur); // byte baru dibaca request ini
    $cur = '';
    $len = strlen($buf);
    $i = 0;

    while (true) {
        while ($i < $len) {
            $c = $buf[$i];
            $n = ($i + 1 < $len) ? $buf[$i + 1] : '';
            if ($inL) {
                $cur .= $c;
                if ($c === "\n") $inL = false;
                $i++;
                continue;
            }
            if ($inC) {
                $cur .= $c;
                if ($c === '*' && $n === '/') { $cur .= $n; $i += 2; $inC = false; continue; }
                $i++;
                continue;
            }
            if ($inS) {
                $cur .= $c;
                if ($c === '\\' && $n !== '') { $cur .= $n; $i += 2; continue; }
                if ($c === "'") $inS = false;
                $i++;
                continue;
            }
            if ($inD) {
                $cur .= $c;
                if ($c === '"') $inD = false;
                $i++;
                continue;
            }
            if ($inB) {
                $cur .= $c;
                if ($c === '`') $inB = false;
                $i++;
                continue;
            }
            // state normal
            if ($c === '-' && $n === '-' && isset($buf[$i+2]) && ctype_space($buf[$i+2])) { $inL = true; $cur .= $c; $i++; continue; }
            if ($c === '#') { $inL = true; $cur .= $c; $i++; continue; }
            if ($c === '/' && $n === '*') { $inC = true; $cur .= $c; $i++; continue; }
            if ($c === "'") { $inS = true; $cur .= $c; $i++; continue; }
            if ($c === '"') { $inD = true; $cur .= $c; $i++; continue; }
            if ($c === '`') { $inB = true; $cur .= $c; $i++; continue; }
            if ($c === ';') {
                if (!$emit($cur)) { fclose($fh); return true; }
                $cur = '';
                $done++;
                $i++;
                if ($done >= $maxStmt || (microtime(true) - $t0) > $maxSec) {
                    // offset absolut = awal request + byte baru yg ter-parse.
                    // $cur (parsial) disimpan, byte-nya BELUM dikonsumsi.
                    $st['offset'] = $base + $i - (strlen($buf) - $consumedNew);
                    $st['cur'] = $cur;
                    fclose($fh);
                    return false;
                }
                continue;
            }
            $cur .= $c;
            $i++;
        }
        // Buffer habis. $cur (parsial + quote/comment state) TETAP di $cur,
        // offset tetap di $base; byte baru di-append. Sentinel trailing
        // (-, /, backslash) tidak perlu pemisahan khusus: karena $cur
        // utuh + state quote/comment ikut terbawa, batas token tak pernah
        // terbelah antar request. Sengaja TANPA normalisasi CRLF: semua
        // byte dihitung 1:1 lawan posisi file agar resume offset exact.
        $chunk = fread($fh, 1048576);
        if ($chunk === false || $chunk === '') break;
        $buf = $cur . $chunk;
        $consumedNew += strlen($chunk);
        $cur = '';
        $len = strlen($buf);
        $i = 0;
    }
    // EOF
    $rest = trim($cur . substr($buf, $i));
    if ($rest !== '') {
        if (!$emit($rest)) { fclose($fh); return true; }
    }
    $st['offset'] = $base + $consumedNew;
    $st['cur'] = '';
    fclose($fh);
    return true;
}

/* ---------------- actions ---------------- */

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;

if ($action === 'check') {
    $req = ['mysqli' => extension_loaded('mysqli'), 'zip' => extension_loaded('zip') || class_exists('ZipArchive')];
    $opt = [];
    foreach (['gd','intl','gettext','mbstring','curl','xml','iconv','fileinfo','openssl'] as $e) {
        $opt[$e] = extension_loaded($e);
    }
    jout([
        'php' => PHP_VERSION,
        'php_ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'required' => $req,
        'optional' => $opt,
        'writable' => is_writable(INST_DIR),
        'files' => [
            'inlis.zip' => file_exists(INST_DIR . '/inlis.zip') || count(glob(INST_DIR . '/inlis.zip.???*')) > 0,
            'slims.zip' => file_exists(INST_DIR . '/slims.zip') || count(glob(INST_DIR . '/slims.zip.???*')) > 0,
            'sql-inlis.sql' => file_exists(INST_DIR . '/sql-inlis.sql'),
            'sql-slims-schema.sql' => file_exists(INST_DIR . '/sql-slims-schema.sql'),
            'sql-slims-sample.sql' => file_exists(INST_DIR . '/sql-slims-sample.sql'),
            'sql-captcha.sql' => file_exists(INST_DIR . '/sql-captcha.sql'),
        ],
    ]);
}

if ($action !== null && $action !== 'check' && !csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
    jout(['ok' => false, 'error' => 'CSRF token tidak valid. Muat ulang halaman.'], 403);
}

if ($action === 'extract') {
    $mode = $_POST['mode'];
    $log = [];
    if (!in_array($mode, ['both','inlis','slims'], true)) jout(['ok'=>false,'error'=>'mode invalid']);
    $targets = [];
    if ($mode === 'both' || $mode === 'inlis') {
        $d = clean_dir($_POST['inlis_dir']);
        if ($d === null) jout(['ok'=>false,'error'=>'inlis_dir invalid']);
        $targets['inlis'] = $d;
    }
    if ($mode === 'both' || $mode === 'slims') {
        $d = clean_dir($_POST['slims_dir']);
        if ($d === null) jout(['ok'=>false,'error'=>'slims_dir invalid']);
        $targets['slims'] = $d;
    }
    foreach ($targets as $app => $dir) {
        $zip = join_parts($app . '.zip', $log);
        if ($zip === null) jout(['ok'=>false,'error'=>"$app.zip tidak ditemukan (termasuk part .001..)","log"=>$log]);
        $err = zip_extract(INST_DIR . '/' . $zip, INST_DIR . '/' . $dir);
        if ($err) jout(['ok'=>false,'error'=>$err,'log'=>$log]);
        $log[] = "$app.zip → $dir/ OK";
    }
    $st = state_load();
    $st['mode'] = $mode;
    $st['targets'] = $targets;
    state_save($st);
    jout(['ok'=>true,'log'=>$log]);
}

if ($action === 'import') {
    $which = $_POST['which']; // inlis | slims_schema | slims_sample | captcha
    $map = ['inlis'=>'sql-inlis.sql','slims_schema'=>'sql-slims-schema.sql','slims_sample'=>'sql-slims-sample.sql','captcha'=>'sql-captcha.sql'];
    if (!isset($map[$which])) jout(['ok'=>false,'error'=>'which invalid']);
    $st = state_load();
    $key = 'imp_' . $which;
    if (!isset($st[$key])) $st[$key] = ['offset'=>0,'cur'=>'','stmt'=>0,'errors'=>[]];
    $m = new mysqli($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], (int)$_POST['db_port']);
    if ($m->connect_error) jout(['ok'=>false,'error'=>'koneksi DB gagal: '.$m->connect_error]);
    $m->set_charset('utf8mb4');
    $file = INST_DIR . '/' . $map[$which];
    if (!file_exists($file)) {
        // Seed captcha opsional: lewati bila artefak tidak diupload.
        if ($which === 'captcha') jout(['ok'=>true,'done'=>true,'stmt'=>0,'progress'=>100,'skipped'=>true]);
        jout(['ok'=>false,'error'=>$map[$which].' tidak ditemukan']);
    }
    $total = filesize($file);
    $finished = sql_import_chunk($m, $file, $st[$key]);
    state_save($st);
    $err = $st[$key]['errors'];
    if ($err) jout(['ok'=>false,'error'=>implode("\n",$err),'stmt'=>$st[$key]['stmt']]);
    jout(['ok'=>true,'done'=>$finished,'stmt'=>$st[$key]['stmt'],
        'progress'=>$total>0?round(100*$st[$key]['offset']/$total,1):0]);
}

if ($action === 'config') {
    $st = state_load();
    $mode = $st['mode'];
    $tg = $st['targets'];
    $log = [];

    $setEnv = function ($path, $pairs) {
        $c = @file_get_contents($path);
        if ($c === false) return "tidak bisa baca $path";
        foreach ($pairs as $k => $v) {
            $pat = '/^([#\s]*' . preg_quote($k, '/') . '\s*=).*/m';
            if (preg_match($pat, $c)) {
                // callback, bukan replacement string: nilai $v boleh berisi
                // $ atau \ (mis. password) tanpa diartikan backreference.
                $c = preg_replace_callback($pat, function ($m) use ($v) {
                    return $m[1] . ' ' . $v;
                }, $c);
            } else {
                $c .= "\n$k = $v\n";
            }
        }
        return @file_put_contents($path, $c, LOCK_EX) === false ? "tidak bisa tulis $path" : null;
    };

    if ($mode === 'both' || $mode === 'inlis') {
        $base = INST_DIR . '/' . $tg['inlis'];
        $sample = $base . '/env.sample';
        if (!file_exists($sample)) jout(['ok'=>false,'error'=>'env.sample tidak ada di '.$tg['inlis']]);
        @copy($sample, $base . '/.env');
        $token = bin2hex(random_bytes(32));
        $err = $setEnv($base . '/.env', [
            'CI_ENVIRONMENT' => 'production',
            'app.baseURL' => "'" . rtrim($_POST['inlis_url'], '/') . "/'",
            'database.default.hostname' => $_POST['inlis_db_host'],
            'database.default.database' => $_POST['inlis_db_name'],
            'database.default.username' => $_POST['inlis_db_user'],
            'database.default.password' => $_POST['inlis_db_pass'],
            'database.default.port' => (int)$_POST['inlis_db_port'],
            'database.data.hostname' => $_POST['inlis_db_host'],
            'database.data.database' => $_POST['inlis_db_name'],
            'database.data.username' => $_POST['inlis_db_user'],
            'database.data.password' => $_POST['inlis_db_pass'],
            'database.data.port' => (int)$_POST['inlis_db_port'],
            'security.TOKEN_SECRET' => $token,
        ]);
        if ($err) jout(['ok'=>false,'error'=>$err]);
        @chmod($base . '/writable', 0755);
        $log[] = '.env INLISLite OK';
    }

    if ($mode === 'both' || $mode === 'slims') {
        $base = INST_DIR . '/' . $tg['slims'];
        if (!is_dir($base . '/config')) jout(['ok'=>false,'error'=>'folder config/ tidak ada di '.$tg['slims']]);
        $https = (stripos($_POST['slims_url'], 'https://') === 0) ? 'true' : 'false';
        $dbphp = "<?php\n\nreturn [\n    'default_profile' => 'SLiMS',\n    'proxy' => false,\n    'nodes' => [\n        'SLiMS' => [\n            'host' => '" . addslashes($_POST['slims_db_host']) . "',\n"
            . "            'database' => '" . addslashes($_POST['slims_db_name']) . "',\n"
            . "            'port' => '" . (int)$_POST['slims_db_port'] . "',\n"
            . "            'username' => '" . addslashes($_POST['slims_db_user']) . "',\n"
            . "            'password' => '" . addslashes($_POST['slims_db_pass']) . "',\n"
            . "            'options' => [\n                'storage_engine' => 'InnoDB'\n            ]\n"
            . "        ],\n    ]\n];\n";
        if (@file_put_contents($base . '/config/database.php', $dbphp, LOCK_EX) === false) {
            jout(['ok'=>false,'error'=>'config/ tidak writable — chmod 755 folder config/ SLiMS dulu']);
        }
        $envsample = $base . '/config/env.sample.php';
        if (file_exists($envsample)) {
            $e = file_get_contents($envsample);
            $e = str_replace(["'<environment>'","'<conditional_environment>'","'<based_on_ip>'"],
                ["'production'","'production'","false"], $e);
            @file_put_contents($base . '/config/env.php', $e, LOCK_EX);
        }
        $urlsample = $base . '/config/url.sample.php';
        if (file_exists($urlsample)) {
            $u = file_get_contents($urlsample);
            $u = str_replace("'force_https' => false", "'force_https' => $https", $u);
            @file_put_contents($base . '/config/url.php', $u, LOCK_EX);
        }
        foreach (['files','images','repository','config'] as $dd) {
            if (is_dir($base . '/' . $dd)) @chmod($base . '/' . $dd, 0755);
        }
        $log[] = 'config SLiMS OK';
    }
    jout(['ok'=>true,'log'=>$log]);
}

if ($action === 'admin') {
    $st = state_load();
    $mode = $st['mode'];
    $tg = $st['targets'];
    $log = [];
    $u = trim($_POST['admin_user']);
    $p = (string)$_POST['admin_pass'];
    if ($u === '' || $p === '') jout(['ok'=>false,'error'=>'admin user/pass wajib diisi']);

    if ($mode === 'both' || $mode === 'inlis') {
        $m = new mysqli($_POST['inlis_db_host'], $_POST['inlis_db_user'], $_POST['inlis_db_pass'], $_POST['inlis_db_name'], (int)$_POST['inlis_db_port']);
        if ($m->connect_error) jout(['ok'=>false,'error'=>'INLIS DB: '.$m->connect_error]);
        // Myth/Auth: hash = bcrypt(base64(sha384(pass))) — password_hash polos TIDAK bisa login
        $h = password_hash(base64_encode(hash('sha384', $p, true)), PASSWORD_DEFAULT, ['cost' => 10]);
        $stmt = $m->prepare('UPDATE users SET password_hash=?, username=?, active=1 WHERE id=1');
        $stmt->bind_param('ss', $h, $u);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            $stmt2 = $m->prepare('UPDATE users SET password_hash=?, active=1 WHERE username=?');
            $stmt2->bind_param('ss', $h, $u);
            $stmt2->execute();
            if ($stmt2->affected_rows < 1) jout(['ok'=>false,'error'=>'user admin INLISLite tidak ketemu (id=1 / username)']);
        }
        $log[] = 'admin INLISLite OK';
    }
    if ($mode === 'both' || $mode === 'slims') {
        $m = new mysqli($_POST['slims_db_host'], $_POST['slims_db_user'], $_POST['slims_db_pass'], $_POST['slims_db_name'], (int)$_POST['slims_db_port']);
        if ($m->connect_error) jout(['ok'=>false,'error'=>'SLiMS DB: '.$m->connect_error]);
        $h = password_hash($p, PASSWORD_BCRYPT);
        $stmt = $m->prepare('UPDATE user SET passwd=?, username=? WHERE user_id=1');
        $stmt->bind_param('ss', $h, $u);
        $stmt->execute();
        if ($stmt->affected_rows < 1) jout(['ok'=>false,'error'=>'user admin SLiMS (user_id=1) tidak ketemu']);
        $inst = INST_DIR . '/' . $tg['slims'] . '/install';
        if (is_dir($inst)) { rrmdir($inst); $log[] = 'folder slims/install/ dihapus'; }
        $log[] = 'admin SLiMS OK';
    }
    jout(['ok'=>true,'log'=>$log]);
}

if ($action === 'captcha') {
    // Tulis setting Captcha terpilih + kunci (secret hanya bila diisi).
    // Dipanggil setelah import sql-captcha.sql (menu+permission).
    $st = state_load();
    $tg = isset($st['targets']) && is_array($st['targets']) ? $st['targets'] : [];
    // Flow uji/manual tanpa langkah extract: izinkan target default.
    if (!isset($tg['inlis']) && isset($_POST['inlis_dir'])) {
        $d = clean_dir($_POST['inlis_dir']);
        if ($d !== null) $tg['inlis'] = $d;
    }
    $log = [];
    if (!isset($tg['inlis'])) jout(['ok'=>false,'error'=>'captcha butuh target inlis (mode both/inlis)']);
    $provider = isset($_POST['captcha_provider']) ? $_POST['captcha_provider'] : 'off';
    if (!in_array($provider, ['off', 'hcaptcha', 'turnstile', 'recaptcha'], true)) jout(['ok'=>false,'error'=>'provider captcha invalid']);
    $site = trim((string)($_POST['captcha_site'] ?? ''));
    $secret = trim((string)($_POST['captcha_secret'] ?? ''));
    if (strlen($site) > 255 || strlen($secret) > 255) jout(['ok'=>false,'error'=>'kunci captcha terlalu panjang']);
    $m = new mysqli($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name'], (int)$_POST['db_port']);
    if ($m->connect_error) jout(['ok'=>false,'error'=>'koneksi DB gagal: '.$m->connect_error]);
    $m->set_charset('utf8mb4');
    $upsert = function ($name, $value) use ($m, &$log) {
        $stmt = $m->prepare('SELECT ID FROM settingparameters WHERE Name=?');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            $stmt = $m->prepare('UPDATE settingparameters SET Value=? WHERE Name=?');
            $stmt->bind_param('ss', $value, $name);
        } else {
            $stmt = $m->prepare('INSERT INTO settingparameters (Name, Value) VALUES (?, ?)');
            $stmt->bind_param('ss', $name, $value);
        }
        if (!$stmt->execute()) { $e = $m->error; $stmt->close(); return $e; }
        $stmt->close();
        $log[] = "setting $name OK";
        return null;
    };
    $err = $upsert('CaptchaProvider', $provider);
    if ($err === null && $site !== '') $err = $upsert('CaptchaSite', $site);
    if ($err === null && $secret !== '') $err = $upsert('CaptchaSecret', $secret);
    if ($err !== null) jout(['ok'=>false,'error'=>'gagal tulis setting captcha: '.$err]);
    // Sinkronkan juga fallback .env bila kunci diisi eksplisit.
    if ($site !== '' || $secret !== '') {
        $envPath = INST_DIR . '/' . $tg['inlis'] . '/.env';
        $c = @file_get_contents($envPath);
        if ($c === false) jout(['ok'=>false,'error'=>'tidak bisa baca .env captcha']);
        $setEnvLine = function ($content, $key, $val) {
            $pat = '/^' . preg_quote($key, '/') . '=.*/m';
            $rep = function () use ($key, $val) { return $key . '=' . $val; };
            if (preg_match($pat, $content)) {
                return preg_replace_callback($pat, $rep, $content);
            }
            return rtrim($content, "\r\n") . "\n$key=$val\n";
        };
        $envKeys = ['hcaptcha' => ['HCAPTCHA_SITE_KEY', 'HCAPTCHA_SECRET_KEY'], 'turnstile' => ['TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY'], 'recaptcha' => ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY']][$provider] ?? null;
        if ($site !== '' && $envKeys) $c = $setEnvLine($c, $envKeys[0], $site);
        if ($secret !== '' && $envKeys) $c = $setEnvLine($c, $envKeys[1], $secret);
        if (@file_put_contents($envPath, $c, LOCK_EX) === false) jout(['ok'=>false,'error'=>'tidak bisa tulis .env captcha']);
        $log[] = '.env kunci captcha OK';
    }
    jout(['ok'=>true,'log'=>$log]);
}

if ($action === 'finish') {
    $log = [];
    if (!empty($_POST['cleanup']) && $_POST['cleanup'] === '1') {
        foreach (array_merge(glob(INST_DIR.'/*.zip'), glob(INST_DIR.'/*.zip.???*'), glob(INST_DIR.'/sql-*.sql')) as $f) {
            @unlink($f);
            $log[] = 'hapus ' . basename($f);
        }
        @unlink(STATE_FILE);
        @unlink(__FILE__);
        jout(['ok'=>true,'log'=>$log,'self_deleted'=>true]);
    }
    jout(['ok'=>true,'log'=>$log,'self_deleted'=>false]);
}

/* ---------------- UI ---------------- */
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Perpus Auto-Installer v<?= INST_VER ?></title>
<style>
body{font-family:system-ui,sans-serif;max-width:760px;margin:2em auto;padding:0 1em;color:#222}
h1{font-size:1.4em} fieldset{margin-bottom:1em} label{display:block;margin:.4em 0}
input[type=text],input[type=password],input[type=number]{width:100%;padding:.4em;box-sizing:border-box}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:.5em 1em}
button{padding:.6em 1.2em;font-size:1em;cursor:pointer}
#log{background:#111;color:#0f0;padding:1em;white-space:pre-wrap;min-height:120px;font-size:.85em}
.bar{height:10px;background:#ddd;margin:.3em 0}.bar>i{display:block;height:100%;background:#28a745;width:0}
.note{background:#fff8e1;padding:.6em;border-left:4px solid #ffb300;font-size:.9em}
</style>
</head>
<body>
<h1>Perpus Auto-Installer v<?= INST_VER ?> — INLISLite + SLiMS</h1>
<div class="note">Buat <b>2 database + user</b> dulu via cPanel → MySQL Databases.
Docroot subdomain INLISLite harus ke <code>.../inlis/public</code>, SLiMS ke <code>.../slims</code>.
Lihat <code>DEPLOY-SATU-HOSTING.md</code> untuk langkah cPanel lengkap.</div>
<p><button id="btn-check" type="button">1. Cek server</button></p>
<div id="check-out"></div>
<form id="f">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
<fieldset><legend>Mode &amp; folder target (relatif dari lokasi installer ini)</legend>
<label>Mode
<select name="mode" id="mode">
<option value="both">Keduanya (satu hosting)</option>
<option value="inlis">Hanya INLISLite (hosting ini)</option>
<option value="slims">Hanya SLiMS (hosting ini)</option>
</select></label>
<div class="grid">
<label>Folder INLISLite <input type="text" name="inlis_dir" value="inlis"></label>
<label>Folder SLiMS <input type="text" name="slims_dir" value="slims"></label>
</div></fieldset>
<fieldset id="fs-inlis"><legend>INLISLite</legend>
<div class="grid">
<label>URL <input type="text" name="inlis_url" placeholder="https://inlis.domain.id"></label>
<label>DB host <input type="text" name="inlis_db_host" value="localhost"></label>
<label>DB name <input type="text" name="inlis_db_name"></label>
<label>DB user <input type="text" name="inlis_db_user"></label>
<label>DB pass <input type="password" name="inlis_db_pass"></label>
<label>DB port <input type="number" name="inlis_db_port" value="3306"></label>
</div>
<details style="margin-top:.5em"><summary>Captcha login (opsional, per customer)</summary>
<div class="grid">
<label>Provider <select name="captcha_provider"><option value="off">Nonaktif</option><option value="hcaptcha">hCaptcha</option><option value="turnstile">Cloudflare Turnstile</option><option value="recaptcha">Google reCAPTCHA v2</option></select></label>
<label>Site key <input type="text" name="captcha_site" placeholder="kosongkan = pakai .env"></label>
<label>Secret key <input type="password" name="captcha_secret" placeholder="kosongkan = pakai .env"></label>
</div>
<div class="note">Menu + permission Captcha selalu di-seed. Provider/keys di sini ditulis ke settingparameters (secret hanya bila diisi). Fallback .env: HCAPTCHA_*, TURNSTILE_*, RECAPTCHA_*.</div>
</details></fieldset>
<fieldset id="fs-slims"><legend>SLiMS</legend>
<label><input type="checkbox" name="slims_sample" value="1" checked> Import sample data</label>
<div class="grid">
<label>URL <input type="text" name="slims_url" placeholder="https://slims.domain.id"></label>
<label>DB host <input type="text" name="slims_db_host" value="localhost"></label>
<label>DB name <input type="text" name="slims_db_name"></label>
<label>DB user <input type="text" name="slims_db_user"></label>
<label>DB pass <input type="password" name="slims_db_pass"></label>
<label>DB port <input type="number" name="slims_db_port" value="3306"></label>
</div></fieldset>
<fieldset><legend>Admin (diset ke kedua app, id=1)</legend>
<div class="grid">
<label>Username <input type="text" name="admin_user" value="admin"></label>
<label>Password <input type="password" name="admin_pass" value="admin"></label>
<div class="note">Default admin/admin — segera ganti setelah install!</div>
</div></fieldset>
<p><button id="btn-go" type="button">2. Install sekarang</button></p>
<div class="bar"><i id="pbar"></i></div>
</form>
<h3>Log</h3>
<div id="log"></div>
<p><label><input type="checkbox" id="cleanup" checked> Hapus installer + artefak setelah selesai</label>
<button id="btn-finish" type="button">3. Finish</button></p>
<script>
const $=id=>document.getElementById(id);
const log=m=>{$('log').textContent+=m+"\n";};
const fd=extra=>{const f=new FormData($('f'));for(const k in extra)f.set(k,extra[k]);return f;};
const post=async(extra)=>{const r=await fetch('?action='+extra.action,{method:'POST',body:fd(extra)});return r.json();};
$('mode').onchange=e=>{const m=e.target.value;$('fs-inlis').style.display=(m==='slims')?'none':'';$('fs-slims').style.display=(m==='inlis')?'none':'';};
$('btn-check').onclick=async()=>{const r=await fetch('?action=check');const j=await r.json();
$('check-out').innerHTML='<pre>'+JSON.stringify(j,null,1)+'</pre>';
log('PHP '+j.php+' (>=8.2: '+(j.php_ok?'OK':'GAGAL')+'), writable: '+(j.writable?'OK':'GAGAL'));};
async function importLoop(which,args,label){for(;;){const j=await post({action:'import',which,...args});
if(!j.ok){log('GAGAL '+label+': '+j.error);throw 0;}
$('pbar').style.width=(j.progress||0)+'%';log(label+': '+j.stmt+' stmt ('+(j.progress||0)+'%)');
if(j.done)break;}}
$('btn-go').onclick=async()=>{try{
const mode=$('mode').value;
log('== extract ==');
let j=await post({action:'extract'});if(!j.ok){log('GAGAL: '+j.error);return;}j.log.forEach(log);
log('== import INLISLite ==');
if(mode!=='slims'){const f=$('f');await importLoop('inlis',{db_host:f.inlis_db_host.value,db_user:f.inlis_db_user.value,db_pass:f.inlis_db_pass.value,db_name:f.inlis_db_name.value,db_port:f.inlis_db_port.value},'inlis');}
log('== import SLiMS ==');
if(mode!=='inlis'){const f=$('f');const a={db_host:f.slims_db_host.value,db_user:f.slims_db_user.value,db_pass:f.slims_db_pass.value,db_name:f.slims_db_name.value,db_port:f.slims_db_port.value};
await importLoop('slims_schema',a,'slims schema');
if(f.slims_sample.checked)await importLoop('slims_sample',a,'slims sample');}
log('== config ==');
j=await post({action:'config'});if(!j.ok){log('GAGAL: '+j.error);return;}j.log.forEach(log);
log('== admin ==');
j=await post({action:'admin'});if(!j.ok){log('GAGAL: '+j.error);return;}j.log.forEach(log);
if(mode!=='slims'){const fc=$('f');await importLoop('captcha',{db_host:fc.inlis_db_host.value,db_user:fc.inlis_db_user.value,db_pass:fc.inlis_db_pass.value,db_name:fc.inlis_db_name.value,db_port:fc.inlis_db_port.value},'captcha menu');
try{j=await post({action:'captcha',db_host:fc.inlis_db_host.value,db_user:fc.inlis_db_user.value,db_pass:fc.inlis_db_pass.value,db_name:fc.inlis_db_name.value,db_port:fc.inlis_db_port.value,captcha_provider:(fc.captcha_provider||{}).value||'off',captcha_site:(fc.captcha_site||{}).value||'',captcha_secret:(fc.captcha_secret||{}).value||''});}catch(e){j={ok:false,error:String(e)};}if(!j.ok){log('GAGAL: '+j.error);return;}j.log.forEach(log);}
$('pbar').style.width='100%';log('SELESAI. Cek URL kedua app, lalu Finish.');
}catch(e){log('Berhenti karena error. Perbaiki lalu klik Install lagi (import resume otomatis).');}};
$('btn-finish').onclick=async()=>{const j=await post({action:'finish',cleanup:$('cleanup').checked?'1':'0'});
(j.log||[]).forEach(log);if(j.self_deleted)log('Installer menghapus dirinya sendiri. Selesai.');};
</script>
</body>
</html>
