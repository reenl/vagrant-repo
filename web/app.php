<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Silex\Application;

call_user_func(function () {
    /** @var Application $app */
    $app = require dirname(__DIR__).'/app/bootstrap.php';
    $app->run();
});
