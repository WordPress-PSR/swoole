<?php
/**
 * WordPress PHPUnit test configuration for Swoole compatibility testing.
 *
 * Copy this file to wp-tests-config.php and adjust the values for your
 * local environment. This file is used by the wordpress-develop PHPUnit
 * test suite when testing against a Swoole-served WordPress instance.
 *
 * Usage:
 *   1. Clone wordpress-develop: git clone https://github.com/WordPress/wordpress-develop.git
 *   2. Copy this file to wordpress-develop/wp-tests-config.php
 *   3. Start the Swoole server: php server.php
 *   4. Run tests: cd wordpress-develop && vendor/bin/phpunit
 *
 * See: https://github.com/WordPress-PSR/swoole/issues/16
 */

// Test database — separate from the Swoole-served WordPress DB.
// PHPUnit will truncate this DB between test runs.
define( 'DB_NAME',     'wordpress_test' );
define( 'DB_USER',     'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST',     '127.0.0.1' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

// Table prefix used by the test suite (different from the live site prefix).
$table_prefix = 'wptests_';

// Absolute path to the WordPress installation served by Swoole.
// Adjust this to match your local setup.
define( 'ABSPATH', dirname( __DIR__ ) . '/swoole/wordpress/' );

// Test site URL — must match the running Swoole server.
define( 'WP_TESTS_DOMAIN',    'localhost:8889' );
define( 'WP_TESTS_EMAIL',     'admin@example.com' );
define( 'WP_TESTS_TITLE',     'Swoole Test Site' );
define( 'WP_PHP_BINARY',      'php' );
define( 'WPLANG',             '' );

// Disable multisite for initial compatibility run.
define( 'WP_TESTS_MULTISITE', false );

// Swoole-specific: some tests directly call header()/setcookie()/exit()
// which are replaced by the PSR request handler. These tests are expected
// to fail and should be tracked separately.
// Set to true to skip those tests rather than fail on them.
define( 'WP_TESTS_SKIP_HEADER_TESTS', false );
