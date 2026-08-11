.DEFAULT_GOAL := help

.PHONY: help test unit-tests integration-tests ui-e2e-tests format lint static-analysis build coverage

help:
	@printf '%s\n' 'qor-api commands:' \
		'  make test              run unit + integration + feature suites' \
		'  make unit-tests        run the Unit suite (sqlite, fast)' \
		'  make integration-tests run the Integration suite (real Postgres)' \
		'  make ui-e2e-tests      run the Feature suite (full HTTP cycle)' \
		'  make format            apply Pint code style fixes' \
		'  make lint              check Pint code style (no changes)' \
		'  make static-analysis   run PHPStan/Larastan' \
		'  make build             install production dependencies' \
		'  make coverage          run the full suite with coverage (>=80%)'

unit-tests:
	php artisan test --testsuite=Unit

integration-tests:
	./vendor/bin/pest --configuration=phpunit.integration.xml

ui-e2e-tests:
	php artisan test --testsuite=Feature

test: unit-tests integration-tests ui-e2e-tests

format:
	./vendor/bin/pint

lint:
	./vendor/bin/pint --test

static-analysis:
	./vendor/bin/phpstan analyse --memory-limit=512M

build:
	composer install --no-interaction --prefer-dist --optimize-autoloader

coverage:
	php artisan test --coverage --min=80
