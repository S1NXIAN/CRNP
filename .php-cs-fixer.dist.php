<?php
/**
 * .php-cs-fixer.dist.php — repo-wide style rules for CRNP.
 *
 * Goals (matched against the existing hand-written code):
 *  - 4-space indent, no tabs.
 *  - Single quotes for non-interpolating strings.
 *  - Short array syntax `[]`.
 *  - K&R-style braces (opening brace on same line).
 *  - Trailing newline at EOF.
 *  - Skip vendored PHPMailer (legacy code, not ours to reformat).
 *
 * Rule names + option keys verified against
 * https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/UPGRADE-v3.md
 * for v3.x renames.
 */

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('PHPMailer')
    ->exclude('uploads')
    ->exclude('vendor')
    ->notName('*.min.js')
    ->ignoreVCS(true)
    ->ignoreDotFiles(true);

$config = new PhpCsFixer\Config('CRNP style');
$config->setRiskyAllowed(true);
$config->setFinder($finder);

$config->setRules([
    // PSR-12 covers indent (4 spaces), LF endings, trailing newline,
    // brace placement, and most whitespace.
    '@PSR12' => true,

    // Keep K&R braces instead of PSR-12's allman style for control
    // structures — matches the existing hand-written style; would otherwise
    // rewrite hundreds of `if/foreach/function` lines.
    'braces' => [
        'position_after_functions_and_oop_constructs' => 'same',
        'position_after_control_structures' => 'same',
        'position_after_anonymous_constructs' => 'same',
    ],

    // Quotes / strings.
    'single_quote' => true,
    'no_trailing_whitespace' => true,
    'no_spaces_inside_parenthesis' => true,

    // Imports: alphabetical, one per line. Option keys per UPGRADE-v3.md.
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',
        'imports_order' => ['class', 'function', 'const'],
    ],
    'no_unused_imports' => true,

    // Cleanup.
    'no_useless_return' => true,
    'no_empty_statement' => true,
    'concat_space' => ['spacing' => 'one'],

    // Arrays.
    'array_syntax' => ['syntax' => 'short'],
    'trailing_comma_in_multiline' => [
        'elements' => ['arrays'],
    ],

    // PHP 8 idioms (we target 8.2; don't enforce typed props/methods that
    // would require rewriting hundreds of page files).
    'declare_strict_types' => false,
    'short_scalar_cast' => true,
    'cast_spaces' => ['space' => 'single'],
    'standardize_not_equals' => true,
]);

return $config;
