<?= $this->extend('App\Views\layout\main'); ?>
<?= $this->section('page'); ?>
<div class="app-main__inner">
    <div class="app-page-title">
        <div class="page-title-wrapper">
            <div class="page-title-heading">
                <div class="page-title-icon">
                    <i class="pe-7s-shield icon-gradient bg-strong-bliss"></i>
                </div>
                <div>Pengaturan Captcha
                    <div class="page-title-subheading">hCaptcha login: aktif/nonaktif — verifikasi murni via API sehingga jalan baik NS mengarah ke hosting maupun ke Cloudflare</div>
                </div>
            </div>
            <div class="page-title-actions">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><i class="fa fa-home"></i> Home</a></li>
                        <li class="breadcrumb-item" aria-current="page">Captcha</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <form method="post" action="<?= base_url('pengaturan-captcha/save') ?>">
        <div class="row">
            <div class="col-md-6">
                <div class="main-card mb-3 card">
                    <div class="card-header">
                        <i class="header-icon lnr-cog icon-gradient bg-plum-plate"></i>
                        Penyedia Captcha Login
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Status</b></label>
                            <select class="form-control" name="provider">
                                <option value="off" <?= $provider === 'off' ? 'selected' : '' ?>>Nonaktif (tanpa captcha)</option>
                                <option value="hcaptcha" <?= $provider === 'hcaptcha' ? 'selected' : '' ?>>hCaptcha</option>
                            </select>
                            <small class="text-muted">Nonaktif = login tanpa verifikasi (berguna untuk pengujian / jaringan tertutup).</small>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Site Key</b> (provider aktif)</label>
                            <input type="text" class="form-control" name="sitekey" maxlength="255" value="<?= esc($sitekey) ?>" placeholder="sitekey provider terpilih">
                            <small class="text-muted">Kosongkan untuk memakai nilai .env yang sudah ada. Kunci uji hCaptcha: <code>10000000-ffff-ffff-ffff-000000000001</code>.</small>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label"><b>Secret Key</b> (provider aktif)</label>
                            <input type="password" class="form-control" name="secret" maxlength="255" value="" placeholder="<?= $hasSecret ? '(sudah terisi — kosongkan bila tak diubah)' : '(belum ada secret tersimpan)' ?>">
                            <small class="text-muted">Tidak pernah ditampilkan balik; kosongkan bila tidak diubah. Secret uji hCaptcha: <code>0x0000000000000000000000000000000000000000</code>.</small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Pengaturan</button>
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
});
</script>
<?= $this->endSection('script'); ?>
