#!/usr/bin/env php
<?php
/**
 * apply-mixcode.php — pasang Label Mixcode Warna ke hasil install INLISLite.
 *
 * Dipakai oleh installer/install.sh (--mixcode) dan installer/installer.php
 * (checkbox). Bisa juga jalan mandiri:
 *
 *   php apply-mixcode.php --app-dir=/path/ke/inlis --zip=mixcode.zip [--check]
 *
 * Isi mixcode.zip (dibuat installer/build.sh):
 *   overlay/app/...   → disalin ke <app-dir>/app/...
 *   patches/*.diff    → diterapkan dengan mini-applier di bawah
 *                       (tanpa butuh `git`/`patch` di hosting)
 *   sql-mixcode.sql   → diimport terpisah oleh installer (lihat README)
 *
 * Keluar 0 bila semua diterapkan; 1 + pesan bila ada anchor tak cocok.
 */

function mix_fail($msg) {
    fwrite(STDERR, "mixcode: $msg\n");
    exit(1);
}

define('MIXCODE_VER', '1.0.0');
define('MIXCODE_SENTINEL', '.mixcode-installed');

/** Terapkan satu unified diff ke $root. Kembalikan daftar file terapan. */
function mix_apply_patch($root, $patch) {
    $lines = preg_split("/\r?\n/", $patch);
    $files = [];
    $cur = null; // ['old'=>, 'new'=>, 'hunks'=>[]]
    $i = 0; $n = count($lines);
    $flush = function () use (&$cur, &$files, $root) {
        if ($cur === null) return;
        mix_apply_file($root, $cur);
        $files[] = $cur['new'];
        $cur = null;
    };
    while ($i < $n) {
        $line = $lines[$i];
        if (str_starts_with($line, '--- ')) {
            $flush();
            $old = preg_replace('/^[ab]\//', '', substr($line, 4));
            $i++;
            if ($i >= $n || !str_starts_with($lines[$i], '+++ ')) mix_fail('patch rusak (tanpa +++).');
            $new = preg_replace('/^[ab]\//', '', substr($lines[$i], 4));
            $cur = ['old' => trim($old), 'new' => trim($new), 'hunks' => []];
        } elseif (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $m)) {
            $hunk = [];
            $i++;
            while ($i < $n && $lines[$i] !== '' && in_array($lines[$i][0], [' ', '-', '+', '\\'], true)) {
                if (str_starts_with($lines[$i], '\\')) { $i++; continue; }
                $hunk[] = $lines[$i];
                $i++;
            }
            $cur['hunks'][] = $hunk;
            continue;
        }
        $i++;
    }
    $flush();
    return $files;
}

function mix_apply_file($root, $file) {
    $path = rtrim($root, '/') . '/' . ltrim($file['new'], '/');
    if (!is_file($path)) mix_fail("file tidak ada: {$file['new']}");
    $raw = file_get_contents($path);
    $eol = (strpos($raw, "\r\n") !== false) ? "\r\n" : "\n";
    $flines = preg_split("/\r?\n/", $raw);
    // Hapus baris kosong semu di ujung akibat split (jaga EOF newline).
    $hasTrailingNl = (bool) preg_match("/\r?\n\$/", $raw);
    if (end($flines) === '') array_pop($flines);

    $offset = 0; // geser akumulasi akibat hunk sebelumnya
    foreach ($file['hunks'] as $hi => $hunk) {
        // Bangun pola: baris konteks/hapus (tanpa awalan).
        $pat = [];
        foreach ($hunk as $hl) {
            $op = $hl[0];
            if ($op === ' ' || $op === '-') $pat[] = substr($hl, 1);
        }
        // Cari posisi: mulai dari offset, cocokkan longgar (\r diabaikan).
        $found = -1;
        $plen = count($pat);
        for ($s = $offset; $s + $plen <= count($flines); $s++) {
            $ok = true;
            for ($k = 0; $k < $plen; $k++) {
                if (rtrim($flines[$s + $k], "\r") !== rtrim($pat[$k], "\r")) { $ok = false; break; }
            }
            if ($ok) { $found = $s; break; }
        }
        if ($found < 0) mix_fail("hunk #" . ($hi + 1) . " tak cocok di {$file['new']}");
        // Terapkan.
        $out = [];
        $j = $found;
        foreach ($hunk as $hl) {
            $op = $hl[0]; $body = substr($hl, 1);
            if ($op === ' ') { $out[] = $flines[$j]; $j++; }
            elseif ($op === '-') {
                if (rtrim($flines[$j], "\r") !== rtrim($body, "\r")) mix_fail("konteks berubah di {$file['new']}");
                $j++;
            } elseif ($op === '+') { $out[] = $body; }
        }
        array_splice($flines, $found, $j - $found, $out);
        $offset = $found + count($out);
    }
    $data = implode($eol, $flines) . ($hasTrailingNl ? $eol : '');
    if (@file_put_contents($path, $data, LOCK_EX) === false) mix_fail("gagal tulis {$file['new']}");
}

/** Salin overlay/app/* dari zip ke app dir. */
function mix_overlay($root, $zipPath) {
    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) mix_fail("tak bisa buka $zipPath (butuh ekstensi zip)");
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = $z->getNameIndex($i);
        if (!str_starts_with($name, 'overlay/') || str_ends_with($name, '/')) continue;
        $rel = substr($name, strlen('overlay/'));
        // Keamanan: tolak path traversal.
        if (strpos($rel, '..') !== false) mix_fail("path zip jahat: $rel");
        $dest = rtrim($root, '/') . '/' . $rel;
        @mkdir(dirname($dest), 0755, true);
        if (@file_put_contents($dest, $z->getFromIndex($i), LOCK_EX) === false) mix_fail("gagal tulis $rel");
    }
    $z->close();
}

// ---- CLI ----
/** Pasang penuh (overlay + patch). Dipakai CLI di bawah dan installer.php.
 *  Kembalikan ['ok'=>bool, 'log'=>[...], 'error'=>...]. */
function mixcode_install($app, $zip) {
    $log = [];
    if (!is_dir($app)) return ['ok' => false, 'error' => "app-dir bukan folder: $app"];
    if (!is_file($zip)) return ['ok' => false, 'error' => "zip tak ada: $zip"];
    // 1. overlay
    $z = new ZipArchive();
    if ($z->open($zip) !== true) return ['ok' => false, 'error' => "tak bisa buka $zip (butuh ekstensi zip)"];
    $z->close();
    mix_overlay($app, $zip);
    $log[] = 'overlay OK';
    // 2. patches dari zip
    $patches = [];
    $z = new ZipArchive();
    $z->open($zip);
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = $z->getNameIndex($i);
        if (str_starts_with($name, 'patches/') && str_ends_with($name, '.diff')) {
            $patches[basename($name)] = $z->getFromIndex($i);
        }
    }
    $z->close();
    if (!$patches) return ['ok' => false, 'error' => 'tak ada patch di zip'];
    ksort($patches);
    // Idempoten: lewati bila versi ini sudah terpasang.
    $sentinel = rtrim($app, '/') . '/' . MIXCODE_SENTINEL;
    if (is_file($sentinel) && trim((string) @file_get_contents($sentinel)) === MIXCODE_VER) {
        $log[] = 'sudah terpasang (v' . MIXCODE_VER . '), lewati';
        return ['ok' => true, 'log' => $log];
    }
    foreach ($patches as $name => $text) {
        $files = mix_apply_patch($app, $text);
        $log[] = "patch $name OK (" . implode(', ', $files) . ')';
    }
    @file_put_contents($sentinel, MIXCODE_VER, LOCK_EX);
    return ['ok' => true, 'log' => $log];
}

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv) && realpath($argv[0]) === __FILE__) {
    $opt = getopt('', ['app-dir:', 'zip:', 'check', 'patches-dir:']);
    $app = $opt['app-dir'] ?? null;
    $zip = $opt['zip'] ?? null;
    if (!$app || !$zip) mix_fail("pakai: php apply-mixcode.php --app-dir=DIR --zip=mixcode.zip [--check] [--patches-dir=DIR]");
    if (!is_dir($app)) mix_fail("app-dir bukan folder: $app");
    if (!is_file($zip)) mix_fail("zip tak ada: $zip");
    if (!empty($opt['check'])) {
        echo "check OK: app-dir dan zip ada\n";
        exit(0);
    }
    // 1. overlay
    mix_overlay($app, $zip);
    echo "overlay OK\n";
    // 2. patches: dari zip, atau folder luar (untuk dev: --patches-dir).
    if (!empty($opt['patches-dir'])) {
        $saved = [];
        foreach (glob(rtrim($opt['patches-dir'], '/') . '/*.diff') as $f) $saved[basename($f)] = file_get_contents($f);
        ksort($saved);
        foreach ($saved as $name => $text) {
            $files = mix_apply_patch($app, $text);
            echo "patch $name OK (" . implode(', ', $files) . ")\n";
        }
        @file_put_contents(rtrim($app, '/') . '/' . MIXCODE_SENTINEL, MIXCODE_VER, LOCK_EX);
        echo "MIXCODE OK\n";
        exit(0);
    }
    $r = mixcode_install($app, $zip);
    foreach ($r['log'] ?? [] as $l) echo "$l\n";
    if (!$r['ok']) mix_fail($r['error']);
    echo "MIXCODE OK\n";
}
