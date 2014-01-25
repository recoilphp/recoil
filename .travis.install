#!/usr/bin/env php
<?php

// Update git to the latest version ...
passthru('sudo apt-get update');
passthru('sudo apt-get install git');

// Update composer to the latest version ...
passthru('composer self-update --no-interaction');

// Build a composer config that uses the GitHub OAuth token if it is available ...
$config = array(
    'config' => array(
        'notify-on-install' => false
    )
);

if ($token = getenv('ARCHER_TOKEN')) {
    $config['config']['github-oauth'] = array(
        'github.com' => $token
    );
    $composerFlags = '--prefer-dist';
} else {
    $composerFlags = '--prefer-source';
}

$file = '~/.composer/config.json';
$dir = dirname($file);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($file, json_encode($config));

// Display some information about GitHub rate limiting ...
if ($token) {
    passthru('curl -s -i -H "Authorization: token $ARCHER_TOKEN" https://api.github.com | grep "^X-RateLimit"');
}

// Install composer dependencies ...
$exitCode = 0;
passthru('composer install --dev --no-progress --no-interaction --ansi ' . $composerFlags, $exitCode);
exit($exitCode);
