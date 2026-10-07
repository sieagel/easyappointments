<?php
declare(strict_types=1);

/**
 * EsotericTrips Easy!Appointments patch installer.
 *
 * Upload/extract this package into the Easy!Appointments installation root,
 * then open this file and click INSTALL PATCH.
 *
 * The installer copies only files contained in ./patch and never replaces
 * the root config.php or database/OAuth credentials.
 *
 * DELETE this file and the patch directory after successful installation.
 */

$root = __DIR__;
$patchRoot = $root . '/patch';

if (!is_dir($patchRoot)) {
    http_response_code(500);
    exit('Patch directory not found. Extract the complete patch ZIP into the Easy!Appointments installation root.');
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($patchRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $fullPath = $file->getPathname();
    $relative = ltrim(str_replace($patchRoot, '', $fullPath), DIRECTORY_SEPARATOR);
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    $files[] = $relative;
}

sort($files);

function page(string $message = '', bool $success = false): void
{
    $color = $success ? '#167c3a' : '#333';
    echo '<!doctype html><html><head><meta charset="utf-8"><title>EsotericTrips EA Patch</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:820px;margin:60px auto;padding:0 24px;line-height:1.55}';
    echo '.box{border:1px solid #ddd;border-radius:10px;padding:24px}button{font-size:18px;padding:12px 24px;cursor:pointer}';
    echo '.msg{margin:18px 0;color:' . $color . ';white-space:pre-wrap}</style></head><body><div class="box">';
    echo '<h1>EsotericTrips Easy!Appointments patch</h1>';

    if ($message !== '') {
        echo '<div class="msg">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    if (!$success) {
        echo '<p>This will copy the patched application, language and JavaScript files into the current Easy!Appointments installation.</p>';
        echo '<p><strong>Your root config.php, database credentials and OAuth credentials are not replaced.</strong></p>';
        echo '<form method="post"><input type="hidden" name="install" value="1"><button type="submit">INSTALL PATCH</button></form>';
    } else {
        echo '<p><strong>Installation completed.</strong></p>';
        echo '<p>Test the Google Calendar settings, an online booking, Google Meet generation and calendar conflict checking.</p>';
        echo '<p>Then delete <code>esoterictrips-patch-install.php</code> and the <code>patch</code> directory.</p>';
    }

    echo '</div></body></html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['install'] ?? '') !== '1') {
    page();
    exit;
}

$copied = [];

foreach ($files as $relative) {
    $source = $patchRoot . '/' . $relative;
    $destination = $root . '/' . $relative;
    $directory = dirname($destination);

    if (!is_file($source)) {
        http_response_code(500);
        exit('Missing patch file: ' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'));
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        http_response_code(500);
        exit('Cannot create directory: ' . htmlspecialchars($directory, ENT_QUOTES, 'UTF-8'));
    }

    if (!copy($source, $destination)) {
        http_response_code(500);
        exit('Cannot install file: ' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'));
    }

    $copied[] = $relative;
}

page("Installed files:\n- " . implode("\n- ", $copied), true);
