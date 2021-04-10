<?php
declare(strict_types=1);

namespace App;

use Mezzio\Swoole\HotCodeReload\FileWatcher\InotifyFileWatcher;
use Chubbyphp\SwooleRequestHandler\PsrRequestFactory;
use Chubbyphp\SwooleRequestHandler\SwooleResponseEmitter;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;
use Swoole\Server\Task;
use Swoole\Constant;
use WordPressPsr\BucketWordPressRoutes;
use WordPressPsr\RequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request as SwooleRequest;
use Swoole\ExitException;

// The below causes all kinds of problems
// It may be possible to use Coroutines with a dropin wp-db and object cache
// These would be a huge effort.
// \Swoole\Runtime::enableCoroutine();

require __DIR__ . '/vendor/autoload.php';

$wordpres_path = __DIR__ . '/wordpress';
$http          = new Server( '0.0.0.0', 8889 );

$http->set(
	array(
		Constant::OPTION_DOCUMENT_ROOT            => $wordpres_path,
		Constant::OPTION_ENABLE_STATIC_HANDLER    => true,
		Constant::OPTION_ENABLE_COROUTINE         => true,
		Constant::OPTION_WORKER_NUM               => swoole_cpu_num(),
		Constant::OPTION_TASK_WORKER_NUM          => BucketWordPressRoutes::MIN_REQUIRED_WORKERS,
		Constant::OPTION_TASK_ENABLE_COROUTINE    => true,
		Constant::OPTION_STATIC_HANDLER_LOCATIONS => array( '/wp-admin', '/wp-content', '/wp-includes' ),
	)
);

$http->on(
	'start',
	function ( Server $server ): void {
		echo 'Swoole http server is started at http://0.0.0.0:8889' . PHP_EOL;
		echo 'WorkerID:' . $server->getWorkerId() . PHP_EOL;
		echo 'ManagerPid:' . $server->getManagerPid() . PHP_EOL;
		echo 'MasterPid:' . $server->getMasterPid() . PHP_EOL;
	}
);

$http->on(
	'WorkerStart',
	function ( $serv, $worker_id ) use ( $wordpres_path ) {
		global $argv;
		if ( $worker_id >= $serv->setting['worker_num'] ) {
			swoole_set_process_name( "php {$argv[0]} task worker" );
		} else {
			swoole_set_process_name( "php {$argv[0]} event worker" );
		}
		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
		}

		clearstatcache();
		error_log( "Worker #$worker_id started" );
	}
);

$task_workers = new BucketWordPressRoutes();

for ( $i = 0; $i < $http->setting['task_worker_num']; $i++ ) {
	$task_workers->addWorker( $i );
}

$psr17 = new Psr17Factory();
/**
 * @var RequestHandlerInterface $app
*/
$app = new RequestHandler( $wordpres_path, $psr17, $psr17 );

$swoole_response_emitter = new SwooleResponseEmitter();
$psr_request_factory     = new PsrRequestFactory(
	$psr17,
	$psr17,
	$psr17
);

$http->on(
	'request',
	function (
		SwooleRequest $swoole_request,
		SwooleResponse $swoole_response
	) use (
		$app,
		$task_workers,
		$http,
		$psr_request_factory,
		$swoole_response_emitter
	) {
		$request   = $psr_request_factory->create( $swoole_request );
		$worker_id = $task_workers->getWorkerForRequest( $request );
		if ( BucketWordPressRoutes::DO_NOT_USE_WORKER === $worker_id ) {
			try {
				$response = $app->handle( $request );
				$swoole_response_emitter->emit( $response, $swoole_response );
			} catch ( ExitException $e ) {
				// WordPress code usually does not call exit directly anymore but sometime it does.
				error_log( $e->getMessage() );
				if ( 'wp-load.php' === basename( $e->getFile() ) ) {
					// WordPress died loading probably missing wp-config.php redirecting to setup.
					$swoole_response->status( 302 );
					$swoole_response->header( 'Location', wp_guess_url() . '/wp-admin/setup-config.php' );
				} else {
					$swoole_response->status( 200 );
					$swoole_response->write( 'WordPress called exit when it shouldn\'t probably it\'s a <a href="https://github.com/WordPress-PSR/swoole/issues/new">bug</a>.' );
				}
				$swoole_response->end();
			}
		} else {
			$http->task(
				$request,
				$worker_id,
				function ( Server $server, $task_id, $data ) use ( $swoole_response, $swoole_response_emitter ) {
					$swoole_response_emitter->emit( $data, $swoole_response );
				}
			);
		}
	}
);

$http->on(
	'task',
	function ( Server $server, Task $task ) use ( $app, $task_workers ) {
		$response = $app->handle( $task->data );

		$task->finish( $response );
		if ( $task_workers->shouldShutdownAfter( $task->data ) ) {
			$server->stop();
		}
	}
);

$http->on(
	'WorkerStop',
	function ( $server, $worker_id ) {
		error_log( "Worker #$worker_id stopped" );
	}
);

if ( extension_loaded( 'inotify' ) && class_exists( InotifyFileWatcher::class ) ) {
	$inotify = new InotifyFileWatcher();
	$inotify->addFilePath( $wordpres_path );

	$http->tick(
		555,
		function () use ( $inotify, $http ) {

			$changed_file_paths = $inotify->readChangedFilePaths();

			if ( ! $changed_file_paths ) {
				return;
			}
			foreach ( $changed_file_paths as $path ) {
				echo "Reloading due to file change: $path";
			}
			$http->reload();
		}
	);
}
$http->start();
