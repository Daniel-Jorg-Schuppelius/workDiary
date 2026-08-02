<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HealthCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginHealth, PluginHealthService, PluginManager};
use App\Support\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dünner Aufrufer der zentralen Health-Pipeline ({@see PluginHealthService},
 * Review 2026-08, W3): iteriert Plugins × Organisationen fehlertolerant,
 * überspringt deaktivierte Plugins und meldet am Ende eine Zusammenfassung —
 * damit `--no-fail` keine halb gelaufenen Checks maskiert (D4).
 */
class HealthCheckCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'plugin:healthcheck
        {plugin? : Plugin-ID. Ohne Argument: alle aktiven Plugins.}
        {--organization= : ID oder Slug einer einzelnen Organisation, sonst alle aktiven}
        {--no-fail : Auch bei ungesunden Plugins mit Exit 0 beenden (für geplante Läufe — Ergebnis wird trotzdem aufgezeichnet).}
        {--fail-on-incomplete : Exit 1, wenn Organisationen wegen Iterationsfehlern übersprungen wurden (Monitoring).}';

    protected $description = 'Führt Healthchecks für ein oder alle Plugins durch und persistiert das Ergebnis.';

    private int $checked = 0;

    private int $skipped = 0;

    private int $unhealthy = 0;

    public function handle(PluginManager $manager, PluginHealthService $service): int {
        $target = $this->argument('plugin');

        $plugins = $target !== null
            ? collect([$manager->find((string) $target)])->filter()
            : $manager->all();

        if ($plugins->isEmpty()) {
            $this->error('Plugin nicht gefunden: ' . (string) $target);

            return self::FAILURE;
        }

        // Per-Plugin-Opt-out (W3c) — nur für den Sammel-Lauf, ein explizit
        // angefragtes Plugin wird immer geprüft.
        $exclude = $target === null ? (array) config('plugins.health_exclude', []) : [];

        $iterationFailures = 0;
        foreach ($plugins as $plugin) {
            if (in_array($plugin->id(), $exclude, true)) {
                $this->skipped++;

                continue;
            }
            if ($plugin->isPerOrganization()) {
                // Per-Org-Plugin: je aktiver Organisation mit gebundenem Kontext
                // prüfen — fehlertolerant, eine kaputte Org bricht nicht den
                // Gesamtlauf ab (D2). Inaktive Organisationen werden übersprungen.
                $iterationFailures += $this->forEachOrganization(
                    function (Organization $org) use ($plugin, $service): void {
                        if (! $plugin->isEnabled()) {
                            $this->skipped++;

                            return; // in dieser Org nicht aktiv → kein Check
                        }
                        $this->checkOne($plugin, (int) $org->id, (string) $org->name, $service);
                    },
                    scope: fn($query) => $query->where('is_active', true),
                );
            } else {
                // Globales Plugin: einmalig ohne Org-Kontext (mit Restore, A13);
                // deaktivierte werden wie im Per-Org-Zweig übersprungen (A7).
                try {
                    OrganizationContext::runWithout(function () use ($plugin, $service): void {
                        if (! $plugin->isEnabled()) {
                            $this->skipped++;

                            return;
                        }
                        $this->checkOne($plugin, null, null, $service);
                    });
                } catch (Throwable $e) {
                    $iterationFailures++;
                    $this->error(sprintf('%s: Abbruch — %s', $plugin->id(), $e->getMessage()));
                }
            }
        }

        $this->line(sprintf(
            'Zusammenfassung: %d geprüft, %d übersprungen, %d ungesund, %d Iterationsfehler.',
            $this->checked,
            $this->skipped,
            $this->unhealthy,
            $iterationFailures,
        ));
        if ($iterationFailures > 0) {
            $this->warn('Achtung: Teile des Laufs wurden wegen Fehlern übersprungen — Ergebnisse sind unvollständig.');
        }

        if ($this->option('fail-on-incomplete') && $iterationFailures > 0) {
            return self::FAILURE;
        }
        // Bei `--no-fail` (geplante Läufe) zählt nur, dass die Checks liefen — ein ungesundes Plugin ist
        // ein erfasster Zustand, kein Kommando-Fehlschlag (kein irreführender „failed"; Auto-Disable bleibt).
        if ($this->option('no-fail')) {
            return self::SUCCESS;
        }

        return $this->unhealthy > 0 || $iterationFailures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkOne(Plugin $plugin, ?int $organizationId, ?string $orgName, PluginHealthService $service): void {
        $label = $plugin->id() . ($orgName !== null ? " [{$orgName}]" : '');
        $this->checked++;

        try {
            $result = $service->check($plugin, $organizationId);
        } catch (Throwable $e) {
            // Die Pipeline fängt Plugin-Fehler selbst; hier landen nur noch
            // Infrastruktur-Fehler (DB weg o. Ä.) — zählen als ungesund.
            $this->unhealthy++;
            $this->error(sprintf('  ✗ %s: pipeline-Fehler — %s', $label, $e->getMessage()));

            return;
        }

        $health = $result['health'];
        match ($health->status) {
            PluginHealth::STATUS_OK => $this->line(sprintf('  ✓ %s: ok %s (%dms)', $label, $health->message, (int) $health->latencyMs)),
            PluginHealth::STATUS_DEGRADED => $this->line(sprintf('  ~ %s: degraded — %s (%dms)', $label, $health->message, (int) $health->latencyMs)),
            default => $this->warn(sprintf('  ✗ %s: failing — %s (%dms)', $label, $health->message, (int) $health->latencyMs)),
        };
        if ($health->isFailing()) {
            $this->unhealthy++;
        }
    }
}
