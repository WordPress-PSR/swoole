WordPress Swoole
===========

A method of running WordPress with the high performance, event-driven Swoole HTTP Server.

Installation
------------

```bash
composer create-project tgc/wordpress-psr-swoole
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
About 2X better. Details coming but more testing and data points are needed.

License
-------

GPL, see LICENSE.