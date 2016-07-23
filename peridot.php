<?php

declare (strict_types = 1); // @codeCoverageIgnore

use Evenement\EventEmitterInterface;
use Peridot\Console\Environment;
use Peridot\Reporter\CodeCoverage\AbstractCodeCoverageReporter;
use Peridot\Reporter\CodeCoverageReporters;

require __DIR__ . '/vendor/autoload.php';

return function (EventEmitterInterface $emitter) {
    (new CodeCoverageReporters($emitter))->register();

    $emitter->on('peridot.start', function (Environment $environment) {
        $environment->getDefinition()->getArgument('path')->setDefault('test/suite');
    });

    $emitter->on('peridot.end', function () {
        echo 'Peak memory usage: ', number_format(memory_get_peak_usage() / 1024 / 1024, 2), ' MB', PHP_EOL;
    });

    $emitter->on('code-coverage.start', function (AbstractCodeCoverageReporter $reporter) {
        $reporter->addDirectoryToWhitelist(__DIR__ . '/src');
    });
};
