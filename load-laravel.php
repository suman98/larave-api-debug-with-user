<?php

// Load this project's autoloader (for vlucas/phpdotenv)
require_once __DIR__ . '/vendor/autoload.php';

// Load .env to get LARAVEL_PROJECT_PATH
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('LARAVEL_PROJECT_PATH');

$laravelPath = $_ENV['LARAVEL_PROJECT_PATH'];

require_once $laravelPath . '/vendor/autoload.php';

$app = require_once $laravelPath . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

require './auth-set.php';