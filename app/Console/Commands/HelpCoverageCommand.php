<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpCoverageCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Hilfe-Abdeckungsprüfung (Feature 039): meldet Seiten ohne Hilfe-Topic,
 * registrierte Topics ohne Quelldatei und fehlende Übersetzungen gegenüber
 * der Leitsprache de — CI-tauglich (Exit 1 bei Befunden).
 *
 * Composer: `composer help:coverage` (Teil von `composer qa`).
 * Bewusste Ausnahmen (öffentliche Seiten ohne App-Layout, die Hilfeseite
 * selbst) stehen in config/help-topics.php unter `coverage_exceptions`.
 */
class HelpCoverageCommand extends Command {
    protected $signature = 'help:coverage';

    protected $description = 'Prüft Seiten auf fehlende Hilfe-Topics und Hilfe-Topics auf fehlende Übersetzungen.';

    public function handle(): int {
        $problems = 0;
        $problems += $this->reportUnmappedPages();
        $problems += $this->reportTopicsWithoutSource();
        $problems += $this->reportMissingTranslations();

        if ($problems === 0) {
            $this->info('Hilfe-Abdeckung vollständig: alle Seiten gemappt, alle Topics in allen Sprachen vorhanden.');

            return self::SUCCESS;
        }

        $this->error(sprintf('Hilfe-Abdeckung unvollständig: %d Befund(e).', $problems));

        return self::FAILURE;
    }

    /** Seiten-Routen (App-Layout) ohne Eintrag in der Topic-Registry. */
    private function reportUnmappedPages(): int {
        /** @var array<string, string> $map */
        $map = (array) config('help-topics.routes', []);
        /** @var list<string> $exceptions */
        $exceptions = (array) config('help-topics.coverage_exceptions', []);

        $unmapped = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '' || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! $this->isAppPage($name, $route->uri())) {
                continue;
            }
            if ($this->matchesAny($name, array_merge(array_keys($map), $exceptions))) {
                continue;
            }
            $unmapped[] = $name;
        }

        sort($unmapped);
        foreach ($unmapped as $name) {
            $this->line("Seite ohne Hilfe-Topic: {$name}");
        }

        return count($unmapped);
    }

    /** Registrierte Topics, deren Leitsprachen-Datei (de) fehlt. */
    private function reportTopicsWithoutSource(): int {
        /** @var array<string, string> $map */
        $map = (array) config('help-topics.routes', []);

        $missing = [];
        foreach (array_unique(array_values($map)) as $topic) {
            if ($topic === '') {
                continue;
            }
            if (! is_file(resource_path("help/de/{$topic}.md"))) {
                $missing[] = $topic;
            }
        }

        sort($missing);
        foreach ($missing as $topic) {
            $this->line("Registriertes Topic ohne de-Datei: {$topic}");
        }

        return count($missing);
    }

    /** Fehlende Übersetzungen: jedes de-Topic braucht alle aktiven Sprachen. */
    private function reportMissingTranslations(): int {
        /** @var list<string> $locales */
        $locales = array_values(array_filter((array) config('app.available_locales', ['de', 'en'])));

        $source = collect(glob(resource_path('help/de/*.md')) ?: [])
            ->map(fn(string $path): string => basename($path, '.md'));

        $count = 0;
        foreach ($locales as $locale) {
            if ($locale === 'de') {
                continue;
            }
            foreach ($source as $topic) {
                if (! is_file(resource_path("help/{$locale}/{$topic}.md"))) {
                    $this->line("Fehlende Übersetzung ({$locale}): {$topic}");
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Heuristik „Seite im App-Layout": benannte GET-Route, kein API-/
     * Portal-/Auth-Endpunkt, und eine Haupt-Ansicht (index/show/board/
     * dashboard bzw. Top-Level-Name).
     */
    private function isAppPage(string $name, string $uri): bool {
        foreach (['api/', 'scim/', 'webhook', 'oauth', '_debugbar'] as $segment) {
            if (str_contains($uri, $segment)) {
                return false;
            }
        }
        foreach (['api.', 'customer.', 'sanctum.', 'ignition.', 'livewire.', 'storage.', 'password.', 'login', 'logout', 'verification.', 'two-factor'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return false;
            }
        }

        return Str::endsWith($name, ['.index', '.show', '.board', '.dashboard']) || ! str_contains($name, '.');
    }

    /** @param list<string> $patterns */
    private function matchesAny(string $name, array $patterns): bool {
        if (in_array($name, $patterns, true)) {
            return true;
        }
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
