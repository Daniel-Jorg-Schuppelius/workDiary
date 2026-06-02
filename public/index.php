<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Ensure Composer dependencies are installed before booting Laravel...
if (! is_file(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 300');

    echo <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkDiary &ndash; Installation erforderlich</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; max-width: 40rem; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        h1 { font-size: 1.4rem; margin: 0 0 0.75rem; }
        p { line-height: 1.6; margin: 0 0 1rem; }
        code, pre { font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace; }
        pre { background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; padding: 1rem; overflow-x: auto; color: #93c5fd; }
        .muted { color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Abhängigkeiten fehlen</h1>
        <p>Die Composer-Abhängigkeiten wurden noch nicht installiert. Bitte führe im Projektverzeichnis folgenden Befehl aus:</p>
        <pre>composer install --no-dev --optimize-autoloader</pre>
        <p>Für die Frontend-Assets zusätzlich:</p>
        <pre>npm ci &amp;&amp; npm run build</pre>
        <p class="muted">Danach diese Seite neu laden. Anschließend führt der Web-Installer unter <code>/install</code> durch die Einrichtung.</p>
    </div>
</body>
</html>
HTML;

    exit;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';


// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
