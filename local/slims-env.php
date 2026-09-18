<?php
$env = 'development';
$conditional_environment = 'development';
$based_on_ip = false;
$range_ip = [''];

if (php_sapi_name() === 'cli') {
    $env = $conditional_environment !== $env ? $conditional_environment : $env;
}
