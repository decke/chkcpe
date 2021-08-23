<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('chkcpe')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'single_quote' => true
    ])
    ->setFinder($finder)
    ->setLineEnding("\n")
    ->setUsingCache(false)
;
