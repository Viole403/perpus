<?php
if (!isset($routes)) {
	$routes = \Config\Services::routes(true);
}

// Pengaturan Captcha (hCaptcha / nonaktif) untuk login.
$routes->group('pengaturan-captcha', ['namespace' => 'Captcha\Controllers'], function ($subroutes) {
	$subroutes->add('', 'Captcha::index');
	$subroutes->add('index', 'Captcha::index');
	$subroutes->post('save', 'Captcha::save');
});
