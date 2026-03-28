<?php
/**
 * Rector configuration for the Swoole WordPress project.
 *
 * Applies the WordPress PSR transformations (exit→wp_exit, header→wp_header, etc.)
 * to the WordPress core installation and any plugins in wp-content/plugins/.
 *
 * This runs automatically via composer post-install-cmd / post-update-cmd.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
// These rule classes are provided by wordpress-psr/request-handler (require in composer.json).
// They live in vendor/wordpress-psr/request-handler/src/Rector/ and are registered
// in Composer's PSR-4 autoloader under the WordPressPsr\ namespace.
use WordPressPsr\Rector\NewCookieFunction;
use WordPressPsr\Rector\NewHeaderFunction;
use WordPressPsr\Rector\NewHeaderRemoveFunction;
use WordPressPsr\Rector\NoExit;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
	define( 'WPINC', 'wp-includes' );
	define( 'EP_NONE', 0 );
}

// Build paths dynamically — only include directories that exist.
$paths = [];
if ( is_dir( __DIR__ . '/wordpress/' ) ) {
	$paths[] = __DIR__ . '/wordpress/';
}
if ( is_dir( __DIR__ . '/wp-content/plugins/' ) ) {
	$paths[] = __DIR__ . '/wp-content/plugins/';
}

if ( empty( $paths ) ) {
	// Nothing to transform yet (e.g. fresh clone before composer install completes).
	return RectorConfig::configure();
}

return RectorConfig::configure()
	->withPaths( $paths )
	->withSkip( [
		// Third-party libraries bundled in WordPress that should not be transformed.
		__DIR__ . '/wordpress/wp-includes/SimplePie/*',
		__DIR__ . '/wordpress/wp-includes/sodium_compat/*',
		__DIR__ . '/wordpress/wp-includes/class-json.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-feed-cache.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-file.php',
		__DIR__ . '/wordpress/wp-includes/class-wp-simplepie-sanitize-kses.php',
		__DIR__ . '/wordpress/wp-admin/includes/noop.php',
		// Vendor directories inside plugins should not be transformed.
		'*/vendor/*',
		'*/node_modules/*',
	] )
	->withAutoloadPaths( [
		__DIR__ . '/vendor/autoload.php',
		__DIR__ . '/wordpress',
	] )
	->withRules( [
		NoExit::class,
		NewHeaderFunction::class,
		NewCookieFunction::class,
		NewHeaderRemoveFunction::class,
	] )
	->withImportNames( importDocBlockNames: false )
	->withParallel( 300 );
