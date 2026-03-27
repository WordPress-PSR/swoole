#!/bin/bash
set -e

WORDPRESS_DIR="/var/www/html/wordpress"
WP_URL="${WP_URL:-http://localhost:8889}"
WP_TITLE="${WP_TITLE:-WordPress Swoole}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-password}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
DB_HOST="${DB_HOST:-db}"
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wordpress}"
DB_PASSWORD="${DB_PASSWORD:-wordpress}"

wait_for_db() {
	echo "Waiting for database to be ready..."
	local max_attempts=30
	local attempt=0
	while [ "$attempt" -lt "$max_attempts" ]; do
		if php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASSWORD}');" 2>/dev/null; then
			echo "Database is ready."
			return 0
		fi
		attempt=$((attempt + 1))
		echo "Attempt $attempt/$max_attempts — waiting 2s..."
		sleep 2
	done
	echo "ERROR: Database did not become ready in time." >&2
	return 1
}

setup_wordpress() {
	if [ ! -d "$WORDPRESS_DIR" ]; then
		echo "ERROR: WordPress directory not found at $WORDPRESS_DIR" >&2
		echo "Run 'composer install' first to download WordPress." >&2
		return 1
	fi

	# Generate wp-config.php if it does not exist
	if [ ! -f "$WORDPRESS_DIR/wp-config.php" ]; then
		echo "Generating wp-config.php..."
		wp config create \
			--path="$WORDPRESS_DIR" \
			--dbname="$DB_NAME" \
			--dbuser="$DB_USER" \
			--dbpass="$DB_PASSWORD" \
			--dbhost="$DB_HOST" \
			--allow-root
	fi

	# Run WordPress installation if not already installed
	if ! wp core is-installed --path="$WORDPRESS_DIR" --allow-root 2>/dev/null; then
		echo "Installing WordPress..."
		wp core install \
			--path="$WORDPRESS_DIR" \
			--url="$WP_URL" \
			--title="$WP_TITLE" \
			--admin_user="$WP_ADMIN_USER" \
			--admin_password="$WP_ADMIN_PASSWORD" \
			--admin_email="$WP_ADMIN_EMAIL" \
			--skip-email \
			--allow-root
		echo "WordPress installed successfully."
		echo "  URL:      $WP_URL"
		echo "  Username: $WP_ADMIN_USER"
		echo "  Password: $WP_ADMIN_PASSWORD"
	else
		echo "WordPress already installed."
	fi
}

wait_for_db
setup_wordpress

echo "Starting Swoole HTTP server..."
exec "$@"
