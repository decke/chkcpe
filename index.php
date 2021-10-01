<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Product;
use CheckCpe\CPE\Status;
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
    $runner = new Runner();

    $lists = [
        Status::VALID => [],
        Status::INVALID => [],
        Status::DEPRECATED => [],
        Status::CHECKNEEDED => [],
        Status::READYTOCOMMIT => [],
        Status::UNKNOWN => []
    ];

    foreach ($lists as $key => $val) {
        $lists[$key] = [
            'status' => $key,
            'color' => Status::getColor($key),
            'cnt' => count($runner->listPorts($key))
        ];
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html', [
        'lists' => $lists
    ]);
});

$app->get('/gc', function ($request, $response) {
    $overlay = Config::getOverlay();
    $overlay->loadFromFile();

    foreach ($overlay->listPorts() as $origin) {
        try {
            $port = Port::loadFromDB($origin);

            if ($port === null) {
                $overlay->unset($origin);
                continue;
            }
        } catch (\Exception $e) {
            continue;
        }

        if ($port->getCPEStr() != '' && $port->getCPEStatus() == Status::VALID) {
            $overlay->unset($port->getOrigin(), 'confirmedmatch');
            $overlay->unset($port->getOrigin(), 'nomatch');

            if (count($overlay->get($port->getOrigin())) < 1) {
                $overlay->unset($port->getOrigin());
            }
        }
    }

    $overlay->saveToFile();

    return $response->withStatus(302)->withHeader('Location', '/');
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

$app->get('/check', function ($request, $response, $args) {
    $params = $request->getQueryParams();

    if (isset($params['origin'])) {
        $port = Port::loadFromDB($params['origin']);

        if ($port === null) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
    } else {
        $overlay = Config::getOverlay();
        $overlay->loadFromFile();
        $runner = new Runner();
        $ports = [];

        foreach ($runner->loadPorts(Status::CHECKNEEDED) as $port) {
            $key = '9999-'.$port->getOrigin();

            if ($overlay->exists($port->getOrigin(), 'priority')) {
                $priority = (int)$overlay->get($port->getOrigin(), 'priority');
                $key = sprintf('%04d-%s', 9999-$priority, $port->getOrigin());
            }

            $ports[$key] = $port;
        }

        ksort($ports);

        $port = array_shift($ports);
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'details.html', [
        'port' => $port
    ]);
});

$app->post('/check/match', function ($request, $response, $args) {
    $params = $request->getParsedBody();

    if (!isset($params['origin']) || !isset($params['cpe'])) {
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    $port = Port::loadFromDB($params['origin']);
    if ($port === null) {
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    $product = new Product($params['cpe']);

    $overlay = Config::getOverlay();
    $overlay->loadFromFile();

    $overlay->set($port->getOrigin(), 'confirmedmatch', (string)$product);
    $overlay->unset($port->getOrigin(), 'nomatch');

    $overlay->saveToFile();

    $port->setCPEStatus(Status::READYTOCOMMIT);
    $port->saveToDB();

    return $response->withStatus(302)->withHeader('Location', '/check');
});

$app->post('/check/nomatch', function ($request, $response, $args) {
    $params = $request->getParsedBody();

    if (!isset($params['origin']) || !isset($params['cpe'])) {
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    $port = Port::loadFromDB($params['origin']);
    if ($port === null) {
        return $response->withStatus(302)->withHeader('Location', '/?portunknown');
    }

    if (!isset($params['cpe'])) {
        return $response->withStatus(302)->withHeader('Location', '/?cpemissing');
    }

    $product = new Product($params['cpe']);

    $overlay = Config::getOverlay();
    $overlay->loadFromFile();

    $data = $overlay->get($port->getOrigin(), 'nomatch');

    if ($data === false) {
        $data = [];
    }

    if (!in_array((string)$product, $data)) {
        $data[] = (string)$product;

        $overlay->set($port->getOrigin(), 'nomatch', $data);
        $overlay->saveToFile();
    }

    $port->removeCPECandidate($product);

    if (count($port->getCPECandidates()) < 1) {
        $port->setCPEStatus(Status::UNKNOWN);
    }

    $port->saveToDB();

    if ($port->getCPEStatus() != Status::UNKNOWN) {
        return $response->withStatus(302)->withHeader('Location', '/check?origin='.$port->getOrigin());
    }

    return $response->withStatus(302)->withHeader('Location', '/check');
});

$app->run();

exit(0);
