<?php

declare(strict_types=1);

/**
 * Rebuilds `phpstan-generated-baseline.neon`.
 *
 * PHPStan's own `--generate-baseline` would freeze *every* current error,
 * including real ones in hand-written code, which is precisely what we do not
 * want: the point is to silence the flatc output while keeping our own code at
 * level 9 with nothing swept under the rug.
 *
 * So this runs the analysis, keeps only the findings that live in generated
 * tables (`src/API/Fbs/**` minus the hand-written `*Proxy.php`), and writes
 * those to the baseline.
 *
 * Run after changing the schemas:
 *   php scripts/generate-phpstan-baseline.php
 */

$root = dirname(__DIR__);
$baselinePath = $root.'/phpstan-generated-baseline.neon';

// Analyse without the baseline, so its own entries do not disappear from view.
$config = $root.'/phpstan.neon';
$backup = null;
if (is_file($config)) {
    $backup = (string) file_get_contents($config);
    file_put_contents($config, "parameters:\n    inferPrivatePropertyTypeFromConstructor: true\n    level: 9\n    paths:\n        - src\n");
}

$command = escapeshellarg($root.'/vendor/bin/phpstan')
    .' analyse --no-progress --error-format=json --memory-limit=1G 2>/dev/null';
$json = (string) shell_exec($command);

if ($backup !== null) {
    file_put_contents($config, $backup);
}

/** @var array{files?: array<string, array{messages: list<array{message: string, identifier?: string}>}>}|null $report */
$report = json_decode($json, true);

if (!is_array($report) || !isset($report['files'])) {
    fwrite(STDERR, "Could not parse the PHPStan report.\n");
    exit(1);
}

$isGenerated = static function (string $path): bool {
    return str_contains($path, '/src/API/Fbs/')
        && !str_ends_with($path, 'Proxy.php')
        && !str_contains($path, '/Contracts/');
};

/** @var array<string, array<string, int>> $entries message => path => count */
$entries = [];
$kept = 0;
$skipped = 0;

foreach ($report['files'] as $path => $file) {
    $relative = str_starts_with($path, $root) ? substr($path, strlen($root) + 1) : $path;

    foreach ($file['messages'] as $message) {
        if (!$isGenerated($path)) {
            $skipped++;
            continue;
        }

        $entries[$message['message']][$relative] ??= 0;
        $entries[$message['message']][$relative]++;
        $kept++;
    }
}

$lines = ["parameters:", "\tignoreErrors:"];

ksort($entries);
foreach ($entries as $message => $paths) {
    ksort($paths);
    foreach ($paths as $relative => $count) {
        $lines[] = "\t\t-";
        $lines[] = "\t\t\tmessage: '#^".preg_quote($message, '#')."$#'";
        $lines[] = "\t\t\tidentifier: null";
        $lines[] = "\t\t\tcount: ".$count;
        $lines[] = "\t\t\tpath: ".$relative;
    }
}

// `identifier: null` is not valid; drop those helper lines.
$lines = array_values(array_filter($lines, static fn (string $line): bool => !str_contains($line, 'identifier: null')));

file_put_contents($baselinePath, implode("\n", $lines)."\n");

echo "baseline written: ".basename($baselinePath)."\n";
echo "  generated-code findings baselined: $kept\n";
echo "  hand-written findings left visible: $skipped\n";
