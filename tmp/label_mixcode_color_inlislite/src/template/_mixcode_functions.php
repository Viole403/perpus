<?php
/**
 * _mixcode_functions.php
 *
 * Partial bersama untuk template Label Mixcode Warna Inlislite.
 * Port dari helpers.php plugin SLiMS label_mixcode_color_slims
 * (https://github.com/drajathasan/label_mixcode_color_slims).
 *
 * - Warna latar diambil dari $label['Warna1'] yang sudah diisi
 *   EksemplarLabelController dari tabel master_kelas_besar.
 *   (Di SLiMS aslinya dari setting lbc_color 0XX..9XX.)
 * - Barcode vertikal memakai helper bawaan Inlislite
 *   get_barcode_png_vertical() (thumbnail_helper.php) sehingga
 *   aman untuk TCPDF/writeHTML dan ekspor Word — tidak butuh
 *   JsBarcode client-side seperti plugin aslinya.
 */

if (!function_exists('mixcode_call_lines')) {
    /**
     * Pecah nomor panggil per baris, setia pada sliceCallNumber() SLiMS:
     * pisah pada spasi yang diapit huruf/angka (mis. "7965.555 919 Har n"
     * menjadi 7965.555 919 / Har / n).
     *
     * @param string $callNumber
     * @return string HTML siap cetak (sudah di-escape)
     */
    function mixcode_call_lines($callNumber)
    {
        if (trim((string) $callNumber) === '') {
            return '<span style="color:#ff0000;">-</span>';
        }

        $split = preg_split('/(?<=\w)\s+(?=[A-Za-z])/m', trim((string) $callNumber));
        if (!is_array($split) || empty($split)) {
            return htmlspecialchars((string) $callNumber);
        }

        // Baris pertama yang diawali angka (mis. "7965.555 919") dipecah
        // lagi per spasi/garis miring agar muat di label sempit,
        // mengikuti konvensi template bawaan Inlislite.
        $lines = [];
        foreach ($split as $i => $part) {
            if ($i === 0 && preg_match('/^[0-9]/', ltrim($part))) {
                foreach (preg_split('/[\s\/]+/', trim($part)) as $sub) {
                    if ($sub !== '') {
                        $lines[] = $sub;
                    }
                }
            } else {
                $lines[] = $part;
            }
        }

        return implode('<br>', array_map('htmlspecialchars', $lines));
    }
}

if (!function_exists('mixcode_vertical_barcode')) {
    /**
     * Ambil URI PNG barcode vertikal untuk satu baris $label.
     * Prioritas: helper bawaan Inlislite > BarcodePNGVertical dari
     * controller > BarcodePNG biasa (horizontal, fallback terakhir).
     *
     * @param array $label Satu entri $LabelData
     * @return string data:image/png;base64,... (atau string kosong)
     */
    function mixcode_vertical_barcode($label)
    {
        $code = isset($label['Barcode']) ? (string) $label['Barcode'] : '';
        if ($code === '') {
            return '';
        }

        if (function_exists('get_barcode_png_vertical')) {
            return get_barcode_png_vertical($code);
        }

        if (!empty($label['BarcodePNGVertical'])) {
            return $label['BarcodePNGVertical'];
        }

        return isset($label['BarcodePNG']) ? (string) $label['BarcodePNG'] : '';
    }
}

if (!function_exists('mixcode_title_slice')) {
    /**
     * Judul untuk sel barcode. $len <= 0 berarti tampil penuh
     * (tanpa potong + tanpa " ..."). SLiMS aslinya memakai 5.
     *
     * @param string $title
     * @param int    $len
     * @return string HTML siap cetak (sudah di-escape)
     */
    function mixcode_title_slice($title, $len = 0)
    {
        $title = (string) $title;
        if ($len <= 0) {
            return htmlspecialchars($title);
        }
        $slice = substr($title, 0, $len);
        return htmlspecialchars($slice) . ' ...';
    }
}
