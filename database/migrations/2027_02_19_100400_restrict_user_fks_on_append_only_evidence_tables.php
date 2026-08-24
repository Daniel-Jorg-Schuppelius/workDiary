<?php
/*
 * Created on   : Sun Aug 23 2026
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
 * Vollscan 2026-08-23, F4 (+E4): ON DELETE CASCADE von users auf append-only
 * Nachweis-Tabellen löschte beim User-Delete den NACHWEIS mit — der
 * AppendOnly-Guard wirkt nur auf Eloquent-Events, nicht auf FK-Kaskaden.
 * RESTRICT passt zur E4-Entscheidung (Austritts-Workflow statt Hard-Delete);
 * der Org-Purge bleibt konvergent (Events hängen per CASCADE an ihrem
 * org-gebundenen Parent und sind vor dem users-Pass weg). Nur MySQL/MariaDB —
 * SQLite (Dev) erzwingt FKs ohnehin nicht durchgängig.
 */
return new class extends Migration {
    private const FKS = [
        ['protocol_events', 'actor_user_id', 'protocol_events_actor_user_id_foreign'],
        ['month_closure_events', 'actor_user_id', 'month_closure_events_actor_user_id_foreign'],
        ['disposal_job_events', 'actor_user_id', 'disposal_job_events_actor_user_id_foreign'],
        ['document_versions', 'uploaded_by_user_id', 'document_versions_uploaded_by_user_id_foreign'],
    ];

    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::FKS as [$table, $column, $name]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->dropForeign($name);
                $blueprint->foreign($column, $name)->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::FKS as [$table, $column, $name]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->dropForeign($name);
                $blueprint->foreign($column, $name)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
