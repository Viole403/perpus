<?php

namespace Eksemplar\Controllers;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * EksemplarLabelController
 *
 * Menangani cetak label eksemplar (PDF dan Word DOCX).
 */
class EksemplarLabelController extends \Base\Controllers\BaseController
{
    use EksemplarBase;

    function __construct()
    {
        $this->initEksemplarBase();
    }

    public function print_label()
    {
        helper(['thumbnail', 'form']);

        $this->data['title'] = 'Cetak Label Eksemplar';

        $this->validation->setRules([
            'eksemplar_ids' => ['label' => 'Eksemplar',      'rules' => 'required'],
            'eksemplar_tpl' => ['label' => 'Template Label', 'rules' => 'required'],
        ]);

        if (!$this->request->getPost() || !$this->validation->withRequest($this->request)->run()) {
            $this->session->setFlashdata('swal_icon',  'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html',
                $this->validation->getErrors()
                    ? $this->validation->listErrors()
                    : 'Tidak ada eksemplar yang dipilih.'
            );
            return redirect()->back();
        }

        $post         = $this->request->getPost();
        $template     = $post['eksemplar_tpl'];
        $paperSize    = $post['paper_size'] ?? 'a4';
        $outputFormat = $post['output_format'] ?? 'pdf';
        $idsArr       = array_filter(
            array_map('intval', explode(',', preg_replace('/[^0-9,]/', '', $post['eksemplar_ids']))),
            fn($id) => $id > 0
        );

        if (empty($idsArr)) {
            $this->session->setFlashdata('swal_icon',  'warning');
            $this->session->setFlashdata('swal_title', 'Peringatan');
            $this->session->setFlashdata('swal_html',  'Tidak ada ID eksemplar yang valid.');
            return redirect()->back();
        }

        // Model A4-1 s.d. A4-12 disembunyikan (file view tetap ada, tidak
        // lagi ditawarkan di dropdown maupun diizinkan di sini).
        $allowedTemplates = [
            'cetak-label-a4-4-qrcode',
            'cetak-label-lr1',  'cetak-label-lr2',  'cetak-label-lr3',
            'cetak-label-lr4',  'cetak-label-lr5',  'cetak-label-lr6',
            'cetak-label-br1',  'cetak-label-br2',
            'cetak-label-tj107-1',
            'cetak-label-tj121-1', 'cetak-label-tj121-2',
            'cetak-label-gc121-1', 'cetak-label-gc121-2',
            'cetak-label-gc121-3', 'cetak-label-gc121-4',
        ];

        if (!in_array($template, $allowedTemplates, true)) {
            $this->session->setFlashdata('swal_icon',  'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html',  'Template tidak dikenali: ' . esc($template));
            return redirect()->back();
        }

        $db = db_connect();

        $eksemplarData = $db->table('collections as a')
            ->select('a.ID, a.NomorBarcode, b.Title, b.Author, b.CallNumber, b.DeweyNo')
            ->join('catalogs b', 'b.ID = a.Catalog_id')
            ->whereIn('a.ID', $idsArr)
            ->get()
            ->getResultObject();

        // Warna label otomatis per-eksemplar dari rentang DDC Master Kelas Besar.
        // DDC dibaca sebagai INT (digit awal, cth. "813.5" -> 813); baris yang
        // rentangnya melingkupi dan tersempit menang (sub-range spt. 320-329
        // mengalahkan 300-399). Tanpa kecocokan -> fallback abu-abu #CCCCCC.
        $fallbackColor = '#CCCCCC';
        $ddcRanges = $db->table('master_kelas_besar')
            ->select('warna, RangeStart, RangeEnd')
            ->where('active', 1)
            ->where('RangeStart IS NOT NULL')
            ->where('RangeEnd IS NOT NULL')
            ->get()
            ->getResultArray();

        $resolveColor = function ($deweyNo) use ($ddcRanges, $fallbackColor) {
            if (!preg_match('/(\d+)/', (string) ($deweyNo ?? ''), $m)) {
                return $fallbackColor;
            }
            $ddcInt = (int) $m[1];
            $best = null;
            $bestWidth = PHP_INT_MAX;
            foreach ($ddcRanges as $range) {
                $start = (int) $range['RangeStart'];
                $end = (int) $range['RangeEnd'];
                if ($ddcInt < $start || $ddcInt > $end) {
                    continue;
                }
                if (($end - $start) < $bestWidth) {
                    $bestWidth = $end - $start;
                    $best = $range['warna'] ?: $fallbackColor;
                }
            }
            return $best ?? $fallbackColor;
        };

        if (empty($eksemplarData)) {
            $this->session->setFlashdata('swal_icon',  'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html',  'Data eksemplar tidak ditemukan.');
            return redirect()->back();
        }

        $namaPerpustakaan = $db->table('settingparameters')
            ->where('Name', 'NamaPerpustakaan')
            ->get()
            ->getRow()
            ->Value ?? 'Perpustakaan Mitra';

        $namaCabang = $namaPerpustakaan;

        $useQrCode = str_contains($template, 'qrcode') || str_contains($paperSize, 'qrcode');

        $LabelData = [];
        foreach ($eksemplarData as $row) {
            // No. Panggil cetak = DDC (INT) + Nama Pengarang utama,
            // cth. "813 - Tere Liye". DB tidak diubah.
            $callNumberPrint = $row->CallNumber;
            if (preg_match('/(\d+)/', (string) ($row->DeweyNo ?? ''), $m)) {
                $author = trim((string) ($row->Author ?? ''));
                $callNumberPrint = ((int) $m[1]) . ($author !== '' ? ' - ' . $author : '');
            }

            $LabelData[] = [
                'Title'              => character_limiter($row->Title, 50),
                'Barcode'            => $row->NomorBarcode,
                'CallNumber'         => $callNumberPrint,
                'NamaPerpustakaan'   => $namaPerpustakaan,
                'NamaCabang'         => $namaCabang,
                'Warna1'             => $resolveColor($row->DeweyNo ?? ''),
                'BarcodePNG'         => $useQrCode
                                        ? get_qrcode_png($row->NomorBarcode)
                                        : get_barcode_png($row->NomorBarcode),
                'BarcodePNGVertical' => null,
            ];
        }

        if ($outputFormat === 'word') {
            $html = view('Eksemplar\Views\template\\' . $template, [
                'LabelData' => $LabelData,
                'outputFormat' => $outputFormat
            ]);
            return $this->_generateWordDocHtml($html);
        }

        return view('Eksemplar\Views\template\\' . $template, ['LabelData' => $LabelData]);
    }

    private function _generateWordDocHtml(string $html)
    {
        // Generate MHTML so images (base64) are fully embedded and layout is preserved
        $boundary = "----=_NextPart_000_0000_01D1B1B1.1B1B1B1B";
        $images = [];
        
        $mhtmlContent = preg_replace_callback('/<img[^>]+src="([^"]+)"[^>]*>/i', function($matches) use (&$images) {
            $src = $matches[1];
            if (strpos($src, 'data:image') === 0) {
                $parts = explode(',', $src);
                if (count($parts) > 1) {
                    $mime = explode(';', explode(':', $parts[0])[1])[0];
                    $base64 = $parts[1];
                    $cid = "img_" . md5($base64) . '.' . explode('/', $mime)[1];
                    $images[$cid] = ['mime' => $mime, 'data' => $base64];
                    return str_replace($src, $cid, $matches[0]);
                }
            }
            return $matches[0];
        }, $html);

        $mhtml = "MIME-Version: 1.0\r\n";
        $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"\r\n\r\n";
        
        $mhtml .= "--$boundary\r\n";
        $mhtml .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
        $mhtml .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        
        // Clean up transparent borders which show as gridlines in MS Word
        $mhtmlContent = str_replace('1px solid transparent', 'none', $mhtmlContent);
        // Ensure page breaks are respected for label roll formats (which use <br> between labels)
        $mhtmlContent = str_replace('</table><br>', '</table><br clear="all" style="page-break-before:always" />', $mhtmlContent);

        // Ensure complete HTML structure for MS Word, with necessary CSS to collapse borders
        $fullHtml = "<html><head><meta charset=\"utf-8\"><title>Export Word</title>
<style>
    table { border-collapse: collapse; }
    td { mso-cellspacing: 0px; mso-padding-alt: 0px 0px 0px 0px; }
</style>
</head><body>\r\n" . $mhtmlContent . "\r\n</body></html>";
        $mhtml .= quoted_printable_encode($fullHtml) . "\r\n\r\n";
        
        foreach ($images as $cid => $img) {
            $mhtml .= "--$boundary\r\n";
            $mhtml .= "Content-Type: " . $img['mime'] . "\r\n";
            $mhtml .= "Content-Transfer-Encoding: base64\r\n";
            $mhtml .= "Content-Location: $cid\r\n\r\n";
            $mhtml .= chunk_split($img['data'], 76, "\r\n") . "\r\n";
        }
        $mhtml .= "--$boundary--";

        $response = service('response');
        $response->setHeader('Content-Type', 'application/vnd.ms-word');
        $response->setHeader('Content-Disposition', 'attachment; filename="label-eksemplar.doc"');
        $response->setHeader('Cache-Control', 'max-age=0');
        $response->setBody($mhtml);

        return $response;
    }
}
