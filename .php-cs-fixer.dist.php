<?php

$finder = Symfony\Component\Finder\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP80Migration:risky' => true,

        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => [], 'strict' => true], // prevent leading slashes on PHP function calls
        'no_superfluous_elseif' => true,
        'not_operator_with_successor_space' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'phpdoc_types_order' => ['null_adjustment' => 'always_first', 'sort_algorithm' => 'none'], // do not set the sort algo to alpha because it fuck-up array definitions
        'single_trait_insert_per_statement' => true,
        'use_arrow_functions' => false, // do not force single line closure to be arrow functions
        'yoda_style' => ['equal' => null, 'identical' => null, 'less_and_greater' => null], // null = leave as-is, don't enforce anything either way
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
