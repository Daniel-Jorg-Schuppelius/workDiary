<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoFreshOrgCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Services\Demo\DemoSeederService;
use Illuminate\Console\Command;

/**
 * Erzeugt einen frischen, isolierten Demo-Mandanten (Feature 040 Nachtrag):
 * neue Organisation (nie eine bestehende), Branchenprofil + vollständige
 * Beispieldaten inkl. Anhängen und durchgespieltem Prozedurlauf. Der Mandant
 * ist über `is_demo` markiert und damit reset-/purge-fähig. Kern liegt in
 * {@see DemoSeederService::freshOrg()} (gemeinsam mit der Plattform-Admin-UI,
 * MVP-349) inkl. `demo.orgCreated`-/`demo.seeded`-Audit.
 */
class DemoFreshOrgCommand extends Command {
    protected $signature = 'demo:fresh-org {--branche= : Musterbranche (Schlüssel aus demo:seed --list)} {--showcase= : Umfang des Showcase: profile (Modul-Empfehlung des Profils, Default) oder full (alle Module)}';

    protected $description = 'Legt einen neuen, isolierten Demo-Mandanten mit Beispieldaten an (Feature 040).';

    public function handle(DemoSeederService $seeder): int {
        $industry = DemoIndustry::fromKey($this->option('branche') !== null ? (string) $this->option('branche') : null);

        $showcase = (string) ($this->option('showcase') ?? '');
        if (! in_array($showcase, ['', 'profile', 'full'], true)) {
            $this->error('--showcase erwartet profile oder full.');

            return self::FAILURE;
        }

        ['organization' => $organization, 'counts' => $counts] = $seeder->freshOrg($industry, null, null, $showcase === 'full');

        $this->info(sprintf(
            'Demo-Mandant „%s" (ID %d, Branche %s) angelegt: %d Nutzer, %d Kunden, %d Projekte, %d Anhänge, %d Prozedurlauf/-läufe.',
            $organization->name,
            $organization->id,
            $industry->label(),
            (int) $counts['users'],
            (int) $counts['customers'],
            (int) $counts['projects'],
            (int) $counts['attachments'],
            (int) $counts['procedure_runs'],
        ));
        $this->line(sprintf(
            'Funktionsumfang: %s (%d aktive Module).',
            ($counts['showcase'] ?? 'profile') === 'full' ? 'Vollumfang' : 'Modul-Empfehlung des Profils',
            (int) ($counts['modules_active'] ?? 0),
        ));
        $this->line(match ((string) ($counts['license_source'] ?? 'free')) {
            'organization' => sprintf('Lizenz: eigene Demo-Lizenz, Tarif %s (%d Tage).', (string) $counts['license_plan'], (int) config('demo.license_days', 30)),
            'installation' => sprintf('Lizenz: Installationslizenz, Tarif %s.', (string) $counts['license_plan']),
            'development' => sprintf('Lizenz: Entwicklungsumgebung, Org-Plan %s gilt ohne Lizenz.', (string) $counts['license_plan']),
            default => 'WARNUNG: keine nutzbare Lizenz — die Demo läuft im Tarif Free, die meisten Module bleiben gesperrt (Lizenz unter Admin → Lizenz ausstellen oder einspielen).',
        });
        $this->line('Demo-Zugänge: demo+01@workdiary.test … demo+06@workdiary.test (Passwort: demo-password).');
        $this->line('Zurücksetzen: php artisan demo:reset ' . $organization->id . ' — Entfernen: Admin → Organisationen → Endgültig löschen.');

        return self::SUCCESS;
    }
}
