<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphJS;

use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\{User, Site, Network};
use React\EventLoop\LoopInterface;
use Pho\Plugins\FeedPlugin;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;
use React\Cache\ArrayCache;

/**
 * The async/event-driven REST server daemon
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class Daemon
{

    use AutoloadingTrait;

    protected $heroku = false;
    protected $kernel;
    protected $server;
    protected $loop;
    
    public function __construct(string $configs = "", string $cors = "", bool $heroku = false, ?LoopInterface &$loop = null)
    {
        if(!isset($loop)) {
            $loop = \React\EventLoop\Factory::create();    
        }
        $this->loop = &$loop;
        $this->heroku = $heroku;
        $this->loadEnvVars($configs);
        $cors .= sprintf(";%s", getenv("CORS_DOMAIN"));
        $this->initKernel();
        $this->server = new Server($this->kernel, $this->loop);
        // won't bootstrap() to skip Pho routes.
        $controller_dir = __DIR__ . DIRECTORY_SEPARATOR . "Controllers";
        $this->server->withControllers($controller_dir);
        $router_dir = __DIR__ . DIRECTORY_SEPARATOR . "Routes";
        $this->server->withRoutes($router_dir);
        $this->addSessionSupport();
    }

    public function __call(string $method, array $params)//: mixed
    {
        return $this->server->$method(...$params);
    }

    protected function addSessionSupport(): void
    {
        $cache = new ArrayCache;
        $this->server->withMiddleware(
            new SessionMiddleware(
                'id',
                $cache, // Instance implementing React\Cache\CacheInterface
                [ // Optional array with cookie settings, order matters
                    0, // expiresAt, int, default
                    '', // path, string, default
                    '', // domain, string, default
                    false, // secure, bool, default
                    false // httpOnly, bool, default
                ]
            )
        );
    }

    protected function initKernel(): void
    {
        $configs = array(
            "services"=>array(
                "database" => ["type" => getenv('DATABASE_TYPE'), "uri" => getenv('DATABASE_URI')],
                "storage" => ["type" => getenv('STORAGE_TYPE'), "uri" =>  getenv("STORAGE_URI")],
                "index" => ["type" => getenv('INDEX_TYPE'), "uri" => getenv('INDEX_URI')]
            ),
            "default_objects" => array(
                    "graph" => getenv('INSTALLATION_TYPE') === 'groupsv2' ? Network::class : Site::class,
                    "founder" => User::class,
                    "actor" => User::class
            )
        );
        $this->kernel = new Kernel($configs);
        if(!empty(getenv("STREAM_KEY"))&&!empty(getenv("STREAM_SECRET"))) {
            $feedplugin = new FeedPlugin($this->kernel,  getenv('STREAM_KEY'),  getenv('STREAM_SECRET'));
            $this->kernel->registerPlugin($feedplugin);
        }
        $founder = new User(
            $this->kernel, $this->kernel->space(), 
            getenv('FOUNDER_NICKNAME'), 
            getenv('FOUNDER_EMAIL'), 
            getenv('FOUNDER_PASSWORD')
        );
        $this->kernel->boot($founder);
        //eval(\Psy\sh());
    }

}

