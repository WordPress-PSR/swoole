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
use Tgc\WordPressPsr\BucketWordPressRoutes;
use Tgc\WordPressPsr\RequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request as SwooleRequest;

$loader = require __DIR__.'/vendor/autoload.php';

$worker_num = 8;
$task_worker_num = 8;
$wordpres_path = '/home/dave/wordpress-psr-request-handler/wordpress';
$http = new Server('0.0.0.0', 8889);

$http->set([
	'document_root' => $wordpres_path,
	'enable_static_handler' => true,
	'enable_coroutine' => true,
	'worker_num' => $worker_num,
	'task_worker_num' => $task_worker_num,
    'task_enable_coroutine' => true,
]);

$http->on('start', function (Server $server): void {
	echo 'Swoole http server is started at http://0.0.0.0:8080'.PHP_EOL;
	echo 'WorkerID:'. $server->getWorkerId().PHP_EOL;
	echo 'ManagerPid:' . $server->getManagerPid().PHP_EOL;
	echo 'MasterPid:'.$server->getMasterPid().PHP_EOL;
});

$http->on('WorkerStart', function ($serv, $worker_id) use ( $wordpres_path ) {
	global $argv;
	if($worker_id >= $serv->setting['worker_num']) {
		error_log((string)$worker_id);
		swoole_set_process_name("php {$argv[0]} task worker");
	} else {
		swoole_set_process_name("php {$argv[0]} event worker");
	}
	error_log( "Worker #$worker_id starting" );
	if (function_exists('opcache_reset')) {
		opcache_reset();
	}
	if (function_exists('apc_clear_cache')) {
		apc_clear_cache();
	}

	clearstatcache();
	error_log( "Worker #$worker_id started" );
});

$task_workers = new BucketWordPressRoutes();

for ( $i = 0; $i < $http->setting['task_worker_num']; $i++ ) {
	$task_workers->addWorker( $i );
}

$psr17 = new Psr17Factory();
/** @var RequestHandlerInterface $app*/
$app = new RequestHandler( $wordpres_path, $psr17, $psr17 );

$swooleResponseEmitter = new SwooleResponseEmitter();
$psrRequestFactory = new PsrRequestFactory(
	$psr17,
	$psr17,
	$psr17
);

$http->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($app, $task_workers, $http, $psrRequestFactory, $swooleResponseEmitter) {
	$request = $psrRequestFactory->create($swooleRequest);
	$worker_id = $task_workers->getWorkerForRequest( $request );
	error_log( (string) $worker_id );
	if ( BucketWordPressRoutes::DO_NOT_USE_WORKER === $worker_id ) {
		$response = $app->handle( $request );
		$swooleResponseEmitter->emit( $response, $swooleResponse );
	} else {
		$http->task( $request, $worker_id, function ( Server $server, $task_id, $data ) use ( $swooleResponse, $swooleResponseEmitter ) {
			$swooleResponseEmitter->emit( $data, $swooleResponse );
		} );
	}
} );

$http->on('task', function ( Server $server, Task $task ) use ($app, $task_workers) {
	$response = $app->handle( $task->data );

	$task->finish( $response );
	if ( $task_workers->shouldShutdownAfter( $task->data ) ) {
		$server->stop();
	}
});

$http->on('WorkerStop', function($server, $worker_id ) {
	error_log( "Worker #$worker_id stopped" );
});

if ( extension_loaded('inotify') && class_exists( InotifyFileWatcher::class ) ) {
	$inotify = new InotifyFileWatcher();
	$inotify->addFilePath( $wordpres_path );

	$http->tick( 555, function () use ( $inotify, $http ) {

		$changedFilePaths = $inotify->readChangedFilePaths();

		if ( ! $changedFilePaths ) {
			return;
		}
		foreach ( $changedFilePaths as $path ) {
			echo "Reloading due to file change: $path";
		}
		$http->reload();
	} );
}
$http->start();
