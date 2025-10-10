<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'node_modules',
        'wp-content/uploads',
        'wp-content/cache',
        'dist',
        'build',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Alap
        '@PSR12' => true,

        // WordPress-barát finomhangolások
        'yoda_style'                   => false,
        'blank_line_after_namespace'   => true,
        'blank_line_after_opening_tag' => true,
        'cast_spaces'                  => ['space' => 'single'],
        'function_declaration'         => ['closure_function_spacing' => 'one'],
        'single_line_comment_style'    => ['comment_types' => ['hash']], // ne engedjen '#'-et
        'trailing_comma_in_multiline'  => true,

        // Általános tisztaság
        'array_syntax'                => ['syntax' => 'short'],
        'ordered_imports'             => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'           => true,
        'binary_operator_spaces'      => ['default' => 'align_single_space_minimal'],
        'concat_space'                => ['spacing' => 'one'],
        'blank_line_before_statement' => ['statements' => ['return', 'if', 'for', 'foreach', 'while']],
        'phpdoc_indent'               => true,
        'phpdoc_trim'                 => true,
        'single_quote'                => true,
        'no_trailing_whitespace'      => true,
        'no_whitespace_in_blank_line' => true,

        // Natív függvények – ésszel
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope'   => 'namespaced',
            'strict'  => false,
        ],
    ])
    ->setFinder($finder);
