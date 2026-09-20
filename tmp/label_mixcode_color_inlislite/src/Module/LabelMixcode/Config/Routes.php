<?php
if (!isset($routes)) {
	$routes = \Config\Services::routes(true);
}

// Pengaturan Label Mixcode Warna (port plugin SLiMS label_mixcode_color_slims)
$routes->group('label-mixcode', ['namespace' => 'LabelMixcode\Controllers'], function ($subroutes) {
	$subroutes->add('', 'LabelMixcode::index');
	$subroutes->add('index', 'LabelMixcode::index');
	$subroutes->post('save', 'LabelMixcode::save');
	$subroutes->get('default-template', 'LabelMixcode::defaultTemplate');
});
