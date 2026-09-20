<?php
/**
 * proof_render.php — render template Mixcode TERPASANG dengan pipeline asli:
 * vendor autoload (Picqer barcode), thumbnail_helper.php asli (GD vertical),
 * dan TCPDF asli. Hasil: PDF beneran + HTML mode Word.
 *
 * Cara pakai (harus php-legacy karena butuh gd/mysqli/intl):
 *   php-legacy tests/proof_render.php mix-left pdf
 *   php-legacy tests/proof_render.php mix-left word
 *   ... (mix-right, mix-both)
 *
 * Output: tests/proof/label-mix-{nama}.{pdf,html}
 */

error_reporting(E_ALL & ~E_DEPRECATED);

[$script, $which, $mode] = array_pad($argv, 3, '');
$map = [
    'mix-left'  => 'cetak-label-a4-mix-left.php',
    'mix-right' => 'cetak-label-a4-mix-right.php',
    'mix-both'  => 'cetak-label-a4-mix-both.php',
];
if (!isset($map[$which]) || !in_array($mode, ['pdf', 'word'], true)) {
    fwrite(STDERR, "Pakai: php-legacy tests/proof_render.php [mix-left|mix-right|mix-both] [pdf|word]\n");
    exit(2);
}

$appRoot = '/home/yuzusa/Projects/Perpus/insilite/www/inlislitev33';
require $appRoot . '/vendor/autoload.php';
if (!class_exists('TCPDF')) {
    require $appRoot . '/vendor/tecnickcom/tcpdf/tcpdf.php';
}
require $appRoot . '/app/Helpers/thumbnail_helper.php';

foreach (['PDF_PAGE_ORIENTATION' => 'P', 'PDF_UNIT' => 'mm', 'PDF_PAGE_FORMAT' => 'A4'] as $c => $v) {
    if (!defined($c)) {
        define($c, $v);
    }
}

// Data dummy menyerupai $LabelData asli dari EksemplarLabelController
$LabelData = [
    ['Title' => 'Harry Potter dan Batu Bertuah', 'Barcode' => 'B00017', 'CallNumber' => '813 Har h',        'NamaPerpustakaan' => 'Perpustakaan Mitra', 'Warna1' => '#546E7A', 'BarcodePNG' => get_barcode_png('B00017')],
    ['Title' => 'Filsafat Ilmu Pengetahuan',     'Barcode' => 'B00018', 'CallNumber' => '101 Sur f',        'NamaPerpustakaan' => 'Perpustakaan Mitra', 'Warna1' => '#F9E103', 'BarcodePNG' => get_barcode_png('B00018')],
    ['Title' => 'Sejarah Agama Islam',           'Barcode' => 'B00019', 'CallNumber' => '2X1 Kar s',        'NamaPerpustakaan' => 'Perpustakaan Mitra', 'Warna1' => '#1CA369', 'BarcodePNG' => get_barcode_png('B00019')],
    ['Title' => 'Karya Umum Referensi',          'Barcode' => 'B00020', 'CallNumber' => '7965.555 919 Har n','NamaPerpustakaan' => 'Perpustakaan Mitra', 'Warna1' => '#023e9c', 'BarcodePNG' => get_barcode_png('B00020')],
    ['Title' => 'Tanpa Nomor Panggil',           'Barcode' => 'B00021', 'CallNumber' => '',                 'NamaPerpustakaan' => 'Perpustakaan Mitra', 'Warna1' => '#FFFF66', 'BarcodePNG' => get_barcode_png('B00021')],
];
$outputFormat = $mode === 'word' ? 'word' : 'pdf';

$proofDir = __DIR__ . '/proof';
if (!is_dir($proofDir)) {
    mkdir($proofDir, 0777, true);
}

if ($mode === 'pdf') {
    // Mode PDF: TCPDF membersihkan output buffer lalu mencetak biner PDF
    // langsung ke STDOUT. Panggil dengan redirect shell, contoh:
    //   php-legacy tests/proof_render.php mix-left pdf > tests/proof/label-mix-mix-left.pdf
    include $appRoot . '/app/Modules/SubModule/Eksemplar/Views/template/' . $map[$which];
    exit; // tidak tercapai (template diakhiri die), pengaman saja
}

$outFile = $proofDir . '/label-mix-' . $which . '.html';
ob_start();
include $appRoot . '/app/Modules/SubModule/Eksemplar/Views/template/' . $map[$which];
// Mode PDF: template memanggil $pdf->Output(...,'D') + die, sehingga
// biner PDF langsung masuk buffer output.
$content = ob_get_clean();
file_put_contents($outFile, $content);
fwrite(STDERR, 'Ditulis: ' . $outFile . ' (' . strlen($content) . " byte)\n");
