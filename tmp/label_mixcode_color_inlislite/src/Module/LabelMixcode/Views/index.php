<?= $this->extend('App\Views\layout\main'); ?>
<?= $this->section('style'); ?>
<style>
    input[type="color"].mix-color {
        width: 52px;
        height: 38px;
        padding: 2px;
    }
</style>
<?= $this->endSection('style'); ?>

<?= $this->section('page'); ?>
<div class="app-main__inner">
    <div class="app-page-title">
        <div class="page-title-wrapper">
            <div class="page-title-heading">
                <div class="page-title-icon">
                    <i class="pe-7s-ticket icon-gradient bg-strong-bliss"></i>
                </div>
                <div>Label Mixcode
                    <div class="page-title-subheading">Pengaturan warna 10 kelas DDC &amp; tampilan label barcode warna</div>
                </div>
            </div>
            <div class="page-title-actions">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><i class="fa fa-home"></i> Home</a></li>
                        <li class="breadcrumb-item" aria-current="page">Label Mixcode</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <form method="post" action="<?= base_url('label-mixcode/save') ?>" id="mix_form">
        <div class="row">
            <!-- ── Warna 10 kelas DDC ── -->
            <div class="col-md-7">
                <div class="main-card mb-3 card">
                    <div class="card-header">
                        <i class="header-icon lnr-picture icon-gradient bg-plum-plate"></i>
                        Klasifikasi Warna Label
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Warna header label mengikuti digit pertama DeweyNo eksemplar. Kelola daftarnya di sini (tersimpan ke Master Kelas Besar).</p>
                        <table style="width: 100%;" class="table table-hover table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:70px;">Kelas</th>
                                    <th class="text-center" style="width:130px;">Rentang</th>
                                    <th class="text-center">Subjek</th>
                                    <th class="text-center" style="width:150px;">Warna</th>
                                    <th class="text-center" style="width:60px;">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kelas as $row) : ?>
                                    <tr>
                                        <td class="text-center"><b><?= esc($row['KdKelas']) ?></b></td>
                                        <td class="text-center"><small><?= esc($row['range']) ?></small></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="name[<?= esc($row['KdKelas']) ?>]" value="<?= esc($row['namakelas']) ?>" maxlength="255">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-center" style="gap:8px;">
                                                <input type="color" class="mix-color" name="color[<?= esc($row['KdKelas']) ?>]" value="<?= esc($row['Warna']) ?>">
                                                <code><?= esc($row['Warna']) ?></code>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm mix-del" data-kode="<?= esc($row['KdKelas']) ?>" title="Hapus kelas <?= esc($row['KdKelas']) ?>">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-success">
                                    <td class="text-center">
                                        <input type="text" class="form-control form-control-sm" name="new_code" maxlength="3" placeholder="010" style="width:70px;">
                                    </td>
                                    <td class="text-center"><small>baru</small></td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="new_name" maxlength="255" placeholder="Nama subjek baru (opsional)">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center" style="gap:8px;">
                                            <input type="color" class="mix-color" name="new_color" value="#ffffff">
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Opsi tampilan ── -->
            <div class="col-md-5">
                <div class="main-card mb-3 card">
                    <div class="card-header">
                        <i class="header-icon lnr-cog icon-gradient bg-plum-plate"></i>
                        Tampilan Label
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Posisi Barcode</b></label>
                            <select class="form-control" name="position">
                                <option value="left" <?= $options['MixcodePosition'] === 'left' ? 'selected' : '' ?>>Barcode di Kiri</option>
                                <option value="right" <?= $options['MixcodePosition'] === 'right' ? 'selected' : '' ?>>Barcode di Kanan</option>
                                <option value="both" <?= $options['MixcodePosition'] === 'both' ? 'selected' : '' ?>>Barcode Kiri + Kanan</option>
                            </select>
                            <small class="text-muted">Posisi barcode di semua label mixcode yang dicetak.</small>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Judul di Label</b></label>
                            <select class="form-control" name="title_mode" id="mix_title_mode">
                                <option value="full" <?= $options['MixcodeTitleMode'] === 'full' ? 'selected' : '' ?>>Tampil penuh</option>
                                <option value="crop" <?= $options['MixcodeTitleMode'] === 'crop' ? 'selected' : '' ?>>Dipotong N karakter</option>
                            </select>
                        </div>
                        <div class="form-group mb-3" id="mix_title_len_wrap">
                            <label class="form-label"><b>Panjang Potongan Judul</b></label>
                            <input type="number" class="form-control" name="title_len" min="1" max="100" value="<?= esc($options['MixcodeTitleLen']) ?>">
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Ukuran Font Judul (px)</b></label>
                            <input type="number" class="form-control" name="title_font" min="6" max="20" value="<?= esc($options['MixcodeTitleFont']) ?>">
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Tinggi Barcode (px)</b></label>
                            <input type="number" class="form-control" name="barcode_height" min="40" max="150" value="<?= esc($options['MixcodeBarcodeHeight']) ?>">
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Teks Header Label</b></label>
                            <select class="form-control" name="header_source" id="mix_header_source">
                                <option value="library" <?= $options['MixcodeHeaderSource'] === 'library' ? 'selected' : '' ?>>Nama perpustakaan (otomatis)</option>
                                <option value="custom" <?= $options['MixcodeHeaderSource'] === 'custom' ? 'selected' : '' ?>>Teks kustom</option>
                            </select>
                        </div>
                        <div class="form-group mb-3" id="mix_header_text_wrap">
                            <label class="form-label"><b>Teks Header Kustom</b></label>
                            <input type="text" class="form-control" name="header_text" maxlength="100" value="<?= esc($options['MixcodeHeaderText']) ?>" placeholder="mis. PERPUSTAKAAN DAERAH">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Pengaturan</button>
                        <a href="<?= base_url('master-kelas-besar') ?>" class="btn btn-outline-secondary">Master Kelas Besar</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?= $this->endSection('page'); ?>

<?= $this->section('script'); ?>
<script>
$(document).ready(function () {
    <?php if (session()->getFlashdata('swal_icon')) : ?>
        Swal.fire({
            icon: '<?= session()->getFlashdata('swal_icon') ?>',
            title: '<?= session()->getFlashdata('swal_title') ?>',
            html: '<?= session()->getFlashdata('swal_html') ?>',
            showConfirmButton: false,
            timer: 4000
        });
    <?php endif; ?>

    function toggleMixRows() {
        $('#mix_title_len_wrap').toggle($('#mix_title_mode').val() === 'crop');
        $('#mix_header_text_wrap').toggle($('#mix_header_source').val() === 'custom');
    }
    $('#mix_title_mode, #mix_header_source').on('change', toggleMixRows);
    toggleMixRows();

    // Simpan otomatis via AJAX (tanpa reload halaman): pilih warna /
    // ubah opsi langsung tersimpan. Input teks/nomor diberi jeda agar
    // tak terkirim tiap ketikan.
    var mixTimer = null;
    function mixSaveAjax(extra, onSuccess) {
        var data = $('#mix_form').serialize();
        if (extra) {
            data += '&' + $.param(extra);
        }
        $.ajax({
            url: $('#mix_form').attr('action'),
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (res) {
            if (res && res.status === 'success') {
                // Samakan label hex dengan pilihan picker.
                $('input.mix-color').each(function () {
                    $(this).closest('div').find('code').text($(this).val().toUpperCase());
                });
                Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message || '', showConfirmButton: false, timer: 1800 });
                if (onSuccess) {
                    onSuccess(res);
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: (res && res.message) || 'Gagal menyimpan.', showConfirmButton: true });
            }
        }).fail(function (xhr) {
            var msg = 'Gagal menyimpan.';
            try {
                msg = JSON.parse(xhr.responseText).message || msg;
            } catch (e) {}
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg, showConfirmButton: true });
        });
    }
    function mixSubmitSoon(ms) {
        clearTimeout(mixTimer);
        mixTimer = setTimeout(function () {
            mixSaveAjax();
        }, ms || 600);
    }
    $('#mix_form').on('change', 'input[type="color"], select', function () {
        mixSubmitSoon(400);
    });
    $('#mix_form').on('input', 'input[type="text"], input[type="number"]', function () {
        // Baris "tambah baru" dikecualikan: disimpan manual via Simpan
        // agar tak terkirim setengah isi saat mengetik.
        if ($(this).attr('name').indexOf('new_') === 0) {
            return;
        }
        mixSubmitSoon(1200);
    });
    // Form tetap bisa disimpan manual (tombol Simpan / tanpa JS): biarkan
    // submit biasa, kecuali pemicunya dari autosave di atas.
    $('#mix_form').on('submit', function (e) {
        if ($(this).data('mix-ajax') === true) {
            return;
        }
        e.preventDefault();
        $(this).data('mix-ajax', true);
        try {
            mixSaveAjax();
        } finally {
            $(this).data('mix-ajax', false);
        }
    });

    // Tombol trash: konfirmasi, hapus via AJAX, hilangkan barisnya.
    $('#mix_form').on('click', '.mix-del', function () {
        var $btn = $(this);
        var kode = $btn.data('kode');
        if (!confirm('Hapus kelas ' + kode + '? Label eksemplar kelas ini kembali ke warna default.')) {
            return false;
        }
        var extra = {};
        extra['del[' + kode + ']'] = '1';
        mixSaveAjax(extra, function () {
            $btn.closest('tr').fadeOut(200, function () {
                $(this).remove();
            });
        });
    });
});
</script>
<?= $this->endSection('script'); ?>
