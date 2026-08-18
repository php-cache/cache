<?php

declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? null;
if (!is_array($arguments) || 3 !== count($arguments) || !is_string($arguments[1]) || !is_string($arguments[2])) {
    fwrite(STDERR, "Usage: check-coverage.php <clover.xml> <minimum-percent>\n");

    exit(2);
}

$coverage = simplexml_load_file($arguments[1]);
if (false === $coverage || !isset($coverage->project->metrics)) {
    fwrite(STDERR, sprintf("Could not read Clover metrics from %s.\n", $arguments[1]));

    exit(2);
}

$metrics = $coverage->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$minimum = (float) $arguments[2];
if (0 === $statements) {
    fwrite(STDERR, "The coverage report does not contain executable statements.\n");

    exit(2);
}

$percentage = 100 * $coveredStatements / $statements;

printf("Line coverage: %.2f%% (%d/%d)\n", $percentage, $coveredStatements, $statements);

if ($percentage < $minimum) {
    fwrite(STDERR, sprintf("Coverage must be at least %.2f%%.\n", $minimum));

    exit(1);
}
