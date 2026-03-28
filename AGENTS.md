# AGENTS.md — WordPress Swoole

## Project Overview

Runs WordPress on the Swoole high-performance HTTP server. Converts Swoole requests to PSR-7, processes through the WordPress PSR Request Handler, and returns PSR-7 responses. Achieves ~2x throughput vs nginx+php-fpm. Part of the WordPress-PSR project.

Default branch is `master`.

## Quick Start (Docker)

```bash
cp .env.example .env               # Optional: customize config
docker compose up -d                # Start all services
# Visit http://localhost:8889 (admin/password)
```

### Docker Commands

```bash
docker compose logs -f app          # View logs
docker compose exec app wp --info --allow-root  # WP-CLI
docker compose down                 # Stop services
docker compose down -v              # Destroy everything including DB volume
```

### Manual Installation

```bash
composer create-project -s dev wordpress-psr/swoole wordpress-swoole
php server.php                      # Start Swoole server
```

## Project Structure

```
swoole/
├── server.php                      # Swoole HTTP server entry point
├── docker-compose.yml              # Docker services (app + MariaDB)
├── Dockerfile                      # PHP 8.2 + Swoole + WP-CLI
├── docker-entrypoint.sh            # Container init (WP install, inotify reload)
├── .env.example                    # Environment variable template
├── wp-cli.yml                      # WP-CLI config (path: wordpress)
├── wp-tests-config-sample.php      # Test configuration
├── composer.json
└── README.md
```

## Code Style & Conventions

- **PHP version**: >= 8.2
- **Swoole extension**: Required (v6+)
- **No linter config** — follow PSR-12 and WordPress conventions
- **Key dependency**: `wordpress-psr/request-handler` (does the WordPress integration)
- **WordPress core**: Installed via Composer into `wordpress/` directory

## Key Patterns

- Swoole HTTP server dispatches requests through `chubbyphp-swoole-request-handler`
- Request routing: admin requests go to separate task workers (WordPress uses `WP_ADMIN` constant)
- Flow: Swoole Request → PSR-7 → WordPress Request Handler → PSR-7 → Swoole Response
- Hot reload via inotify (Docker) — PHP changes trigger automatic server restart
- WordPress installed to `wordpress/` subdirectory

## Important Notes

- **Never commit `.env`** — contains database credentials
- WordPress core modifications are handled by the `request-handler` package via Rector
- This is an experimental project — expect bugs in edge cases
- Redis object cache support available (uncomment in `docker-compose.yml`)
