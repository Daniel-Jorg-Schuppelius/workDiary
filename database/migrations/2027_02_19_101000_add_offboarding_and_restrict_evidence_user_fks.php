<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Mitarbeiter-Austritt (Feature 126, MVP-689 — Vollscan H1, Entscheid E4):
 * `users.left_at` trägt das fachliche Austrittsdatum (Deaktivierung vollzieht
 * der UserOffboardingService). Zugleich verlieren die Arbeitszeit-/Lohn-
 * Nachweistabellen ihr users-CASCADE (ArbZG § 16 Abs. 2 / MiLoG § 17: 2 Jahre,
 * GoBD 10 Jahre) — ein User-Hard-Delete darf Nachweise nie mitreißen.
 * RESTRICT statt SET NULL: der Nachweis braucht den Personenbezug; der
 * Austritts-Workflow ist der vorgesehene Weg. Nur MySQL (SQLite-Dev erzwingt
 * FKs nicht durchgängig); der Org-Purge bleibt konvergent (Retry-Pässe).
 */
return new class extends Migration {
    /** @var list<array{0: string, 1: string, 2: string}> Tabelle, Spalte, FK-Name */
    private const EVIDENCE_FKS = [
        ['attendances', 'user_id', 'attendances_user_id_foreign'],
        ['time_entries', 'user_id', 'time_entries_user_id_foreign'],
        ['timesheets', 'user_id', 'timesheets_user_id_foreign'],
        ['time_export_lines', 'user_id', 'time_export_lines_user_id_foreign'],
        ['month_closures', 'user_id', 'month_closures_user_id_foreign'],
        ['day_closures', 'user_id', 'day_closures_user_id_foreign'],
        ['time_account_entries', 'user_id', 'tacce_user_fk'],
        ['time_account_balances', 'user_id', 'taccb_user_fk'],
        ['flex_balances', 'user_id', 'flex_balances_user_id_foreign'],
        ['overtime_requests', 'user_id', 'overtime_requests_user_id_foreign'],
        ['time_correction_requests', 'user_id', 'time_correction_requests_user_id_foreign'],
        ['vacations', 'user_id', 'vacations_user_id_foreign'],
        ['vacation_entitlements', 'user_id', 'vacation_entitlements_user_id_foreign'],
        ['sick_leaves', 'user_id', 'sick_leaves_user_id_foreign'],
        ['expenses', 'user_id', 'expenses_user_id_foreign'],
        ['per_diem_trips', 'user_id', 'per_diem_trips_user_id_foreign'],
        ['travel_logs', 'user_id', 'travel_logs_user_id_foreign'],
        ['external_wage_items', 'user_id', 'external_wage_items_user_id_foreign'],
        ['work_schedules', 'user_id', 'work_schedules_user_id_foreign'],
    ];

    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('left_at')->nullable()->after('deactivated_at');
        });

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
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('left_at');
        });

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
