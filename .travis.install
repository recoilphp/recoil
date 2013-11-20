#!/usr/bin/env php
<?php
/**
 * This script is executed before composer dependencies are installed,
 * and as such must be included in each project as part of the skeleton.
 */

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
    passthru('curl -s -i -H "Authorization: token $ARCHER_TOKEN" https://api.github.com | grep "^X-RateLimit"');
} else {
    $composerFlags = '--prefer-source';
}

$file = '~/.composer/config.json';
$dir  = dirname($file);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($file, json_encode($config));

passthru('composer self-update --no-interaction');

$exitCode = 0;
passthru('composer install --dev --no-progress --no-interaction --ansi ' . $composerFlags, $exitCode);
exit($exitCode);
