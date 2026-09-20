<?php
/**
 * cetak-label-mix-gc121.php — Label Mixcode untuk Kertas Golden Cock 121.
 *
 * Mengikuti pola bawaan cetak-label-gc121-1.php: A4, grid 2 kolom x 4 baris
 * (label 101 x 68 mm, margin atas 19mm, kiri 1mm, gap kolom 3.5mm,
 * gap baris 10mm). Layout mix: header warna + barcode vertikal saja
 * (kiri/kanan/keduanya mengikuti Posisi Barcode di Pengaturan) +
 * no. panggil & judul.
 *
 * Judul/warna/header mengikuti Pengaturan > Label Mixcode.
 */
require_once __DIR__ . '/_mixcode_functions.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetCellPadding(0);

// ── Konfigurasi grid (sama seperti bawaan GC121-1) ──
$cols       = 2;
$rows       = 4;
$labelW     = 101.0;
$labelH     = 68.0;
$marginLeft = 2.25; // (210 - (2x101 + 3.5)) / 2: grid disenterkan, sisa simetris
$marginTop  = 19.0;
$gapCol     = 3.5;
$gapRow     = 10.0;

$position = 'right';
if (isset($mixSettings['position'])) {
    $position = (string) $mixSettings['position'];
}
if (!in_array($position, ['left', 'right', 'both'], true)) {
    $position = 'right';
}

$col       = 0;
$row       = 0;
$total     = count($LabelData);
$idx       = 0;

$pdf->AddPage();

foreach ($LabelData as $label) {
    $idx++;

    $x = $marginLeft + $col * ($labelW + $gapCol);
    $y = $marginTop  + $row * ($labelH + $gapRow);

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
            . ($barcodeImg !== '' ? '<img src="' . $barcodeImg . '" style="width:13mm; height:40mm;">' : $barcodeTxt)
            . '</td>';
    };
    $textCell = '<td style="text-align: center; vertical-align: middle;'
        . ' border-left:solid 1px #CCC; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC; padding:1mm;">'
        . '<span style="font-size:9pt; font-weight:bold;">' . $callLines . '</span>'
        . '<br><span style="font-size:8pt;">' . $titleSlice . '</span>'
        . '</td>';

    if ($position === 'left') {
        $bodyRow = $barcodeCell('26') . $textCell;
        $colspan = 2;
    } elseif ($position === 'both') {
        $bodyRow = $barcodeCell('20') . $textCell . $barcodeCell('20');
        $colspan = 3;
    } else {
        $bodyRow = $textCell . $barcodeCell('26');
        $colspan = 2;
    }

    $html = '
    <table cellpadding="1" cellspacing="0" style="width:' . $labelW . 'mm;">
        <tr>
            <td colspan="' . $colspan . '" style="border:solid 1px #CCC; height:13mm; text-align: center; vertical-align: middle;
                       font-size:9pt; font-weight:bold; background-color: ' . htmlspecialchars($warna) . ';">'
                . $library .
            '</td>
        </tr>
        <tr style="height:51mm;">' . $bodyRow . '</tr>
    </table>';

    $pdf->SetXY($x, $y);
    if (isset($outputFormat) && $outputFormat == 'word') {
        echo $html . '<br>';
        continue;
    }
    $pdf->writeHTML($html, false, false, false, false, '');

    // ── Navigasi grid ──
    $col++;
    if ($col >= $cols) {
        $col = 0;
        $row++;
    }
    if ($row >= $rows) {
        $row = 0;
        $col = 0;
        if ($idx < $total) {
            $pdf->AddPage();
        }
    }
}

if (isset($outputFormat) && $outputFormat == 'word') {
    return;
}

$pdf->Output('label-mixcode-gc121.pdf', 'D');
die;
