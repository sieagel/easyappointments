<?php
/**
 * EsotericTrips Easy!Appointments 1.6.0 patch installer.
 *
 * Upload/extract this package into the Easy!Appointments installation directory,
 * then open this file in the browser and click INSTALL PATCH.
 *
 * The installer preserves the existing root config.php (database credentials,
 * BASE_URL, OAuth client credentials, etc.) and only adds the optional calendar
 * routing constants if they are not already present.
 *
 * Delete this file after a successful installation.
 */

declare(strict_types=1);

$root = __DIR__;
$required = [
    $root . '/application/config/config.php',
    $root . '/application/config/google.php',
    $root . '/application/controllers/Google.php',
    $root . '/application/controllers/Calendar.php',
    $root . '/application/libraries/Availability.php',
    $root . '/application/libraries/Google_sync.php',
    $root . '/application/libraries/Synchronization.php',
];

foreach ($required as $file) {
    if (!is_file($file)) {
        http_response_code(500);
        exit('This does not look like an Easy!Appointments installation directory: ' . htmlspecialchars($file));
    }
}

$files = [
    'application/config/google.php',
    'application/controllers/Google.php',
    'application/controllers/Calendar.php',
    'application/libraries/Availability.php',
    'application/libraries/Google_sync.php',
    'application/libraries/Synchronization.php',
];

function render_page(string $message = '', bool $success = false): void
{
    $color = $success ? '#167c3a' : '#333';
    echo '<!doctype html><html><head><meta charset="utf-8"><title>EsotericTrips EA Patch</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:760px;margin:60px auto;padding:0 24px;line-height:1.55}';
    echo '.box{border:1px solid #ddd;border-radius:10px;padding:24px}button{font-size:18px;padding:12px 24px;cursor:pointer}';
    echo '.msg{margin:18px 0;color:' . $color . ';white-space:pre-wrap}</style></head><body><div class="box">';
    echo '<h1>EsotericTrips Easy!Appointments patch</h1>';
    if ($message !== '') {
        echo '<div class="msg">' . htmlspecialchars($message) . '</div>';
    }
    if (!$success) {
        echo '<p>This will update only the six patched Easy!Appointments source files and preserve your existing root <code>config.php</code>.</p>';
        echo '<form method="post"><input type="hidden" name="install" value="1"><button type="submit">INSTALL PATCH</button></form>';
    } else {
        echo '<p><strong>Installation completed.</strong></p>';
        echo '<p>Now test a public online booking and verify the Google Meet link. Then delete <code>esoterictrips-patch-install.php</code>.</p>';
    }
    echo '</div></body></html>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['install'] ?? '') !== '1') {
    render_page();
    exit;
}

$patchRoot = $root . '/patch';
$copied = [];

foreach ($files as $relative) {
    $source = $patchRoot . '/' . $relative;
    $destination = $root . '/' . $relative;

    if (!is_file($source)) {
        http_response_code(500);
        exit('Missing patch file: ' . htmlspecialchars($source));
    }

    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        http_response_code(500);
        exit('Cannot create directory: ' . htmlspecialchars($dir));
    }

    if (!copy($source, $destination)) {
        http_response_code(500);
        exit('Cannot install file: ' . htmlspecialchars($relative));
    }

    $copied[] = $relative;
}

/*
 * Preserve the existing root config.php. Add only our optional routing
 * constants if they are not already defined in the Config class.
 *
 * The secondary calendar is specified by display name so the runtime patch
 * can resolve it through the authenticated Google Calendar account. This
 * avoids publishing a private calendar ID in the package.
 */
$configPath = $root . '/config.php';

if (!is_file($configPath)) {
    http_response_code(500);
    exit('Root config.php was not found. Nothing was changed in config.php.');
}

$config = file_get_contents($configPath);

$injections = [
    'GOOGLE_DEFAULT_CALENDAR' => "    // EsotericTrips: keep the provider's selected Google Calendar unless explicitly overridden.\n    // const GOOGLE_DEFAULT_CALENDAR = '';\n",
    'GOOGLE_SECONDARY_CONFLICT_CALENDAR' => "    // EsotericTrips: read-only conflict calendar. The patch resolves this by calendar name.\n    const GOOGLE_SECONDARY_CONFLICT_CALENDAR = 'Manas';\n",
    'GOOGLE_SECONDARY_CONFLICT_LOCATION_KEYWORDS' => "    // EsotericTrips: live/in-person locations that must also check the Manas calendar.\n    const GOOGLE_SECONDARY_CONFLICT_LOCATION_KEYWORDS = ['Center Manas'];\n",
];

foreach ($injections as $constant => $block) {
    if (strpos($config, 'GOOGLE_' . $constant) !== false || strpos($config, 'const ' . $constant) !== false) {
        continue;
    }

    $needle = "\n}\n";
    $pos = strrpos($config, $needle);
    if ($pos === false) {
        http_response_code(500);
        exit('Could not safely locate the end of class Config in root config.php.');
    }

    $config = substr($config, 0, $pos) . "\n" . $block . substr($config, $pos);
}

if (file_put_contents($configPath, $config) === false) {
    http_response_code(500);
    exit('Could not update root config.php.');
}

render_page("Installed files:\n- " . implode("\n- ", $copied) . "\n\nUpdated root config.php without replacing your database/OAuth settings.", true);
