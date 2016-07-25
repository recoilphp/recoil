<?php

declare (strict_types = 1); // @codeCoverageIgnore

use Evenement\EventEmitterInterface;
use Peridot\Console\Environment;
use Peridot\Reporter\CodeCoverage\AbstractCodeCoverageReporter;
use Peridot\Reporter\CodeCoverageReporters;

$autoloader = require __DIR__ . '/vendor/autoload.php';
assert(
    (new \Recoil\Dev\Instrumentation\Autoloader($autoloader))->register() ||
    true
);

return function (EventEmitterInterface $emitter) {
    (new CodeCoverageReporters($emitter))->register();

    $emitter->on('peridot.start', function (Environment $environment) {
        $environment->getDefinition()->getArgument('path')->setDefault('test/suite');
    });

    $emitter->on('code-coverage.start', function (AbstractCodeCoverageReporter $reporter) {
        $reporter->addDirectoryToWhitelist(__DIR__ . '/src');
    });
};
