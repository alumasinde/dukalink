<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$checked = 0;

$required = [
    'app', 'config', 'database/migrations', 'public', 'routes', 'styles',
    'public/router.php', 'public/.htaccess', 'public/api.php', '.env.example',
];
foreach ($required as $path) {
    if (!file_exists($root . '/' . $path)) {
        $errors[] = 'Missing required path: ' . $path;
    }
}

$dirs = [$root . '/app', $root . '/public', $root . '/routes', $root . '/styles', $root . '/database'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $checked++;
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $errors[] = implode("\n", $output);
        }
    }
}

$forbiddenPublic = ['.env', 'composer.json', 'composer.lock', 'README.md'];
foreach ($forbiddenPublic as $file) {
    if (file_exists($root . '/public/' . $file)) {
        $errors[] = 'Sensitive file is exposed under public/: ' . $file;
    }
}

if ($errors) {
    fwrite(STDERR, "QA FAILED\n");
    foreach ($errors as $error) fwrite(STDERR, "- {$error}\n");
    exit(1);
}

echo "QA PASSED\n";
echo "PHP files syntax-checked: {$checked}\n";
echo "Required structure: OK\n";
echo "Public sensitive-file check: OK\n";
