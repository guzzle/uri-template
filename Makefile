test:
	php vendor/bin/phpunit tests/ --colors=always

static: static-phpstan static-codestyle-check static-composer-normalize-check

static-phpstan:
	composer install
	composer bin phpstan update
	vendor/bin/phpstan analyze $(PHPSTAN_PARAMS)

static-codestyle-fix:
	composer install
	composer bin php-cs-fixer update
	vendor/bin/php-cs-fixer fix --diff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"

static-composer-normalize-fix:
	composer install
	composer bin composer-normalize update
	composer bin composer-normalize normalize --diff $(COMPOSER_NORMALIZE_PARAMS) ../../composer.json

static-composer-normalize-check:
	$(MAKE) static-composer-normalize-fix COMPOSER_NORMALIZE_PARAMS="--dry-run"
