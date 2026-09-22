<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;

new App();

require dirname(__DIR__) . '/app/Views/home.php';
