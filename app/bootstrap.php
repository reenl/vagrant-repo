<?php

use Reen\VagrantRepo\JsonResponse;
use Silex\Application;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;

return call_user_func(function () {
    $app = new Application();
    $app['env'] = getenv('SYMFONY_ENV') ?: 'prod';
    $app->register(new \Silex\Provider\MonologServiceProvider(), [
        'monolog.logfile' => dirname(__DIR__).'/app/'.$app['env'].'.log',
    ]);

    $app['boxes.path'] = dirname(__DIR__).'/web/vagrant';

    $app->get('/vagrant/{name}', function ($name) use ($app) : Response {
        $boxPath = $app['boxes.path'].'/'.$name;
        if (!is_dir($boxPath)) {
            return new JsonResponse([
                'error' => 'Not found!',
            ], Response::HTTP_NOT_FOUND);
        }

        $versions = [];
        $boxFiles = scandir($boxPath.'/boxes');
        foreach ($boxFiles as $boxFile) {
            if ($boxFile[0] === '.') {
                continue;
            }

            if (substr($boxFile, -4) !== '.box') {
                continue;
            }

            $version = substr($boxFile, 0, -4);
            $fullPath = $boxPath.'/boxes/'.$boxFile;

            $url = url_generator($app)->generate('box_version', [
                'name' => $name,
                'version' => $version,
            ], UrlGenerator::ABSOLUTE_URL);

            $versions[] = [
                'version' => $version,
                'providers' => [[
                    'name' => 'virtualbox',
                    'url' => $url,
                    'checksum_type' => 'sha256',
                    'checksum' => hash_file('sha256', $fullPath),
                ]],
            ];
        }

        return new JsonResponse([
            'name' => $name,
            'description' => file_get_contents($boxPath.'/description.txt'),
            'versions' => $versions,
        ]);
    })->bind('box');

    $app->get('/vagrant/{name}/boxes/{version}.box', function ($name, $version, Request $request) use ($app) : Response {
        $boxPath = $app['boxes.path'].'/'.$name.'/boxes/'.$version.'.box';
        if (!file_exists($boxPath)) {
            logger($app)->info('Not found!', [
                'boxPath' => $boxPath,
                'uri' => $request->getUri(),
            ]);

            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        logger($app)->info('Box downloaded via PHP, your webserver does a better job at this.', [
            'boxPath' => $boxPath,
            'uri' => $request->getUri(),
        ]);

        return new BinaryFileResponse($boxPath);
    })->bind('box_version')->assert('version', '.+');

    return $app;
});

function url_generator($app) : UrlGenerator
{
    return $app[__FUNCTION__];
}

function logger($app) : \Psr\Log\LoggerInterface
{
    return $app[__FUNCTION__];
}
