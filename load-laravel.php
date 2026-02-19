<?php

require_once '/Users/suman/Desktop/projects/gec/vendor/autoload.php';

$app = require_once '/Users/suman/Desktop/projects/gec/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dotenv = Dotenv\Dotenv::createImmutable('/Users/suman/Desktop/projects/debug-gec');
$dotenv->load();


require './auth-set.php';