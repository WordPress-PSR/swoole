<?php
declare(strict_types=1);

namespace App;

use Chubbyphp\SwooleRequestHandler\OnRequest;
use Chubbyphp\SwooleRequestHandler\PsrRequestFactory;
use Chubbyphp\SwooleRequestHandler\SwooleResponseEmitter;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;
use Swoole\Server\Task;
use Tgc\WordPressPsr\BucketWordPressRoutes;
use Tgc\WordPressPsr\RequestHandler;
use Tgc\WordPressPsr\RequestHandlerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Swoole\Http\Request as SwooleRequest;

$loader = require __DIR__.'/vendor/autoload.php';

$worker_num = 8;
$task_worker_num = 8;

$http = new Server('0.0.0.0', 8889);

$http->set([
	'document_root' => '/home/dave/wordpress-psr-request-handler/wordpress',
	'enable_static_handler' => true,
	'enable_coroutine' => false,
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
$psr17 = new Psr17Factory();
/** @var RequestHandlerInterface $app*/
$app = new RequestHandler('/home/dave/wordpress-psr-request-handler/wordpress', $psr17, $psr17 );

$onrequest = new OnRequest(
	new PsrRequestFactory(
		$psr17,
		$psr17,
		$psr17
	),
	new SwooleResponseEmitter(),
	$app
);

$task_workers = new BucketWordPressRoutes();

for ( $i = 0; $i < $task_worker_num; $i++ ) {
	$task_workers->addWorker( $i );
}

$http->on('WorkerStart', function ($serv, $worker_id) use ( $task_workers ) {
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

	// Disable Hook
//    class_exists('Swoole\Runtime') && \Swoole\Runtime::enableCoroutine(false);
//	$app->bootstrap();
	error_log( "Worker #$worker_id started" );
});

$swooleResponseEmitter = new SwooleResponseEmitter();
$psrRequestFactory = new PsrRequestFactory(
	$psr17,
	$psr17,
	$psr17
);


$http->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($task_workers, $http, $psrRequestFactory, $onrequest, $swooleResponseEmitter) {
	$request = $psrRequestFactory->create($swooleRequest);
	$worker_id = $task_workers->getWorkerForRequest( $request );
	error_log((string)$worker_id);
	$http->task( $request, $worker_id, function( Server $server, $task_id, $data ) use( $swooleResponse, $swooleResponseEmitter ) {
		$swooleResponseEmitter->emit( $data, $swooleResponse );
	} );
//	$onrequest($swooleRequest, $swooleResponse);
//	$http->stop();
} );

$http->on('task', function ( Server $server, Task $task ) use ($app, $task_workers) {
	$response = $app->handle( $task->data );

//	echo "Task Callback: ";
//	var_dump( $server );
//	var_dump( $task_id );
	$task->finish( $response );
	if ( $task_workers->shouldShutdownAfter( $task->data ) ) {
		$server->stop();
	}
});

$http->on('WorkerStop', function($server, $worker_id ) {
	error_log( "Worker #$worker_id stopped" );
});

$http->start();