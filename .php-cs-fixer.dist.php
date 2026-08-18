<?php

declare(strict_types=1);

$header = <<<'HEADER'
This file is part of php-cache organization.

(c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
HEADER;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'Resources',
        'vendor',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => false,
        'header_comment' => ['header' => $header],
        'modern_serialization_methods' => false,
        'no_php4_constructor' => false,
        'no_trailing_whitespace_in_string' => true,
        'php_unit_construct' => false,
        'php_unit_mock_short_will_return' => false,
        'php_unit_set_up_tear_down_visibility' => false,
        'php_unit_test_annotation' => false,
        'protected_to_private' => false,
        'static_lambda' => false,
        'void_return' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
