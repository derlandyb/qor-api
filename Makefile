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
		'  make coverage          run Unit+Feature with coverage (>=80%); Integration needs real Postgres, excluded (see coverage target comment)'

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

# Scoped to Unit+Feature: phpunit.xml unconditionally forces DB_CONNECTION=sqlite (see its own
# comment), but Integration needs real Postgres (phpunit.integration.xml) — running it here would
# fail on any Postgres-only SQL (e.g. plpgsql triggers), not measure coverage.
coverage:
	php artisan test --testsuite=Unit,Feature --coverage --min=80
