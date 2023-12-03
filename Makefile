test:
	php vendor/bin/phpunit tests/ --colors=always

static: static-phpstan static-psalm static-codestyle-check

static-phpstan:
	composer install
	composer bin phpstan update
	vendor/bin/phpstan analyze $(PHPSTAN_PARAMS)

static-psalm:
	composer install
	composer bin psalm update
	vendor/bin/psalm.phar $(PSALM_PARAMS)

static-codestyle-fix:
	composer install
	composer bin php-cs-fixer update
	vendor/bin/php-cs-fixer fix --diff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"
