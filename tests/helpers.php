<?php

/** tests/helpers.php — shared harness for the offline checks. */
declare(strict_types=1);

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) {
        $fails++;
    }
}
