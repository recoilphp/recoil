<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__);

return Symfony\CS\Config\Config::create()
    ->fixers(array(
        '-concat_without_spaces',
        '-empty_return',
        '-new_with_braces',
        'align_double_arrow',
        'align_equals',
        'ordered_use',
        'short_array_syntax',
    ))
    ->finder($finder);
