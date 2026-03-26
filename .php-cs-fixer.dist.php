<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'commonlib',
    ])
;
return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PER-CS' => true,
        'no_alternative_syntax' => ['fix_non_monolithic_code' => true],
        '@PHP8x2Migration' => true,
        'braces_position' => ['functions_opening_brace' => 'same_line', 'classes_opening_brace' => 'same_line'],
    ])
    ->setFinder($finder)
;