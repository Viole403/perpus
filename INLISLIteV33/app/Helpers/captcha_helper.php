<?php

/**
 * captcha_helper.php — penyedia captcha terpusat untuk login.
 *
 * Provider: off | hcaptcha.
 * (Varian Cloudflare Turnstile & Google reCAPTCHA ada di branch
 * feature/cloudflare-turnstile.)
 *
 * Konfigurasi dibaca dari settingparameters (diatur via menu
 * Pengaturan > Captcha), dengan fallback ke environment:
 *   CaptchaProvider : off | hcaptcha | turnstile | recaptcha
 *   CaptchaSite     : sitekey provider aktif (fallback HCAPTCHA_SITE_KEY)
 *   CaptchaSecret   : secret provider aktif (fallback HCAPTCHA_SECRET_KEY)
 *
 * Semua provider diverifikasi murni via HTTPS API masing-masing,
 * sehingga jalan baik NS domain mengarah ke hosting maupun ke Cloudflare.
 */

if (!function_exists('captcha_config')) {
    /**
     * @return array{provider:string,sitekey:string,secret:string}
     */
    function captcha_config()
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        $stored = function ($name) {
            try {
                $row = db_connect()->table('settingparameters')
                    ->select('Value')->where('Name', $name)->get()->getRow();
                if ($row && trim((string) $row->Value) !== '') {
                    return trim((string) $row->Value);
                }
            } catch (\Throwable $e) {
                // database belum siap (mis. saat install) → fallback env
            }
            return null;
        };

        $provider = strtolower((string) ($stored('CaptchaProvider') ?? ''));
        if (!in_array($provider, ['off', 'hcaptcha'], true)) {
            $secretProbe = $stored('CaptchaSecret');
            if ($secretProbe === null) {
                $secretProbe = getenv('HCAPTCHA_SECRET_KEY');
            }
            // Perilaku lama: menegakkan captcha bila secret terisi.
            $provider = !empty($secretProbe) ? 'hcaptcha' : 'off';
        }

        $site = $stored('CaptchaSite');
        if ($site === null) {
            $site = getenv('HCAPTCHA_SITE_KEY');
        }
        $secret = $stored('CaptchaSecret');
        if ($secret === null) {
            $secret = getenv('HCAPTCHA_SECRET_KEY');
        }

        $cfg = [
            'provider' => $provider,
            'sitekey'  => (string) $site,
            'secret'   => (string) $secret,
        ];
        return $cfg;
    }
}

if (!function_exists('captcha_enabled')) {
    function captcha_enabled()
    {
        return captcha_config()['provider'] !== 'off';
    }
}

if (!function_exists('captcha_response_field')) {
    /**
     * Nama field POST berisi token hCaptcha.
     */
    function captcha_response_field()
    {
        return 'h-captcha-response';
    }
}

if (!function_exists('captcha_post_verify')) {
    /**
     * POST form-urlencoded ke endpoint verifikasi. Kembalikan array hasil
     * atau null bila request gagal.
     */
    function captcha_post_verify($url, array $data)
    {
        // Gunakan cURL (bukan file_get_contents) karena verifikasi SSL-nya
        // mengikuti setting curl.cainfo di php.ini.
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            log_message('error', 'Captcha verify request failed: ' . $curlError);
            return null;
        }

        $decoded = json_decode($result, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('captcha_verify')) {
    /**
     * Verifikasi token hCaptcha. True bila provider nonaktif.
     */
    function captcha_verify($response = null)
    {
        $cfg = captcha_config();
        if ($cfg['provider'] === 'off') {
            return true;
        }

        if ($response === null) {
            $request = \Config\Services::request();
            $response = $request->getPost('h-captcha-response');
        }

        if (empty($cfg['secret']) || empty($response)) {
            return false;
        }

        $res = captcha_post_verify('https://hcaptcha.com/siteverify', [
            'secret'   => $cfg['secret'],
            'response' => $response,
        ]);

        return is_array($res) && isset($res['success']) && $res['success'] === true;
    }
}

if (!function_exists('captcha_widget_html')) {
    /**
     * HTML widget captcha untuk form login ('' bila nonaktif).
     * Callback onHcaptchaSuccess/Expired/Error dipertahankan untuk hCaptcha.
     */
    function captcha_widget_html()
    {
        $cfg = captcha_config();
        if ($cfg['provider'] === 'off' || empty($cfg['sitekey'])) {
            return '';
        }

        ob_start();
        ?>
        <div class="hcaptcha-container" aria-label="Verifikasi keamanan">
            <div class="h-captcha"
                 data-sitekey="<?= esc($cfg['sitekey'], 'attr') ?>"
                 data-callback="onHcaptchaSuccess"
                 data-expired-callback="onHcaptchaExpired"
                 data-error-callback="onHcaptchaError"></div>
        </div>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
        <?php
        return (string) ob_get_clean();
    }
}
