<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$forbiddenPrefixes = [
    'database/',
    'config/',
    'src/Adapters/AsteriskAmi/Network/',
    'src/AiVoice/',
    'src/Media/',
    'src/Recording/',
    'src/RemoteConnector/',
    'src/Transcription/',
];
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
    'socket_create(' => 'network_socket_call',
    'new PDO(' => 'database_runtime',
    'mysqli_' => 'database_runtime',
    'Illuminate\\Database' => 'database_runtime',
    'ARI' => 'asterisk_media_runtime',
    'RTP' => 'asterisk_media_runtime',
    'WebRTC' => 'asterisk_media_runtime',
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

$launchPath = $root.'/.larena/launch-context.json';
if (is_file($launchPath)) {
    $launch = json_decode((string) file_get_contents($launchPath), true, 512, JSON_THROW_ON_ERROR);
    $base = $launch['base_commit'] ?? null;
    $allowedPaths = $launch['allowed_paths'] ?? [];
    if (is_string($base) && preg_match('/^[0-9a-f]{40}$/', $base) === 1 && is_array($allowedPaths)) {
        $changed = [];
        exec('git -C '.escapeshellarg($root).' diff --name-only '.escapeshellarg($base), $changed, $diffExit);
        $untracked = [];
        exec('git -C '.escapeshellarg($root).' ls-files --others --exclude-standard', $untracked, $untrackedExit);
        if ($diffExit !== 0 || $untrackedExit !== 0) {
            $failures[] = 'unable_to_resolve_launch_change_boundary';
        } else {
            foreach (array_values(array_unique([...$changed, ...$untracked])) as $changedPath) {
                $matched = false;
                foreach ($allowedPaths as $allowedPath) {
                    if (!is_string($allowedPath)) {
                        continue;
                    }
                    if (str_ends_with($allowedPath, '/**')) {
                        $matched = str_starts_with($changedPath, substr($allowedPath, 0, -2));
                    } else {
                        $matched = fnmatch($allowedPath, $changedPath, FNM_PATHNAME);
                    }
                    if ($matched) {
                        break;
                    }
                }
                if (!$matched) {
                    $failures[] = 'outside_launch_write_boundary:'.$changedPath;
                }
            }
        }
    } else {
        $failures[] = 'invalid_launch_change_boundary';
    }
}

$adapterRoot = $root.'/src/Adapters/AsteriskAmi';
if (is_dir($adapterRoot)) {
    $adapterIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adapterRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($adapterIterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Contracts/')) {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (str_contains($contents, 'implements AmiTransport')) {
            $failures[] = 'non_fixture_ami_transport_implementation:'.substr($file->getPathname(), strlen($root) + 1);
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
