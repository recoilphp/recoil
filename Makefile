SOURCE = $(shell find src test -type f)

test: | vendor
	php -c test/etc/php.ini vendor/bin/peridot

coverage: artifacts/tests/coverage/index.html

coverage-open: artifacts/tests/coverage/index.html
	open artifacts/tests/coverage/index.html

lint: $(SOURCE) | vendor
	@mkdir -p artifacts/
	vendor/bin/php-cs-fixer fix

prepare: lint coverage
	composer validate
	travis lint

ci: lint artifacts/tests/coverage/clover.xml
	php -c test/etc/php.ini -d zend.assertions=-1 vendor/bin/peridot

.PHONY: FORCE test coverage coverage-open lint prepare ci

vendor: composer.lock
	composer install

composer.lock: composer.json
	composer update

artifacts/tests/coverage/index.html: $(SOURCE) | vendor
	phpdbg -c test/etc/php.ini -qrr vendor/bin/peridot --reporter html-code-coverage --code-coverage-path=$(@D)

artifacts/tests/coverage/clover.xml: $(SOURCE) | vendor
	phpdbg -c test/etc/php.ini -qrr vendor/bin/peridot --reporter clover-code-coverage --code-coverage-path=$@

%.php: FORCE
	@php -l $@ > /dev/null
