<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : phpstan-bootstrap.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Console\{Application as Artisan, Command};
use Illuminate\Contracts\Console\Kernel;

/*
 * Nur für PHPStan (bootstrapFiles in phpstan.neon), läuft nie in der Anwendung.
 *
 * Larastan (≤ 3.13) typisiert `$this->option()`/`$this->argument()` aus der
 * Befehlssignatur, findet den Befehl dafür aber über eine eigene
 * Konsolen-Application, die nur `Artisan::starting`-Registrierungen kennt.
 * Seit Laravel 13.37 hält der Kernel die aus app/Console/Commands entdeckten
 * Befehle in `$this->commands` und löst sie nur in seiner eigenen
 * Artisan-Instanz auf. Larastan sieht sie nicht mehr und fällt auf die breite
 * Union `array|string|float|int|bool|null` zurück (82 `cast.string`-Meldungen
 * nach dem Upgrade vom 2026-10-18). Deshalb werden die vom Kernel aufgelösten
 * App-Befehle hier für jede weitere Application nachregistriert. Entfällt,
 * sobald Larastan den Artisan des Kernels nutzt.
 */
$classes = [];
foreach (app(Kernel::class)->all() as $command) {
    if ($command instanceof Command && str_starts_with($command::class, 'App\\')) {
        $classes[] = $command::class;
    }
}

Artisan::starting(static function (Artisan $artisan) use ($classes): void {
    $artisan->resolveCommands($classes);
});
