<?php

// Autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!class_exists('Eril\\DbTbl\\Config')) {
    die("\033[91m✖ Autoload not found. Run 'composer install' first." . "\033[0m\n");
}
