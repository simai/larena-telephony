<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [$root.'/src', $root.'/tests', $root.'/scripts', $root.'/tools'];
$failures = [];
$checked = 0;
foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        ++$checked;
        $command = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1';
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $failures[] = implode("\n", $output);
        }
        $output = [];
    }
}
fwrite(STDOUT, json_encode([
    'schema' => 'larena.telephony.lint.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'checked_files' => $checked,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
exit($failures === [] ? 0 : 1);
