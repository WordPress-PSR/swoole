WordPress Swoole
===========

A method of running WordPress with the high performance, event-driven Swoole HTTP Server.
This project is very much a work in progress, and many bugs can be expected.
Pull requests are very welcome.

Installation
------------

Swoole must be installed from pecl or [another source](https://www.swoole.co.uk/docs/get-started/installation).

```bash
composer create-project -s dev wordpress-psr/swoole wordpress-swoole
```
Start swoole process
```bash
cd wordpress-swoole
php server.php
```

Visit http://0.0.0.0:8889/ It should redirect to configure and perform WordPress's famous 5-minute installation.
If it fails for any reason create wp-config.php manually.
```bash
cp wordpress/wp-config-sample.php wp-config.php
```
Than run the installation with WP cli.
```bash
wp core install --url='http://0.0.0.0:8889' --title='Swoole Test' --admin_user=admin --admin_password=password --skip-email
```

Overview
-----------

Most of the heavy lifting is done by [WordPress PSR Request Handler](https://github.com/WordPress-PSR/request-handler/).
This project is mostly just glue that pieces the request handler with the [chubbyphp-swoole-request-handler](https://github.com/chubbyphp/chubbyphp-swoole-request-handler).
The basic flow goes like this:
1. Receive Swoole Request.
2. Convert Swoole Request to PSR-7 Request.
3. Pass to WordPress Request Handler.
4. Receive PSR-7 Response.
5. Convert to Swoole Response.

Because of the way WordPress makes use of constants such as `WP_ADMIN` to designate the source of some requests a system was developed to route certain requests to specific workers, so the admin would be served by a separate pool from the frontend.
In the case the flow looks like:

1. Receive Swoole Request.
2. Convert Swoole Request to PSR-7 Request.
3. Determine if the request is for a special route.
3. Use Swoole\Server::task() to send the request to a task worker designated for the special route.
3. The task worker passes the request to the WordPress Request Handler.
4. Send PSR-7 Response back to main worker.
5. Convert to Swoole Response.

Running
-------
```bash
php server.php
```

Performance
-------
About 2X better. A simple ab of the homepage with only the base install data shows
695 requests pre second running WordPress on Swoole vs 324 running php-fpm.
Nginx+php-fpm:
```
ab -n 10000 -c 8 http://pm.localhost:8088/

Server Software:        nginx/1.16.1
Server Hostname:        pm.localhost
Server Port:            8088

Document Path:          /
Document Length:        8570 bytes

Concurrency Level:      8
Time taken for tests:   30.841 seconds
Complete requests:      10000
Failed requests:        0
Total transferred:      88020000 bytes
HTML transferred:       85700000 bytes
Requests per second:    324.25 [#/sec] (mean)
Time per request:       24.672 [ms] (mean)
Time per request:       3.084 [ms] (mean, across all concurrent requests)
Transfer rate:          2787.14 [Kbytes/sec] received

Connection Times (ms)
min  mean[+/-sd] median   max
Connect:        0    0   0.0      0       0
Processing:    12   25   3.6     25      38
Waiting:       12   24   3.6     25      38
Total:         12   25   3.6     25      38

Percentage of the requests served within a certain time (ms)
50%     25
66%     26
75%     27
80%     28
90%     29
95%     30
98%     32
99%     32
100%     38 (longest request)
```
Swoole:
```
ab -n 10000 -c 8 http://0.0.0.0:8889/

Server Software:        swoole-http-server
Server Hostname:        0.0.0.0
Server Port:            8889

Document Path:          /
Document Length:        6256 bytes

Concurrency Level:      8
Time taken for tests:   14.383 seconds
Complete requests:      10000
Failed requests:        3996
   (Connect: 0, Receive: 0, Length: 3996, Exceptions: 0)
Total transferred:      64911212 bytes
HTML transferred:       62561212 bytes
Requests per second:    695.28 [#/sec] (mean)
Time per request:       11.506 [ms] (mean)
Time per request:       1.438 [ms] (mean, across all concurrent requests)
Transfer rate:          4407.40 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.0      0       1
Processing:     7   11  11.8     11     448
Waiting:        7   11  11.8     11     448
Total:          7   11  11.8     11     448

Percentage of the requests served within a certain time (ms)
  50%     11
  66%     11
  75%     11
  80%     12
  90%     12
  95%     12
  98%     13
  99%     15
 100%    448 (longest request)
```

Above tests were run with PHP 7.4.15, swoole 4.6.4 on an i7-1065G7 CPU.

License
-------

GPL, see LICENSE.
