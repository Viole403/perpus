<?php
/**
 * cetak-label-a4-mix-left.php — Label Mixcode Warna, barcode di KIRI.
 *
 * Port layout "leftCode_template.php" dari plugin SLiMS
 * label_mixcode_color_slims ke Inlislite (TCPDF/writeHTML + ekspor Word).
 *
 * Tiap label: header nama perpustakaan (latar warna kelas),
 * kiri barcode vertikal saja, kanan dibagi dua: no. panggil
 * (atas) + judul penuh (bawah).
 *
 * Kunci $LabelData yang dipakai (sudah disediakan controller):
 * Title, Barcode, CallNumber, NamaPerpustakaan, Warna1, BarcodePNG.
 * Barcode vertikal dibuat langsung via get_barcode_png_vertical()
 * sehingga TIDAK perlu ubahan controller selain whitelist template.
 */
require_once __DIR__ . '/_mixcode_functions.php';

// ── Pengaturan ala SLiMS lbc_settings (silakan ubah di sini) ──
$mixPerRow   = 2; // chunk: label per baris (kertas A4)
$mixPerPage  = 8; // label per halaman (setara pageBreakAt)
// Kertas non-A4 (Roll/Stiker/dll): 1 label per baris, mengalir kontinyu.
$mixPaper = isset($paperSize) ? (string) $paperSize : 'a4';
if ($mixPaper !== 'a4') {
    $mixPerRow  = 1;
    $mixPerPage = 20;
    $mixPagePad = '10px';
} else {
    $mixPagePad = '58px';
}
$mixColW = ($mixPerRow === 1) ? '100%' : '50%';
// Diambil dari menu Pengaturan > Label Mixcode (variabel $mixSettings
// diisi controller; fallback di bawah = perilaku bawaan bila dibuka
// tanpa controller, mis. pada uji render mandiri).
$mixTitleLen = isset($mixSettings['titleLen']) ? (int) $mixSettings['titleLen'] : 0;
$mixTitleFont = isset($mixSettings['titleFont']) ? (int) $mixSettings['titleFont'] : 8;
$mixBarcodeHeight = isset($mixSettings['barcodeHeight']) ? (int) $mixSettings['barcodeHeight'] : 110;

// Create new TCPDF instance
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false); // label tak perlu nomor halaman + link mikro TCPDF
$pdf->AddPage();
$pdf->setCellPaddings($left = '2', $top = '', $right = '', $bottom = '4');

$html = '';
$no = 0;
$item = 0;
$rec = 0;
$jumlahData = count($LabelData);

foreach ($LabelData as $label) {
    $rec++;

    if ($item == 0) {
        $html .= '<div style="padding:' . $mixPagePad . ';">';
        $html .= '<table style="1px solid transparent;">';
    }

    if ($no == 0) {
        $html .= '<tr>';
    }

    $warna      = !empty($label['Warna1']) ? $label['Warna1'] : '#FFFF66';
    $titleSlice = mixcode_title_slice(isset($label['Title']) ? $label['Title'] : '', $mixTitleLen);
    $callLines  = mixcode_call_lines(isset($label['CallNumber']) ? $label['CallNumber'] : '');
    $barcodeImg = mixcode_vertical_barcode($label);
    $barcodeTxt = htmlspecialchars(isset($label['Barcode']) ? (string) $label['Barcode'] : '');

    $html .= '<td style="width:' . $mixColW . ';">'
        . '<table cellpadding="0" cellspacing="0" style="width:255px;" nobr="true">'
        . '<tr style="vertical-align:center">'
        . '<td style="border:solid 1px #CCC; height:47px; width:255px; text-align: center; vertical-align: middle; background-color: ' . htmlspecialchars($warna) . ';" colspan="2">'
        . htmlspecialchars(isset($label['NamaPerpustakaan']) ? (string) $label['NamaPerpustakaan'] : '')
        . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td rowspan="2" style="width:35%; text-align: center; vertical-align: middle; padding: 4px 3px; border-left:solid 1px #CCC; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC;">'
        . ($barcodeImg !== '' ? '<img src="' . $barcodeImg . '" width="32" height="' . $mixBarcodeHeight . '">' : $barcodeTxt)
        . '</td>'
        . '<td style="width:65%; border-bottom:solid 1px #CCC; border-right:solid 1px #CCC; text-align: center; vertical-align: middle; padding: 4px 3px;">'
        . '<span style="font-size: 11px; font-weight: bold;">' . $callLines . '</span>'
        . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="border-bottom:solid 1px #CCC; border-right:solid 1px #CCC; border-top:solid 1px #CCC; text-align: center; vertical-align: middle; padding: 4px 3px;">'
        . '<span style="font-size: ' . $mixTitleFont . 'px;">' . $titleSlice . '</span>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</td>';

    if ($no == ($mixPerRow - 1)) {
        $html .= '</tr>';
        $no = 0;
    } else {
        $no++;
    }

    if ($item == ($mixPerPage - 1) || $rec == $jumlahData) {
        if ($no > 0) {
            $html .= '</tr>';
            $no = 0;
        }
        $html .= '</table>';
        $html .= '</div>';
        $item = 0;
    } else {
        $item++;
    }
}

if (isset($outputFormat) && $outputFormat == 'word') {
    echo $html;
    return;
}
$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Output('label-mixcode-kiri.pdf', 'D');
die;
