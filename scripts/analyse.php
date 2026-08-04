<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$phpstan = $root.'/vendor/bin/phpstan';
if (!is_file($phpstan)) {
    fwrite(STDERR, "phpstan is not installed\n");
    exit(1);
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($phpstan)
    .' analyse --configuration='.escapeshellarg($root.'/phpstan.neon.dist')
    .' --no-progress --error-format=raw';
passthru($command, $exitCode);
exit($exitCode);
