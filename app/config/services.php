<?php

use Phalcon\Version;
use Phalcon\Mvc\View\Exception;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\DI\FactoryDefault;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Mvc\Model\Metadata\Memory as MetaData;
use Phalcon\Cache\Backend\Libmemcached;
use Phalcon\Cache\Frontend\Data as FrontData;
use Phalcon\Session\Adapter\Libmemcached as SessionAdapter;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Events\Manager as EventsManager;
use TTDemo\Library\Elements;
use TTDemo\Plugins\NotFoundPlugin;
use TTDemo\Plugins\Acl\Resource;
use TTDemo\Plugins\Acl\SecurityPlugin;
use Phalcon\Mvc\Router;

/**
 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
 */
$di = new FactoryDefault;

$di->setShared('config', $config);

$eventsManager = new EventsManager;

$di->setShared('eventsManager', $eventsManager);

/**
 * We register the events manager
 */
$di->setShared('dispatcher', function () use ($di, $eventsManager) {
    $securityPlugin = new SecurityPlugin;
    $securityPlugin->setResources(new Resource);

    /**
     * Check if the user is allowed to access certain action using the SecurityPlugin
     */
    $eventsManager->attach('dispatch:beforeDispatch', $securityPlugin);

    /**
     * Handle exceptions and not-found exceptions using NotFoundPlugin
     */
    $eventsManager->attach('dispatch:beforeException', new NotFoundPlugin);

    $dispatcher = new Dispatcher;

    $dispatcher->setDefaultNamespace('TTDemo\Controllers');
    $dispatcher->setEventsManager($eventsManager);

    return $dispatcher;
});

/**
 * The URL component is used to generate all kind of urls in the application
 */
$di->setShared('url', function () use ($config) {
    $url = new UrlProvider;
    $url->setBaseUri($config->get('application')->baseUri);

    return $url;
});

/**
 * Setting the View and View Engines
 */
$di->setShared('view', function () use ($config, $di, $eventsManager) {
    $view = new View;

    $view->registerEngines(
        [
            '.volt'  => function ($view, $di) use ($config) {
                $volt = new Volt($view, $di);

                $path = APPLICATION_ENV == APP_TEST ? DOCROOT . 'tests/_cache/' : DOCROOT . $config->get('volt')->cacheDir;

                $options = [
                    'compiledPath'      => $path,
                    'compiledExtension' => $config->get('volt')->compiledExt,
                    'compiledSeparator' => $config->get('volt')->separator,
                    'compileAlways'     => APPLICATION_ENV !== APP_PRODUCTION,
                ];

                $volt->setOptions($options);

                $volt->getCompiler()
                    ->addFunction('strtotime', 'strtotime')
                    ->addFunction('sprintf', 'sprintf')
                    ->addFunction('str_replace', 'str_replace')
                    ->addFunction('is_a', 'is_a');

                return $volt;
            },
            '.phtml' => 'Phalcon\Mvc\View\Engine\Php'
        ]
    );

    $view
        ->setVar('version', Version::get())
        ->setViewsDir(DOCROOT . $config->get('application')->viewsDir);

    $eventsManager->attach('view', function ($event, $view) use ($di, $config) {
        /**
         * @var \Phalcon\Events\Event $event
         * @var \Phalcon\Mvc\View $view
         */
        if ($event->getType() == 'notFoundView') {
            $message = sprintf('View not found - %s', $view->getActiveRenderPath());
            throw new Exception($message);
        }
    });

    $view->setEventsManager($eventsManager);

    return $view;
});

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () use ($config) {
    $config = $config->get('database')->toArray();

    $dbClass = 'Phalcon\Db\Adapter\Pdo\\' . $config['adapter'];
    unset($config['adapter']);

    return new $dbClass($config);
});

/**
 * If the configuration specify the use of metadata adapter use it or use memory otherwise
 */
$di->set('modelsMetadata', function () {
    return new MetaData;
});

$di->setShared('cacheMemcache', function () {
    $frontCache = new FrontData(
        [
            "lifetime" => 86400,
        ]
    );
    $cache = new Libmemcached(
        $frontCache,
        [
            "servers" => [
                [
                    "host"   => '127.0.0.1',
                    "port"   => 11211,
                    "weight" => 1,
                ],
            ],
            "client" => [
                \Memcached::OPT_HASH       => \Memcached::HASH_MD5,
                \Memcached::OPT_PREFIX_KEY => "prefix.",
            ],
            "prefix"   => 'home_',
        ]
    );
    return $cache;
});

///**
// * Start the session the first time some component request the session service
// */
//$di->set('session', function () {
//    $session = new SessionAdapter;
//    $session->start();
//
//    return $session;
//});

//Isolating the session data
$di->setShared('session', function () use ($config) {
    $session = new SessionAdapter(
        [
            "servers" => [
                [
                    "host"   => '127.0.0.1',
                    "port"   => 11211,
                    "weight" => 1,
                ],
            ],
            "client" => [
                \Memcached::OPT_HASH       => \Memcached::HASH_MD5,
                \Memcached::OPT_PREFIX_KEY => "prefix.",
            ],
            "lifetime" => 86400,
            "prefix"   => 'home_',
        ]
    );
    $session->start();
    return $session;
});

/**
 * Add routing capabilities
 */
$di->setShared('router', function () use ($eventsManager) {
    return require APP_PATH . 'config/routes.php';
});

/**
 * Register the flash service with custom CSS classes
 */
$di->set('flash', function () {
    return new FlashSession(
        [
            'error'   => 'alert alert-danger',
            'success' => 'alert alert-success',
            'notice'  => 'alert alert-info',
            'warning' => 'alert alert-warning'
        ]
    );
});

/**
 * Register a user component
 */
$di->set('elements', function () {
    return new Elements;
});
