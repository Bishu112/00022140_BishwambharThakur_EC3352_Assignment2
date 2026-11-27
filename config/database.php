<?php
// The connection values live in the ignored local config.php file.
$legacyConfig = dirname(__DIR__).'/config.php';
if (!is_file($legacyConfig)) {
    $legacyConfig = dirname(__DIR__).'/config.example.php';
}
return require $legacyConfig;
