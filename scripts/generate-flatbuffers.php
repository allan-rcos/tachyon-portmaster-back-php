<?php

declare(strict_types=1);

/**
 * Portable flatc runner.
 *
 * Replaces the previous shell one-liner (`flatc --php -o $(pwd)/src/ .../*.fbs`),
 * which relied on POSIX `$(pwd)` and shell glob expansion and therefore did not
 * run on Windows. This resolves paths and expands the schema glob in PHP, then
 * invokes `flatc`. The post-generation normalization lives in
 * {@see patch-flatbuffers.php}, run right after this by the composer script.
 *
 * Requires `flatc` on PATH.
 */

$root = dirname(__DIR__);
$schemaGlob = $root . '/swagger/flatbuffers/schemas/*.fbs';
$outDir = $root . '/src/';

$schemas = glob($schemaGlob) ?: [];
if ($schemas === []) {
    fwrite(STDERR, "No .fbs schemas found at: $schemaGlob\n");
    exit(1);
}

$flatc = getenv('FLATC') ?: 'flatc';

$command = array_merge([$flatc, '--php', '-o', $outDir], $schemas);
$escaped = implode(' ', array_map('escapeshellarg', $command));

echo "running: $escaped\n";
passthru($escaped, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "flatc failed with exit code $exitCode\n");
    exit($exitCode);
}

echo 'flatc generated ' . count($schemas) . " schema(s) into src/API/Fbs.\n";
