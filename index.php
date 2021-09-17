<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\Util\Logger;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/vendor/autoload.php';

/* handle static files from php builtin webserver */
if (php_sapi_name() == 'cli-server') {
    $basedir = dirname(__FILE__);
    $allowed_subdirs = array('/css/', '/js/', '/images/');

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = realpath($basedir.$uri);

    if ($path !== false && strpos($path, $basedir) === 0) {
        foreach ($allowed_subdirs as $dir) {
            if (strpos($path, $basedir.$dir) === 0) {
                return false;
            }
        }
    }
}

Logger::start();

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$twig = Twig::create(__DIR__.'/templates/', ['cache' => false]);

$app->add(TwigMiddleware::create($app, $twig));

$app->get('/', function ($request, $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html');
});

$app->get('/list/{status}', function ($request, $response, $args) {
    $runner = new Runner();

    $view = Twig::fromRequest($request);
    return $view->render($response, 'list.html', [
        'status' => $args['status'],
        'ports' => $runner->loadPorts($args['status'])
    ]);
});

$app->get('/{category}/{portname}', function ($request, $response, $args) {
    $port = Port::loadFromDB($args['category'].'/'.$args['portname']);
    if ($port === null) {
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'details.html', [
        'port' => $port
    ]);
});

$app->run();

exit(0);
