<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoShowcaseSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Demo;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Support\Collection;

/**
 * Vorführszenarien des Demo-Mandanten ohne Fachdienst (Krisenübung,
 * Phase-38-Basics, Cloud-Eingang); Szenarien mit Fachdienst liefern die
 * Demo-Blöcke der Module (Welle 4.1). Aus dem DemoSeederService
 * extrahiert (Refactoring Welle 2, B6b); wird ausschließlich innerhalb
 * dessen Seed-Transaktion aufgerufen. Alle Szenarien sind robust:
 * Fehler (z. B. deaktivierte Module) brechen den Gesamt-Seed nicht ab.
 */
class DemoShowcaseSeeder {
    /**
     * Aktiver Modulumfang der Demo-Organisation (MVP-838): null = alle
     * Module („Vollumfang"), sonst die Modul-Empfehlung des Branchenprofils.
     * Blöcke inaktiver Module werden übersprungen — die Demo zeigt, was das
     * Profil empfiehlt, nicht den ganzen Katalog.
     *
     * @var list<string>|null
     */
    private ?array $activeModules = null;

    /** @param list<string>|null $modules */
    public function withActiveModules(?array $modules): static {
        $this->activeModules = $modules;

        return $this;
    }

    private function moduleActive(string $code): bool {
        return $this->activeModules === null || in_array($code, $this->activeModules, true);
    }

    /** Demo Feature 070: geplante Krisenübung (Playbook-Verbesserung). */
    public function seedCrisisExercise(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.crisis_management')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            \App\Models\Crisis\CrisisExercise::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Stabsübung IT-Ausfall (Demo)'),
                'scenario' => (string) __('Zentraler Server fällt aus; Wiederanlauf nach Playbook, Kommunikation an Kunden binnen 4 Stunden.'),
                'next_due_on' => \Carbon\Carbon::now()->addDays(21)->toDateString(),
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Krisenübung übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Phase-38-Pakete ohne Fachdienst (Vollaudit 2026-07, N23): Urlaubsanspruch
     * mit Übertrag und Führerscheinkontrolle; Kassenbuch sowie Abrechnungsplan
     * und Rabatt/Skonto-Rechnung liefern die Demo-Blöcke von Finanzen und Faktura. Jeder Block ist einzeln robust — ein Fehler
     * (z. B. deaktiviertes Modul) bricht den Gesamt-Seed nicht ab.
     *
     * @param  Collection<int, User>  $users
     */
    public function seedPhase38Basics(Organization $organization, Customer $customer, Collection $users): int {
        /** @var User|null $actor */
        $actor = $users->first();
        if ($actor === null) {
            return 0;
        }
        $count = 0;

        // 1) Urlaubsanspruch mit Übertrag aus dem Vorjahr (Verfall 31.03.).
        try {
            \App\Models\Absence\VacationEntitlement::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'user_id' => $actor->id,
                'year' => (int) \Illuminate\Support\Carbon::now()->year,
            ], [
                'entitled_days' => 30,
                'carryover_days' => 5,
                'carryover_expires_on' => \Illuminate\Support\Carbon::now()->startOfYear()->addMonths(3)->subDay()->toDateString(),
                'note' => (string) __('Demo: Resturlaub aus dem Vorjahr, verfällt zum 31.03.'),
            ]);
            $count++;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Urlaubsanspruch übersprungen: ' . $e->getMessage());
        }

        // 5) Führerscheinkontrolle (Fuhrpark, Halterhaftung).
        if ($this->moduleActive('module.fuhrpark')) {
            try {
                /** @var User $driver */
                $driver = $users->skip(1)->first() ?? $actor;
                \App\Models\Fleet\DriverLicenseCheck::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'user_id' => $driver->id,
                ], [
                    'checked_by' => $actor->id,
                    'checked_at' => \Illuminate\Support\Carbon::now()->toDateString(),
                    'license_classes' => 'B, BE',
                    'license_valid_until' => \Illuminate\Support\Carbon::now()->addYears(3)->toDateString(),
                    'next_due_on' => \Illuminate\Support\Carbon::now()->addMonths(6)->toDateString(),
                    'note' => (string) __('Demo: Sichtkontrolle Original-Führerschein.'),
                ]);
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('Demo-Seeder: Führerscheinkontrolle übersprungen: ' . $e->getMessage());
            }
        }

        return $count;
    }
    /**
     * Demo Feature 080 (P9): Cloud-Dokumenteingang mit Ordnerregel und einem
     * Importprotokoll, das den REALEN Mischfall zeigt — übernommen, in der
     * Zuordnungs-Inbox, Dublette und abgewiesen. Erst dieser Mischfall macht
     * den Importbericht in einer Demo lesbar; eine Liste aus lauter Erfolgen
     * beantwortet keine Frage.
     *
     * Bewusst OHNE echte Verbindung: `status = draft`, kein Token, keine
     * external_account_id — nichts läuft an, der Runner überspringt sie.
     */
    public function seedCloudIntake(Organization $organization, ?User $actor): int {
        if (! $this->moduleActive('module.documents')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $connection = \App\Models\CloudIntake\CloudDocumentConnection::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'provider' => \App\Enums\CloudIntake\CloudIntakeProvider::Google,
                'root_folder_path' => '/Belege',
            ], [
                'container_id' => 'my-drive',
                // Der Drive-Adapter benennt „Meine Ablage" ebenfalls unübersetzt (Google-Begriff).
                'container_label' => 'Meine Ablage',
                'status' => \App\Enums\CloudIntake\CloudIntakeConnectionStatus::Draft,
                'created_by_user_id' => $actor->id,
                'last_run_at' => \Illuminate\Support\Carbon::now()->subHours(3),
            ]);

            \App\Models\CloudIntake\CloudDocumentRoute::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'connection_id' => $connection->id,
                'path_pattern' => 'Eingangsrechnungen/**',
            ], [
                'priority' => 10,
                'allowed_extensions' => ['pdf', 'xml'],
                'target' => \App\Enums\CloudIntake\CloudIntakeRouteTarget::IncomingInvoice,
                'auto_version' => false,
                'active' => true,
            ]);

            $items = [
                ['re-2026-0912.pdf', \App\Enums\CloudIntake\CloudIntakeItemStatus::Imported, null, 2],
                ['re-2026-0913.pdf', \App\Enums\CloudIntake\CloudIntakeItemStatus::Imported, null, 2],
                ['unbekannter-lieferant.pdf', \App\Enums\CloudIntake\CloudIntakeItemStatus::Inbox, 'supplier_unmatched', 1],
                ['re-2026-0912.pdf', \App\Enums\CloudIntake\CloudIntakeItemStatus::Duplicate, 'sha256_match', 1],
                ['angebot.zip', \App\Enums\CloudIntake\CloudIntakeItemStatus::Rejected, 'blocked_extension', 0],
            ];

            $count = 0;
            foreach ($items as $index => [$name, $status, $reason, $daysAgo]) {
                $item = \App\Models\CloudIntake\CloudDocumentItem::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'connection_id' => $connection->id,
                    'external_item_id' => 'demo-' . $index,
                    'revision' => 'rev-1',
                ], [
                    'provider' => \App\Enums\CloudIntake\CloudIntakeProvider::Google,
                    'source_path' => 'Eingangsrechnungen/' . $name,
                    'status' => $status,
                    'status_reason' => $reason,
                    'target' => \App\Enums\CloudIntake\CloudIntakeRouteTarget::IncomingInvoice,
                ]);
                $item->forceFill(['created_at' => \Illuminate\Support\Carbon::now()->subDays($daysAgo)])->save();
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Cloud-Dokumenteingang übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
    /**
     * Lokale Buchhaltung (Feature 125, MVP-678): ein kleiner, aber
     * vollständiger Durchstich — Profil, Geschäftsjahr, Kontenplan,
     * Buchungsregeln und eine festgeschriebene Erlösbuchung mit offenem Posten.
     *
     * Bewusst OHNE Periodenabschluss: Eine Demo, in der man nichts mehr buchen
     * kann, beantwortet keine Frage.
     */
}
