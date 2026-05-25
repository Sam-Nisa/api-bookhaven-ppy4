<?php
$lines = file('storage/logs/laravel.log');
$errors = array_filter($lines, function($line) {
    return strpos($line, 'local.ERROR') !== false;
});
echo implode('', array_slice($errors, -10));
