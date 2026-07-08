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
use App\Models\{AuditLog, Organization};
use App\Services\Demo\DemoSeederService;
use Illuminate\Console\Command;

/**
 * Erzeugt einen frischen, isolierten Demo-Mandanten (Feature 040 Nachtrag):
 * neue Organisation (nie eine bestehende), Branchenprofil + vollständige
 * Beispieldaten inkl. Anhängen und durchgespieltem Prozedurlauf. Der Mandant
 * ist über `is_demo` markiert und damit reset-/purge-fähig.
 */
class DemoFreshOrgCommand extends Command {
    protected $signature = 'demo:fresh-org {--branche= : Musterbranche (it-service|elektro|facility)}';

    protected $description = 'Legt einen neuen, isolierten Demo-Mandanten mit Beispieldaten an (Feature 040).';

    public function handle(DemoSeederService $seeder): int {
        $industry = DemoIndustry::fromKey($this->option('branche') !== null ? (string) $this->option('branche') : null);

        // Eindeutiger Name — nie Kollision mit bestehenden (echten) Orgs.
        $base = 'Demo ' . $industry->label();
        $name = $base;
        $suffix = 2;
        while (Organization::query()->where('name', $name)->orWhere('name', $name . ' (Demo)')->exists()) {
            $name = $base . ' #' . $suffix++;
        }

        $organization = Organization::query()->create([
            'name' => $name,
            'plan' => 'enterprise',
            'locale' => 'de',
            'timezone' => config('app.timezone', 'Europe/Berlin'),
            'is_active' => true,
        ]);

        $counts = $seeder->seed($organization, null, $industry);

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'event' => 'demo.seeded',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => $counts,
        ]);

        $this->info(sprintf(
            'Demo-Mandant „%s" (ID %d, Branche %s) angelegt: %d Nutzer, %d Kunden, %d Projekte, %d Anhänge, %d Prozedurlauf/-läufe.',
            $organization->refresh()->name,
            $organization->id,
            $industry->label(),
            (int) $counts['users'],
            (int) $counts['customers'],
            (int) $counts['projects'],
            (int) $counts['attachments'],
            (int) $counts['procedure_runs'],
        ));
        $this->line('Demo-Zugänge: demo+01@workdiary.test … demo+06@workdiary.test (Passwort: demo-password).');
        $this->line('Zurücksetzen: php artisan demo:reset ' . $organization->id . ' — Entfernen: Admin → Organisationen → Endgültig löschen.');

        return self::SUCCESS;
    }
}
