<?php

use Reen\VagrantRepo\Box;
use Reen\VagrantRepo\BoxNotFound;
use Reen\VagrantRepo\JsonResponse;
use Silex\Application;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;

return call_user_func(function () {
    $app = new Application();
    $app['env'] = getenv('SYMFONY_ENV') ?: 'prod';
    $app->register(new MonologServiceProvider(), [
        'monolog.logfile' => dirname(__DIR__).'/app/'.$app['env'].'.log',
    ]);

    $app['boxes.path'] = dirname(__DIR__).'/web/vagrant';

    $app->get('/vagrant', function () use ($app) {
        return new JsonResponse([
            'boxes' => list_boxes($app['boxes.path']),
        ]);
    });

    $app->post('/vagrant/{name}', function ($name, Request $request) use ($app) {
        try {
            $definition = json_decode($request->getContent(), true);
            write_box($app['boxes.path'], $name, $definition);

            $box = fetch_box($app['boxes.path'], $name, url_generator($app));

            return new JsonResponse($box->describe());
        } catch (RuntimeException $ex) {
            return new JsonResponse([
                'error' => $ex->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    });

    $app->get('/vagrant/{name}', function ($name) use ($app) : Response {
        try {
            $box = fetch_box($app['boxes.path'], $name, url_generator($app));
        } catch (BoxNotFound $ex) {
            return new JsonResponse([
                'error' => 'Not found!',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($box->describe());
    })->bind('box');

    $app->post('/vagrant/{name}/boxes/{version}.box', function ($name, $version, Request $request) use ($app) : Response {
        try {
            $box = fetch_box($app['boxes.path'], $name, url_generator($app));
            if ($box->hasVersion($version)) {
                return new JsonResponse([
                    'error' => 'Box version already exists',
                ], Response::HTTP_NOT_FOUND);
            }

            $stream = $request->getContent(true);
            write_version($app['boxes.path'], $name, $version, $stream);

            $box = fetch_box($app['boxes.path'], $name, url_generator($app));

            return new JsonResponse($box->describe());
        } catch (BoxNotFound $ex) {
            return new JsonResponse([
                'error' => 'Box does not exist',
            ], Response::HTTP_NOT_FOUND);
        }
    })->assert('version', '.+');

    $app->get('/vagrant/{name}/boxes/{version}.box', function ($name, $version, Request $request) use ($app) : Response {
        try {
            $box = fetch_box($app['boxes.path'], $name, url_generator($app));
            $versionPath = $box->path($version);

            logger($app)->info('Box downloaded via PHP, your webserver does a better job at this.', [
                'boxPath' => $versionPath,
                'uri' => $request->getUri(),
            ]);

            return new BinaryFileResponse($versionPath);
        } catch (BoxNotFound $ex) {
            return new JsonResponse([
                'error' => 'Not found!',
            ], Response::HTTP_NOT_FOUND);
        }
    })->bind('box_version')->assert('version', '.+');

    return $app;
});

function list_boxes($boxPath) : array
{
    $boxes = [];
    $files = scandir($boxPath);
    foreach ($files as $name) {
        if ($name[0] === '.') {
            continue;
        }

        $definition = sprintf('%s/%s/box.json', $boxPath, $name);
        if (!file_exists($definition)) {
            continue;
        }

        $boxes[] = $name;
    }

    return $boxes;
}

function cache_warmup_box($basePath, $name, UrlGenerator $urlGenerator)
{
    $boxPath = sprintf('%s/%s', $basePath, $name);

    $versions = [];
    $paths = [];
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

        $url = $urlGenerator->generate('box_version', [
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

        $paths[$version] = $fullPath;
    }

    $cache = [
        'versions' => $versions,
        'paths' => $paths,
    ];

    file_put_contents($boxPath.'/cache.json', json_encode($cache));

    return $cache;
}

function fetch_box($basePath, $name, UrlGenerator $urlGenerator) : Box
{
    $boxPath = sprintf('%s/%s', $basePath, $name);
    $boxDefinitionString = file_get_contents($boxPath.'/box.json');
    $boxDefinition = json_decode($boxDefinitionString, true);

    if (!file_exists($boxPath.'/cache.json')) {
        $cache = cache_warmup_box($basePath, $name, $urlGenerator);
    } else {
        $cacheString = file_get_contents($boxPath.'/cache.json');
        $cache = json_decode($cacheString, true);
    }

    return new Box($boxDefinition['name'], $boxDefinition['description'], $cache['versions'], $cache['paths']);
}

function write_box($basePath, $name, $definition)
{
    if (!array_key_exists('name', $definition)) {
        throw new \RuntimeException('Name must be provided.');
    }

    if ($definition['name'] != $name) {
        throw new \RuntimeException('Name change not supported yet.');
    }

    if (!array_key_exists('description', $definition)) {
        throw new \RuntimeException('Description must be provided.');
    }

    $boxPath = sprintf('%s/%s', $basePath, $name);
    if (!is_dir($boxPath) && !mkdir($boxPath.'/boxes', 0755, true)) {
        throw new \RuntimeException('Unable to create box directory.');
    }

    $content = json_encode([
        'name' => $definition['name'],
        'description' => $definition['description'],
    ]);
    $result = file_put_contents($boxPath.'/box.json', $content);
    if ($result !== strlen($content)) {
        echo strlen($content).' '.$result.PHP_EOL;
        throw new \RuntimeException('Writing box.json failed.');
    }
}

function write_version($basePath, $name, $version, $stream)
{
    $boxPath = sprintf('%s/%s', $basePath, $name);
    $versionPath = sprintf('%s/boxes/%s.box', $boxPath, $version);
    if (file_exists($versionPath)) {
        throw new RuntimeException('Version already exists.');
    }

    $handle = fopen($versionPath, 'w');
    stream_copy_to_stream($stream, $handle);

    unlink($boxPath.'/cache.json');
}

function url_generator(Application $app) : UrlGenerator
{
    return $app[__FUNCTION__];
}

function logger(Application $app) : \Psr\Log\LoggerInterface
{
    return $app[__FUNCTION__];
}
