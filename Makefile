test: vendor
	php -c test/etc/php.ini vendor/bin/peridot

coverage: vendor
	phpdbg -c test/etc/php.ini -qrr vendor/bin/peridot --reporter html-code-coverage --code-coverage-path=artifacts/tests/coverage

lint: vendor $(shell find src)
	vendor/bin/php-cs-fixer fix

prepare: lint coverage
	composer validate
	travis lint

ci: lint
	php -c test/etc/php.ini -d zend.assertions=-1 vendor/bin/peridot
	phpdbg -c test/etc/php.ini -qrr vendor/bin/peridot --reporter clover-code-coverage --code-coverage-path=artifacts/tests/coverage/clover.xml

.PHONY: FORCE test coverage lint prepare ci

vendor: composer.lock
	composer install

composer.lock: composer.json
	composer update

%.php: FORCE
	@php -l $@ > /dev/null
