test: deps
	vendor/bin/peridot

coverage: deps
	phpdbg -qrr vendor/bin/peridot --reporter html-code-coverage --code-coverage-path=artifacts/tests/coverage

lint: $(shell find src)
	vendor/bin/php-cs-fixer fix

deps: vendor/autoload.php

prepare: lint coverage

ci: lint
	phpdbg -qrr vendor/bin/peridot --reporter clover-code-coverage --code-coverage-path=artifacts/tests/coverage/clover.xml

.PHONY: test coverage lint deps prepare ci

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update

src/%.php: FORCE
	@php -l $@

FORCE:
