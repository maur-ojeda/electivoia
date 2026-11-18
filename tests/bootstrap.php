<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// KernelTestCase requires KERNEL_CLASS. It used to come from a committed
// .env.test, which must not be versioned because it carries APP_SECRET.
// Default it here so tests run on any machine, with or without local env files.
$_SERVER['KERNEL_CLASS'] ??= 'App\\Kernel';
$_ENV['KERNEL_CLASS'] ??= 'App\\Kernel';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
