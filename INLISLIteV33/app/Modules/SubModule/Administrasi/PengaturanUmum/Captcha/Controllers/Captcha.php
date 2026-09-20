<?php

namespace Captcha\Controllers;

/**
 * Captcha — pengaturan captcha login.
 *
 * Provider: off | hcaptcha | turnstile (Cloudflare) | recaptcha (Google v2).
 * Kunci generik (dipakai provider aktif):
 *   CaptchaProvider : off | hcaptcha | turnstile | recaptcha
 *   CaptchaSite     : sitekey (kosong = pakai .env/pertahankan lama)
 *   CaptchaSecret   : secret  (kosong = pakai .env/pertahankan lama)
 */
class Captcha extends \Base\Controllers\BaseController
{
    private $defaults = [
        'CaptchaProvider'       => '',
        'CaptchaHcaptchaSite'   => '',
        'CaptchaHcaptchaSecret' => '',
    ];

    public function index()
    {
        helper('captcha');
        $db = db_connect();

        $optRows = $db->table('settingparameters')
            ->select('Name, Value')
            ->whereIn('Name', array_keys($this->defaults))
            ->get()
            ->getResultArray();
        $options = [];
        foreach ($optRows as $optRow) {
            $options[$optRow['Name']] = (string) ($optRow['Value'] ?? '');
        }

        $cfg = captcha_config();

        $this->data['title']    = 'Pengaturan Captcha';
        $this->data['options']  = $options;
        $this->data['provider'] = $cfg['provider'];
        $this->data['sitekey']  = $cfg['sitekey'];
        // Secret tak pernah ditampilkan balik; hanya status terisi/tidak.
        $this->data['hasSecret'] = $cfg['secret'] !== '';

        return view('Captcha\Views\index', $this->data);
    }

    public function save()
    {
        if (!$this->request->getPost()) {
            return redirect()->to(base_url('pengaturan-captcha'));
        }

        $this->validation->setRules([
            'provider' => ['label' => 'Penyedia', 'rules' => 'required|in_list[off,hcaptcha,turnstile,recaptcha]'],
            'sitekey'  => ['label' => 'Site Key', 'rules' => 'permit_empty|max_length[255]'],
            'secret'   => ['label' => 'Secret Key', 'rules' => 'permit_empty|max_length[255]'],
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            $this->session->setFlashdata('swal_icon', 'error');
            $this->session->setFlashdata('swal_title', 'Gagal');
            $this->session->setFlashdata('swal_html', $this->validation->listErrors());
            return redirect()->to(base_url('pengaturan-captcha'))->withInput();
        }

        $post = $this->request->getPost();
        $db = db_connect();

        $options = ['CaptchaProvider' => $post['provider']];
        // Site/secret kosong = pertahankan nilai lama (jangan timpa dengan kosong).
        foreach (['CaptchaSite' => 'sitekey', 'CaptchaSecret' => 'secret'] as $name => $key) {
            $val = trim((string) ($post[$key] ?? ''));
            if ($val !== '') {
                $options[$name] = $val;
            }
        }

        foreach ($options as $name => $value) {
            $exists = $db->table('settingparameters')->where('Name', $name)->countAllResults();
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
        }

        $this->session->setFlashdata('swal_icon', 'success');
        $this->session->setFlashdata('swal_title', 'Berhasil');
        $this->session->setFlashdata('swal_html', 'Pengaturan Captcha berhasil disimpan.');
        return redirect()->to(base_url('pengaturan-captcha'));
    }
}
