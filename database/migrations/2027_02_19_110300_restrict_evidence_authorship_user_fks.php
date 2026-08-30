<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110300_restrict_evidence_authorship_user_fks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Zweiter Teil von Vollscan H1 / Sicherheitsscan 2026-08-23, S-08.
 *
 * Die Migration `…_101000` hat den Arbeitszeit- und Lohnnachweisen ihr
 * users-CASCADE genommen. Offen blieben die **Nachweise mit Personenbezug in
 * der Urheberschaft**: ein gelöschtes Mitglied riss weiterhin Protokolle
 * **samt Kundenunterschriften**, Dokumente, Vernichtungsnachweise,
 * Tagebucheinträge, Formularrückläufe, Arbeitsschutz-Ereignisse und Touren
 * mit sich — ohne Audit-Spur, weil die Kaskade auf DB-Ebene läuft und weder
 * der GoBD-Guard noch das Löschverbot im TimeExportService greifen.
 *
 * **RESTRICT statt SET NULL**, wie schon bei den Zeitnachweisen: ein Nachweis
 * ohne die Person, die ihn erstellt hat, ist als Nachweis wenig wert — und
 * die Spalten sind ohnehin NOT NULL. Der Austritt
 * ({@see \App\Services\Org\UserOffboardingService}) ist der vorgesehene Weg;
 * die Oberfläche weist das Löschen vorher mit klarer Meldung ab, diese FKs
 * sind das Netz darunter.
 *
 * Nur MySQL (SQLite-Dev erzwingt FKs nicht durchgängig). Der Org-Purge bleibt
 * konvergent: alle betroffenen Tabellen tragen `organization_id` und werden
 * in den Retry-Pässen vor `users` geleert.
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string, 2: string}> Tabelle, Spalte, FK-Name */
    private const EVIDENCE_FKS = [
        ['protocols', 'created_by_user_id', 'protocols_created_by_user_id_foreign'],
        ['documents', 'created_by_user_id', 'documents_created_by_user_id_foreign'],
        ['disposal_jobs', 'created_by_user_id', 'disposal_jobs_created_by_user_id_foreign'],
        ['disposal_handovers', 'created_by_user_id', 'disposal_handovers_created_by_user_id_foreign'],
        ['diary_entries', 'user_id', 'diary_entries_user_id_foreign'],
        ['form_submissions', 'submitted_by_user_id', 'form_submissions_submitted_by_user_id_foreign'],
        ['safety_events', 'reported_by_user_id', 'safety_events_reported_by_user_id_foreign'],
        ['tours', 'user_id', 'tours_user_id_foreign'],
    ];

    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::EVIDENCE_FKS as [$tableName, $column, $name]) {
            Schema::table($tableName, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->dropForeign($name);
                $blueprint->foreign($column, $name)->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::EVIDENCE_FKS as [$tableName, $column, $name]) {
            Schema::table($tableName, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->dropForeign($name);
                $blueprint->foreign($column, $name)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
