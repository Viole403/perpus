<?php

namespace LabelMixcode\Controllers;

/**
 * LabelMixcode — halaman pengaturan Label Mixcode Warna.
 *
 * Menyimpan opsi tampilan di settingparameters (prefix Mixcode)
 * dan warna 10 kelas DDC di master_kelas_besar (kolom warna).
 */
class LabelMixcode extends \Base\Controllers\BaseController
{
    /** Nilai bawaan bila setting belum pernah disimpan. */
    private $defaults = [
        'MixcodePosition'      => 'right',
        'MixcodeTitleMode'     => 'full',
        'MixcodeTitleLen'      => '0',
        'MixcodeTitleFont'     => '8',
        'MixcodeBarcodeHeight' => '110',
        'MixcodeHeaderSource'  => 'library',
        'MixcodeHeaderText'    => '',
    ];

    /** Kunci template lama → posisi barcode, untuk migrasi otomatis. */
    private function positionFromTemplateKey($val)
    {
        $val = (string) $val;
        if (strpos($val, 'mix-left') !== false || $val === 'left') {
            return 'left';
        }
        if (strpos($val, 'mix-both') !== false || $val === 'both') {
            return 'both';
        }
        if (strpos($val, 'mix-right') !== false || $val === 'right') {
            return 'right';
        }
        return null;
    }

    /** Posisi barcode tersimpan (migrasi dari kunci template lama bila perlu). */
    private function storedPosition()
    {
        $db = db_connect();
        $row = $db->table('settingparameters')
            ->select('Value')
            ->where('Name', 'MixcodePosition')
            ->get()
            ->getRow();
        $val = trim((string) ($row->Value ?? ''));
        if (in_array($val, ['left', 'right', 'both'], true)) {
            return $val;
        }
        $old = $db->table('settingparameters')
            ->select('Value')
            ->where('Name', 'MixcodeTemplate')
            ->get()
            ->getRow();
        $mapped = $this->positionFromTemplateKey($old->Value ?? '');
        return $mapped ?? $this->defaults['MixcodePosition'];
    }

    /** File template mix untuk tiap posisi barcode. */
    private function fileForPosition($position)
    {
        $map = [
            'left'  => 'cetak-label-a4-mix-left',
            'right' => 'cetak-label-a4-mix-right',
            'both'  => 'cetak-label-a4-mix-both',
        ];
        return $map[$position] ?? $map['right'];
    }

    /** Label klasifikasi untuk dropdown Model di halaman eksemplar. */
    private function classificationLabels()
    {
        $db = db_connect();
        $rows = $db->table('master_kelas_besar')
            ->select('KdKelas, namakelas, Warna')
            ->where('active', 1)
            ->orderBy('KdKelas', 'ASC')
            ->get()
            ->getResultArray();
        $labels = [];
        foreach ($rows as $row) {
            $kode = (string) $row['KdKelas'];
            if (preg_match('/^(\d)00$/', $kode, $m)) {
                $range = $m[1] . '00 – ' . $m[1] . '99.999';
            } else {
                $range = $kode;
            }
            // Nama DB ("000 - Karya Umum") → subjek pendek ("Karya Umum").
            $subject = preg_replace('/^\d+\s*-\s*/', '', (string) ($row['namakelas'] ?? ''));
            $labels[] = [
                'kode'  => $kode,
                'label' => trim($range . ' ' . $subject),
                'color' => (string) ($row['Warna'] ?? '#ffffff'),
            ];
        }
        return $labels;
    }

    /** JSON untuk halaman eksemplar: posisi + daftar nama label. */
    public function defaultTemplate()
    {
        $position = $this->storedPosition();
        return $this->response->setJSON([
            'position'        => $position,
            'template'        => $this->fileForPosition($position),
            'classifications' => $this->classificationLabels(),
        ]);
    }

    public function index()
    {
        $db = db_connect();

        // Warna seluruh kelas di Master Kelas Besar (dinamis: ikut isi tabel,
        // bukan hardcoded 10 — tambah/ubah/hapus langsung dari form ini).
        $kelas = $db->table('master_kelas_besar')
            ->select('KdKelas, namakelas, Warna')
            ->where('active', 1)
            ->orderBy('KdKelas', 'ASC')
            ->get()
            ->getResultArray();
        foreach ($kelas as &$row) {
            if (empty($row['Warna'])) {
                $row['Warna'] = '#ffffff';
            }
            // Rentang ala daftar Stevan: 000 → "000 – 099.999".
            if (preg_match('/^(\d)00$/', (string) $row['KdKelas'], $m)) {
                $row['range'] = $m[1] . '00 – ' . $m[1] . '99.999';
            } else {
                $row['range'] = (string) $row['KdKelas'];
            }
        }
        unset($row);

        // Opsi tampilan dari settingparameters.
        $optRows = $db->table('settingparameters')
            ->select('Name, Value')
            ->whereIn('Name', array_keys($this->defaults))
            ->get()
            ->getResultArray();
        $options = $this->defaults;
        foreach ($optRows as $optRow) {
            $options[$optRow['Name']] = (string) ($optRow['Value'] ?? '');
            if ($options[$optRow['Name']] === '') {
                $options[$optRow['Name']] = $this->defaults[$optRow['Name']];
            }
        }

        $this->data['title']   = 'Pengaturan Label Mixcode';
        $this->data['kelas']   = $kelas;
        $this->data['options'] = $options;
        $this->data['options']['MixcodePosition'] = $this->storedPosition();

        return view('LabelMixcode\Views\index', $this->data);
    }

    public function save()
    {
        if (!$this->request->getPost()) {
            return redirect()->to(base_url('label-mixcode'));
        }

        $this->validation->setRules([
            'position'       => ['label' => 'Posisi Barcode',   'rules' => 'required|in_list[left,right,both]'],
            'title_mode'     => ['label' => 'Mode Judul',       'rules' => 'required|in_list[full,crop]'],
            'title_len'      => ['label' => 'Panjang Judul',    'rules' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[100]'],
            'title_font'     => ['label' => 'Ukuran Font Judul','rules' => 'required|integer|greater_than_equal_to[6]|less_than_equal_to[20]'],
            'barcode_height' => ['label' => 'Tinggi Barcode',   'rules' => 'required|integer|greater_than_equal_to[40]|less_than_equal_to[150]'],
            'header_source'  => ['label' => 'Sumber Header',    'rules' => 'required|in_list[library,custom]'],
            'header_text'    => ['label' => 'Teks Header',      'rules' => 'permit_empty|max_length[100]'],
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            $this->session->setFlashdata('swal_icon', 'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html', $this->validation->listErrors());
            return redirect()->to(base_url('label-mixcode'))->withInput();
        }

        $post = $this->request->getPost();
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $by = login_id();
        $terminal = $this->request->getIPAddress();

        // 1. Klasifikasi warna → master_kelas_besar.
        // Respon JSON bila dipanggil AJAX (simpan tanpa reload halaman).
        $isAjax = $this->request->isAJAX();
        $fail = function ($msg) use ($isAjax) {
            if ($isAjax) {
                return $this->response->setJSON(['status' => 'error', 'message' => trim(strip_tags($msg))])->setStatusCode(422);
            }
            $this->session->setFlashdata('swal_icon', 'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html', $msg);
            return redirect()->to(base_url('label-mixcode'))->withInput();
        };
        $done = function ($msg) use ($isAjax) {
            if ($isAjax) {
                return $this->response->setJSON(['status' => 'success', 'message' => $msg]);
            }
            $this->session->setFlashdata('swal_icon', 'success');
            $this->session->setFlashdata('swal_title', 'Berhasil');
            $this->session->setFlashdata('swal_html', $msg);
            return redirect()->to(base_url('label-mixcode'));
        };
        // Status saat ini — hanya yang berubah yang ditulis agar simpan cepat.
        // Catatan: key array numerik selalu di-cast PHP menjadi integer,
        // jadi daftar validitas disimpan sebagai LIST string (value tidak
        // di-cast), bukan key array.
        $cur = [];
        $validKode = [];
        foreach ($db->table('master_kelas_besar')->select('KdKelas, namakelas, Warna')->get()->getResultArray() as $curRow) {
            $k = (string) $curRow['KdKelas'];
            $cur[$k] = $curRow;
            $validKode[] = $k;
        }
        $curOpt = $this->defaults;
        $existingOpt = [];
        foreach ($db->table('settingparameters')->select('Name, Value')->whereIn('Name', array_keys($this->defaults))->get()->getResultArray() as $optRow) {
            $curOpt[$optRow['Name']] = (string) ($optRow['Value'] ?? '');
            $existingOpt[$optRow['Name']] = true;
        }
        $changed = 0;

        // 1a. Hapus baris yang dicentang dulu.
        // Catatan: kunci array numerik (mis. '800') di-cast PHP menjadi
        // integer, jadi normalkan ke string sebelum perbandingan strict.
        $deleted = [];
        foreach ((array) ($post['del'] ?? []) as $kode => $on) {
            $kode = (string) $kode;
            if (!$on || !in_array($kode, $validKode, true)) {
                continue;
            }
            $db->table('master_kelas_besar')->where('KdKelas', $kode)->delete();
            $deleted[] = $kode;
            $changed++;
        }

        // 1b. Ubah nama + warna. Kumpulkan dulu per keluarga digit pertama
        // (baris terakhir menang, seperti semula), lalu tulis hanya yang
        // benar-benar berubah agar simpan cepat.
        $colors = $post['color'] ?? [];
        $names = $post['name'] ?? [];
        $famColor = [];
        $pendingNames = [];
        foreach ($colors as $kode => $hex) {
            $kode = (string) $kode;
            if (in_array($kode, $deleted, true) || !in_array($kode, $validKode, true)) {
                continue;
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $hex)) {
                return $fail('Warna kelas ' . esc($kode) . ' tidak valid (format #RRGGBB).');
            }
            $nama = trim((string) ($names[$kode] ?? ''));
            if ($nama === '' || strlen($nama) > 255) {
                return $fail('Nama subjek kelas ' . esc($kode) . ' wajib diisi (maks 255 karakter).');
            }
            if ($nama !== (string) ($cur[$kode]['namakelas'] ?? '')) {
                $pendingNames[$kode] = $nama;
            }
            $famColor[substr($kode, 0, 1)] = strtoupper($hex);
        }
        foreach ($pendingNames as $kode => $nama) {
            $db->table('master_kelas_besar')
                ->where('KdKelas', $kode)
                ->update([
                    'namakelas'      => $nama,
                    'UpdateBy'       => $by,
                    'UpdateDate'     => $now,
                    'UpdateTerminal' => $terminal,
                ]);
            $changed++;
        }
        foreach ($famColor as $digit => $hex) {
            $needsWrite = false;
            foreach ($cur as $curKode => $curRow) {
                if (in_array((string) $curKode, $deleted, true)) {
                    continue;
                }
                if (substr((string) $curKode, 0, 1) === (string) $digit
                    && strtoupper((string) ($curRow['Warna'] ?? '')) !== $hex) {
                    $needsWrite = true;
                    break;
                }
            }
            if ($needsWrite) {
                $db->table('master_kelas_besar')
                    ->where('LEFT(KdKelas, 1)', (string) $digit)
                    ->update([
                        'Warna'          => $hex,
                        'UpdateBy'       => $by,
                        'UpdateDate'     => $now,
                        'UpdateTerminal' => $terminal,
                    ]);
                $changed++;
            }
        }

        // 1c. Tambah kelas baru (opsional).
        $newCode = trim((string) ($post['new_code'] ?? ''));
        if ($newCode !== '') {
            $newName = trim((string) ($post['new_name'] ?? ''));
            $newColor = (string) ($post['new_color'] ?? '');
            if (!preg_match('/^[0-9]{1,3}$/', $newCode)) {
                return $fail('Kode kelas baru harus 1–3 digit angka (mis. 010).');
            }
            if (in_array($newCode, $validKode, true)) {
                return $fail('Kode kelas ' . esc($newCode) . ' sudah ada.');
            }
            if ($newName === '' || strlen($newName) > 255) {
                return $fail('Nama subjek kelas baru wajib diisi (maks 255 karakter).');
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $newColor)) {
                return $fail('Warna kelas baru tidak valid (format #RRGGBB).');
            }
            $db->table('master_kelas_besar')->insert([
                'KdKelas'        => $newCode,
                'namakelas'      => $newName,
                'Warna'          => strtoupper($newColor),
                'CreateBy'       => $by,
                'CreateDate'     => $now,
                'CreateTerminal' => $terminal,
                'active'         => 1,
            ]);
            $changed++;
        }

        // 2. Opsi tampilan → settingparameters (upsert per Name).
        $options = [
            'MixcodePosition'      => $post['position'],
            'MixcodeTitleMode'     => $post['title_mode'],
            'MixcodeTitleLen'      => (string) (int) $post['title_len'],
            'MixcodeTitleFont'     => (string) (int) $post['title_font'],
            'MixcodeBarcodeHeight' => (string) (int) $post['barcode_height'],
            'MixcodeHeaderSource'  => $post['header_source'],
            'MixcodeHeaderText'    => trim((string) ($post['header_text'] ?? '')),
        ];
        foreach ($options as $name => $value) {
            if (isset($existingOpt[$name]) && $curOpt[$name] === $value) {
                continue;
            }
            $exists = isset($existingOpt[$name]);
            $row = [
                'Value'          => $value,
                'UpdateBy'       => login_id(),
                'UpdateDate'     => date('Y-m-d H:i:s'),
                'UpdateTerminal' => $this->request->getIPAddress(),
            ];
            if ($exists) {
                $db->table('settingparameters')->where('Name', $name)->update($row);
            } else {
                $row['Name'] = $name;
                $db->table('settingparameters')->insert($row);
            }
            $changed++;
        }

        return $done($changed > 0 ? $changed . ' perubahan disimpan.' : 'Tidak ada perubahan.');
    }
}
