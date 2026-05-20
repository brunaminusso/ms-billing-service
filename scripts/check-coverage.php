<?php

declare(strict_types=1);

$path = $argv[1] ?? 'build/coverage.xml';
$min = (float)($argv[2] ?? '80');

if (!file_exists($path)) {
    fwrite(STDERR, "Coverage file not found: {$path}\n");
    exit(1);
}

$xml = simplexml_load_file($path);
if (!$xml) {
    fwrite(STDERR, "Failed to parse coverage file.\n");
    exit(1);
}

$metrics = $xml->xpath('//metrics');
if (!$metrics) {
    fwrite(STDERR, "No metrics found in coverage file.\n");
    exit(1);
}

$totalStatements = 0;
$coveredStatements = 0;

foreach ($metrics as $metric) {
    $totalStatements += (int)$metric['statements'];
    $coveredStatements += (int)$metric['coveredstatements'];
}

$coverage = $totalStatements > 0 ? ($coveredStatements / $totalStatements) * 100 : 0.0;

printf("Coverage: %.2f%%\n", $coverage);

if ($coverage < $min) {
    fwrite(STDERR, "Coverage below minimum: {$min}%\n");
    exit(1);
}
