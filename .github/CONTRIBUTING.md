# Contributing

**Recoil** is open source software; contributions from the community are
encouraged and appreciated. Please take a moment to read these guidelines
before submitting changes.

## Requirements

- [PHP 7](http://php.net)
- [GNU make](https://www.gnu.org/software/make/) (or equivalent)
- [composer](https://getcomposer.org/download/)
- [phpdbg](http://phpdbg.com) (required for building coverage reports)

## Running the tests

    make

The default target of the make file installs all necessary dependencies and runs
the tests.

Code coverage reports can be built with:

    make coverage

The coverage reports are written to the `artifacts/test/coverage` directory.

## Code style

PHP code must adhere to the [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
as closely as possible. This project includes a [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)
configuration file to assist with maintaining a consistent style.

## Submitting changes

Change requests are reviewed and accepted via pull-requests on GitHub. If you're
unfamiliar with this process, please read the relevant GitHub documentation
regarding [forking a repository](https://help.github.com/articles/fork-a-repo)
and [using pull-requests](https://help.github.com/articles/using-pull-requests).

Before submitting your pull-request (typically against the `master` branch),
please run:

    make prepare

To apply any automated code-style updates, run linting checks, run the tests and
build coverage reports. Please ensure that your changes are tested and that a
high level of code coverage is maintained.
