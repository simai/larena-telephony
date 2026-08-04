<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$forbiddenPrefixes = ['database/', 'config/', 'src/Adapters/Asterisk/', 'src/AiVoice/', 'src/Media/', 'src/RemoteConnector/'];
$command = 'git -C '.escapeshellarg($root).' ls-files --cached --others --exclude-standard';
exec($command, $files, $exitCode);
if ($exitCode !== 0) {
    $failures[] = 'unable_to_list_repository_files';
}
foreach ($files as $file) {
    foreach ($forbiddenPrefixes as $prefix) {
        if (str_starts_with($file, $prefix)) {
            $failures[] = 'forbidden_path:'.$file;
        }
    }
}

$dangerousSourcePatterns = [
    'curl_' => 'network_curl_call',
    'fsockopen(' => 'network_socket_call',
    'stream_socket_client(' => 'network_socket_call',
    'Illuminate\\Database' => 'database_runtime',
    'Asterisk' => 'asterisk_runtime',
    'ARI' => 'asterisk_media_runtime',
];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    foreach ($dangerousSourcePatterns as $pattern => $code) {
        if (str_contains($contents, $pattern)) {
            $failures[] = $code.':'.substr($file->getPathname(), strlen($root) + 1);
        }
    }
}

fwrite(STDOUT, json_encode([
    'schema' => 'larena.telephony.scope_validation.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'checked_files' => count($files),
    'failures' => array_values(array_unique($failures)),
    'nonclaims' => ['Scope validation does not authorize network, PBX, persistence, release or production operations.'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
exit($failures === [] ? 0 : 1);
