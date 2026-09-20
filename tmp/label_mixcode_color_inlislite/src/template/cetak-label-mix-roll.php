<?php
/**
 * cetak-label-mix-roll.php — Label Mixcode untuk Kertas Label Roll.
 *
 * Mengikuti pola bawaan cetak-label-lr1.php: halaman custom 57 x 40 mm
 * landscape, 1 label per halaman (1 stiker = 1 halaman).
 * Layout mix: header warna + barcode vertikal saja (kiri/kanan/keduanya
 * mengikuti Posisi Barcode di Pengaturan) + no. panggil & judul.
 *
 * Judul/warna/header mengikuti Pengaturan > Label Mixcode (judul penuh
 * atau potong, header otomatis/kustom). Ukuran mm fixed mengikuti fisik
 * stiker roll (tipografi tidak memakai setting px).
 */
require_once __DIR__ . '/_mixcode_functions.php';

$pdf = new TCPDF('L', 'mm', [57, 40], true, 'UTF-8', false);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCellPadding(1);

$position = 'right';
if (isset($mixSettings['position'])) {
    $position = (string) $mixSettings['position'];
}
if (!in_array($position, ['left', 'right', 'both'], true)) {
    $position = 'right';
}

foreach ($LabelData as $label) {
    $pdf->AddPage();

    $warna      = !empty($label['Warna1']) ? $label['Warna1'] : '#FFFF66';
    $titleLen   = isset($mixSettings['titleLen']) ? (int) $mixSettings['titleLen'] : 0;
    $titleSlice = mixcode_title_slice(isset($label['Title']) ? $label['Title'] : '', $titleLen);
    $callLines  = mixcode_call_lines(isset($label['CallNumber']) ? $label['CallNumber'] : '');
    $barcodeImg = mixcode_vertical_barcode($label);
    $barcodeTxt = htmlspecialchars(isset($label['Barcode']) ? (string) $label['Barcode'] : '');
    $library    = htmlspecialchars(isset($label['NamaPerpustakaan']) ? (string) $label['NamaPerpustakaan'] : '');

    $barcodeCell = function ($width) use ($barcodeImg, $barcodeTxt) {
        return '<td style="width:' . $width . 'mm; text-align: center; vertical-align: middle;'
            . ' border-left:solid 1px #CCC; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC;">'
            . ($barcodeImg !== '' ? '<img src="' . $barcodeImg . '" style="width:13mm; height:25mm;">' : $barcodeTxt)
            . '</td>';
    };
    $textCell = '<td style="text-align: center; vertical-align: middle;'
        . ' border-left:solid 1px #CCC; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC; padding:1mm;">'
        . '<span style="font-size:7pt; font-weight:bold;">' . $callLines . '</span>'
        . '<br><span style="font-size:6pt;">' . $titleSlice . '</span>'
        . '</td>';

    if ($position === 'left') {
        $bodyRow = $barcodeCell('16') . $textCell;
        $colspan = 2;
    } elseif ($position === 'both') {
        $bodyRow = $barcodeCell('12') . $textCell . $barcodeCell('12');
        $colspan = 3;
    } else {
        $bodyRow = $textCell . $barcodeCell('16');
        $colspan = 2;
    }

    $html = '
    <table cellpadding="1" cellspacing="0" style="width:57mm;">
        <tr>
            <td colspan="' . $colspan . '" style="border:solid 1px #CCC; height:9mm; text-align: center; vertical-align: middle; font-size:7pt; font-weight:bold; background-color: ' . htmlspecialchars($warna) . ';">'
                . $library .
            '</td>
        </tr>
        <tr style="height:30mm;">' . $bodyRow . '</tr>
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

$pdf->Output('label-mixcode-roll.pdf', 'D');
die;
