test: install
	vendor/bin/archer test

coverage: install
	vendor/bin/archer cov

lint: install
	./vendor/bin/php-cs-fixer fix

install: vendor/autoload.php

prepare: lint coverage

ci: coverage

.PHONY: _default test coverage lint install prepare ci

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update
