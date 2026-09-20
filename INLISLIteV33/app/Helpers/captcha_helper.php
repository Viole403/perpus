<?php

/**
 * captcha_helper.php — penyedia captcha terpusat untuk login.
 *
 * Provider: off | hcaptcha | turnstile (Cloudflare) | recaptcha (Google v2).
 *
 * Konfigurasi dibaca dari settingparameters (diatur via menu
 * Pengaturan > Captcha), dengan fallback ke environment:
 *   CaptchaProvider : off | hcaptcha | turnstile | recaptcha
 *   CaptchaSite     : sitekey provider aktif
 *   CaptchaSecret   : secret provider aktif
 *
 * Env fallback per provider (kunci per customer di-installer/.env):
 *   hcaptcha  → HCAPTCHA_SITE_KEY / HCAPTCHA_SECRET_KEY
 *   turnstile → TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY
 *   recaptcha → RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY
 *
 * Semua provider diverifikasi murni via HTTPS API masing-masing,
 * sehingga jalan baik NS domain mengarah ke hosting maupun ke Cloudflare.
 *
 * Catatan: ini captcha form login. Proteksi bot/attacker site-wide
 * (challenge first-visit Cloudflare) adalah lapis terpisah di edge,
 * bukan di branch ini.
 */

if (!function_exists('captcha_providers')) {
    /**
     * Metadata per provider: field POST, endpoint verifikasi, widget.
     *
     * @return array<string,array{field:string,verify:string,div:string,script:string,preconnect:string[]}>
     */
    function captcha_providers()
    {
        return [
            'hcaptcha' => [
                'field'      => 'h-captcha-response',
                'verify'     => 'https://hcaptcha.com/siteverify',
                'div'        => 'h-captcha',
                'script'     => 'https://js.hcaptcha.com/1/api.js',
                'preconnect' => ['https://js.hcaptcha.com'],
            ],
            'turnstile' => [
                'field'      => 'cf-turnstile-response',
                'verify'     => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                'div'        => 'cf-turnstile',
                'script'     => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
                'preconnect' => ['https://challenges.cloudflare.com'],
            ],
            'recaptcha' => [
                'field'      => 'g-recaptcha-response',
                'verify'     => 'https://www.google.com/recaptcha/api/siteverify',
                'div'        => 'g-recaptcha',
                'script'     => 'https://www.google.com/recaptcha/api.js',
                'preconnect' => ['https://www.google.com', 'https://www.gstatic.com'],
            ],
        ];
    }
}

if (!function_exists('captcha_env_keys')) {
    /**
     * @return array{0:string,1:string}|null [sitekey, secret] atau null bila off/unknown.
     */
    function captcha_env_keys($provider)
    {
        $map = [
            'hcaptcha'  => ['HCAPTCHA_SITE_KEY', 'HCAPTCHA_SECRET_KEY'],
            'turnstile' => ['TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY'],
            'recaptcha' => ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'],
        ];
        return $map[$provider] ?? null;
    }
}

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

        $providers = array_keys(captcha_providers());
        $provider  = strtolower((string) ($stored('CaptchaProvider') ?? ''));
        if (!in_array($provider, array_merge(['off'], $providers), true)) {
            // Belum ada setting DB: tebak dari secret env yang terisi
            // (perilaku lama: hcaptcha bila secret-nya ada, selain itu off).
            $provider = 'off';
            foreach ($providers as $p) {
                $keys = captcha_env_keys($p);
                if ($keys && getenv($keys[1])) {
                    $provider = $p;
                    break;
                }
            }
        }

        $site = $stored('CaptchaSite');
        $secret = $stored('CaptchaSecret');
        if ($provider !== 'off') {
            $keys = captcha_env_keys($provider);
            if ($site === null && $keys) {
                $site = getenv($keys[0]);
            }
            if ($secret === null && $keys) {
                $secret = getenv($keys[1]);
            }
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
     * Nama field POST berisi token, mengikuti provider aktif.
     */
    function captcha_response_field()
    {
        $cfg = captcha_config();
        $all = captcha_providers();
        return $all[$cfg['provider']]['field'] ?? 'h-captcha-response';
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
     * Verifikasi token provider aktif. True bila provider nonaktif.
     */
    function captcha_verify($response = null)
    {
        $cfg = captcha_config();
        if ($cfg['provider'] === 'off') {
            return true;
        }

        $all = captcha_providers();
        if (!isset($all[$cfg['provider']])) {
            return false;
        }

        if ($response === null) {
            $request = \Config\Services::request();
            $response = $request->getPost($all[$cfg['provider']]['field']);
        }

        if (empty($cfg['secret']) || empty($response)) {
            return false;
        }

        $res = captcha_post_verify($all[$cfg['provider']]['verify'], [
            'secret'   => $cfg['secret'],
            'response' => $response,
        ]);

        return is_array($res) && isset($res['success']) && $res['success'] === true;
    }
}

if (!function_exists('captcha_widget_html')) {
    /**
     * HTML widget captcha untuk form ('' bila nonaktif / sitekey kosong).
     * Callback generik onCaptchaSuccess/Expired/Error — dipakai form login;
     * halaman lain yang tak mendefinisikannya tetap jalan (widget auto-render).
     */
    function captcha_widget_html()
    {
        $cfg = captcha_config();
        $all = captcha_providers();
        if ($cfg['provider'] === 'off' || empty($cfg['sitekey']) || !isset($all[$cfg['provider']])) {
            return '';
        }
        $meta = $all[$cfg['provider']];

        ob_start();
        ?>
        <div class="captcha-container" aria-label="Verifikasi keamanan">
            <div class="<?= esc($meta['div'], 'attr') ?>"
                  data-sitekey="<?= esc($cfg['sitekey'], 'attr') ?>"
                  data-callback="onCaptchaSuccess"
                  data-expired-callback="onCaptchaExpired"
                  data-error-callback="onCaptchaError"></div>
        </div>
        <script src="<?= esc($meta['script'], 'attr') ?>" async defer></script>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('captcha_preconnect_hosts')) {
    /**
     * Host untuk <link rel="preconnect"> di layout login, mengikuti provider.
     *
     * @return string[]
     */
    function captcha_preconnect_hosts()
    {
        $cfg = captcha_config();
        $all = captcha_providers();
        if ($cfg['provider'] === 'off' || empty($cfg['sitekey']) || !isset($all[$cfg['provider']])) {
            return [];
        }
        return $all[$cfg['provider']]['preconnect'];
    }
}
