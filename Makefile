test:
	php vendor/bin/phpunit tests/ --colors=always

static: static-phpstan static-codestyle-check

static-phpstan:
	composer install
	composer bin phpstan update
	vendor/bin/phpstan analyze $(PHPSTAN_PARAMS)

static-phpstan-update-baseline:
	composer install
	composer bin phpstan update
	$(MAKE) static-phpstan PHPSTAN_PARAMS="--generate-baseline"

static-codestyle-fix:
	composer install
	composer bin php-cs-fixer update
	vendor/bin/php-cs-fixer fix --diff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"
