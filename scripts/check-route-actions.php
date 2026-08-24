#!/usr/bin/env php
<?php
/*
 * check-route-actions.php
 *
 * CI-Gate (Read-only). Prüft, dass jede registrierte Route auf eine real
 * existierende Controller-Methode zeigt (Vollreview W0.4 — Fehlerklasse
 * "Route registriert, Methode fehlt" wie legacy.diary.bulk). Closures und
 * Framework-Routen werden übersprungen.
 *
 * Aufruf: php scripts/check-route-actions.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var Illuminate\Routing\Router $router */
$router = $app->make('router');

$failures = [];
$checked = 0;

foreach ($router->getRoutes()->getRoutes() as $route) {
    $action = $route->getActionName();

    if ($action === 'Closure' || ! str_contains($action, '@')) {
        // Closures sowie invokable Controller ohne @-Notation: invokables
        // prüft der Router beim Registrieren selbst (class_exists).
        continue;
    }

    [$class, $method] = explode('@', $action, 2);

    $checked++;

    if (! class_exists($class)) {
        $failures[] = sprintf('%s → Klasse %s fehlt (Route: %s)', $route->uri(), $class, $route->getName() ?? '—');
        continue;
    }

    if (! method_exists($class, $method) && ! method_exists($class, '__call')) {
        $failures[] = sprintf('%s → %s::%s() fehlt (Route: %s)', $route->uri(), $class, $method, $route->getName() ?? '—');
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Routen mit fehlender Controller-Methode:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("\n%d Fehler bei %d geprüften Routen.\n", count($failures), $checked));
    exit(1);
}

echo sprintf("OK — %d Controller-Routen geprüft, alle Methoden vorhanden.\n", $checked);
exit(0);
