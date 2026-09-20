<?php
/**
 * test_render.php — uji render template Mixcode tanpa web server / DB.
 *
 * Cara pakai:
 *   php tests/test_render.php
 *
 * Meng-include ketiga template dalam mode Word ($outputFormat='word'
 * sehingga hanya HTML yang di-echo tanpa TCPDF/keluar PDF), memakai
 * stub TCPDF + stub get_barcode_png_vertical(), lalu memeriksa:
 *  - nama perpustakaan muncul
 *  - warna kelas (Warna1) dipakai sebagai background header
 *  - potongan judul 5 karakter muncul
 *  - tiap nomor panggil terpecah per baris
 *  - jumlah <img> barcode sesuai posisi (kiri/kanan=1 per label,
 *    both=2 per label)
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// ── Stub konstanta + class TCPDF (dipakai di awal tiap template) ──
if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
if (!defined('PDF_UNIT')) define('PDF_UNIT', 'mm');
if (!defined('PDF_PAGE_FORMAT')) define('PDF_PAGE_FORMAT', 'A4');

class TCPDF
{
    public function __construct(...$args) {}
    public function __call($m, $a) {}
    public function SetPrintHeader($v) {}
    public function AddPage() {}
    public function setCellPaddings($l = '', $t = '', $r = '', $b = '') {}
    public function writeHTML($h, ...$a) {}
    public function Output($f, $d) {}
}

// ── Stub barcode vertikal (helper asli butuh GD + Picqer) ──
function get_barcode_png_vertical($code, $angle = 90)
{
    return 'data:image/png;base64,' . base64_encode('barcode:' . $code);
}

// ── Data dummy menyerupai $LabelData dari controller ──
$LabelData = [
    [
        'Title'            => 'Harry Potter dan Batu Bertuah',
        'Barcode'          => 'B00017',
        'CallNumber'       => '813 Har h',
        'NamaPerpustakaan' => 'Perpustakaan Mitra',
        'Warna1'           => '#546E7A',
        'BarcodePNG'       => 'data:image/png;base64,xxx',
    ],
    [
        'Title'            => 'Filsafat Ilmu Pengetahuan',
        'Barcode'          => 'B00018',
        'CallNumber'       => '101 Sur f',
        'NamaPerpustakaan' => 'Perpustakaan Mitra',
        'Warna1'           => '#F9E103',
        'BarcodePNG'       => 'data:image/png;base64,yyy',
    ],
    [
        'Title'            => 'Sejarah Agama Islam',
        'Barcode'          => 'B00019',
        'CallNumber'       => '2X1 Kar s',
        'NamaPerpustakaan' => 'Perpustakaan Mitra',
        'Warna1'           => '#1CA369',
        'BarcodePNG'       => 'data:image/png;base64,zzz',
    ],
];
$outputFormat = 'word';

$base = dirname(__DIR__) . '/src/template';
$cases = [
    'cetak-label-a4-mix-left.php'  => 3, // 1 img x 3 label
    'cetak-label-a4-mix-right.php' => 3, // 1 img x 3 label
    'cetak-label-a4-mix-both.php'  => 6, // 2 img x 3 label
];

$fail = 0;
foreach ($cases as $file => $expectedImg) {
    ob_start();
    include $base . '/' . $file;
    $html = ob_get_clean();

    $checks = [
        'nama perpustakaan' => strpos($html, 'Perpustakaan Mitra') !== false,
        'warna kelas 813 (#546E7A)' => strpos($html, '#546E7A') !== false,
        'warna kelas 101 (#F9E103)' => strpos($html, '#F9E103') !== false,
        'warna kelas 2X1 (#1CA369)' => strpos($html, '#1CA369') !== false,
        'judul penuh tampil' => strpos($html, 'Harry Potter dan Batu Bertuah') !== false,
        'call number terpecah (813<br>)' => strpos($html, '813<br>') !== false,
        'jumlah <img> barcode = ' . $expectedImg => substr_count($html, '<img') === $expectedImg,
    ];

    echo "== $file ==\n";
    foreach ($checks as $name => $ok) {
        echo ($ok ? "  [OK] " : "  [GAGAL] ") . $name . "\n";
        if (!$ok) {
            $fail++;
        }
    }
}

// ── Unit kecil untuk mixcode_call_lines ──
require $base . '/_mixcode_functions.php';
$unit = [
    ['813 Har h', '813<br>Har<br>h'],
    ['7965.555 919 Har n', '7965.555<br>919<br>Har<br>n'],
    ['', '<span style="color:#ff0000;">-</span>'],
];
echo "== mixcode_call_lines ==\n";
foreach ($unit as [$in, $expected]) {
    $got = mixcode_call_lines($in);
    $ok = $got === $expected;
    echo ($ok ? "  [OK] " : "  [GAGAL] ") . "'$in' => '$got'" . ($ok ? '' : " (mau: '$expected')") . "\n";
    if (!$ok) {
        $fail++;
    }
}

echo $fail === 0 ? "\nSEMUA UJI LOLOS\n" : "\n$fail UJI GAGAL\n";
exit($fail === 0 ? 0 : 1);
