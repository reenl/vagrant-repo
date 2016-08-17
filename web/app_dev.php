<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Silex\Application;

call_user_func(function () {
    if (getenv('SYMFONY_ENV') !== 'dev') {
        echo 'This endpoint is only allowed when running in development mode.';

        return;
    }

    /** @var Application $app */
    $app = require dirname(__DIR__).'/app/bootstrap.php';
    $app['debug'] = true;
    $app->run();
});
