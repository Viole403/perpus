<?php
/**
 * cetak-label-mix-br.php — Label Mixcode untuk Kertas Barcode Roll.
 *
 * Mengikuti pola bawaan cetak-label-br1.php: halaman custom 57 x 25 mm
 * landscape, 1 label per halaman. Fisik stiker terlalu pendek untuk
 * barcode vertikal, sehingga dipakai barcode horizontal + no. panggil
 * dan judul ringkas di sampingnya.
 *
 * Judul/warna/header mengikuti Pengaturan > Label Mixcode.
 */
require_once __DIR__ . '/_mixcode_functions.php';

$pdf = new TCPDF('L', 'mm', [57, 25], true, 'UTF-8', false);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCellPadding(1);

foreach ($LabelData as $label) {
    $pdf->AddPage();

    $warna      = !empty($label['Warna1']) ? $label['Warna1'] : '#FFFF66';
    $titleLen   = isset($mixSettings['titleLen']) ? (int) $mixSettings['titleLen'] : 0;
    $titleSlice = mixcode_title_slice(isset($label['Title']) ? $label['Title'] : '', $titleLen);
    $callLines  = mixcode_call_lines(isset($label['CallNumber']) ? $label['CallNumber'] : '');
    $barcodeTxt = htmlspecialchars(isset($label['Barcode']) ? (string) $label['Barcode'] : '');
    $library    = htmlspecialchars(isset($label['NamaPerpustakaan']) ? (string) $label['NamaPerpustakaan'] : '');

    $barcodeSrc = '';
    if (!empty($label['Barcode']) && function_exists('get_barcode_png')) {
        $barcodeSrc = get_barcode_png($label['Barcode']);
    } elseif (!empty($label['BarcodePNG'])) {
        $barcodeSrc = $label['BarcodePNG'];
    }

    $html = '
    <table cellpadding="1" cellspacing="0" style="width:57mm;">
        <tr>
            <td colspan="2" style="border:solid 1px #CCC; height:5mm; text-align: center; vertical-align: middle; font-size:6pt; font-weight:bold; background-color: ' . htmlspecialchars($warna) . ';">'
                . $library .
            '</td>
        </tr>
        <tr>
            <td style="width:65%; border-left:solid 1px #CCC; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC;
                       text-align: center; vertical-align: middle; height:17mm; padding:1mm;">
                ' . ($barcodeSrc !== '' ? '<img src="' . $barcodeSrc . '" style="width:34mm; height:7mm;"><br>' : '') . '
                <span style="font-size:6pt;">*' . $barcodeTxt . '*<br>' . $titleSlice . '</span>
            </td>
            <td style="width:35%; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC;
                       text-align: center; vertical-align: middle; font-size:7pt; font-weight:bold;">' . $callLines . '
            </td>
        </tr>
    </table>';

    if (isset($outputFormat) && $outputFormat == 'word') {
        echo $html . '<br>';
        continue;
    }
    $pdf->writeHTML($html, true, false, false, false, '');
}

if (isset($outputFormat) && $outputFormat == 'word') {
    return;
}

$pdf->Output('label-mixcode-br.pdf', 'D');
die;
