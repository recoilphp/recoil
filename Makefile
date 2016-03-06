test: deps
	vendor/bin/phpunit

coverage: deps
	phpdbg -qrr vendor/bin/phpunit -c phpunit.xml.coverage

lint: deps
	vendor/bin/php-cs-fixer fix

deps: vendor/autoload.php

prepare: lint coverage

ci: coverage

.PHONY: test coverage lint deps prepare ci

vendor/autoload.php: composer.lock
	composer install

composer.lock: composer.json
	composer update
