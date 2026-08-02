<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_14_100000_harden_plugin_error_store.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Fehlerspeicher-Härtung (Plugin-System-Review 2026-08, W2):
 *
 *  - plugin_errors: Dedup-Spalten (error_hash/occurrences/last_occurred_at)
 *    + Indizes für Inbox-Defaultabfrage und Org-Filter (C3, D11).
 *  - plugin_states: Zeitfenster für den Auto-Disable (C1) und Health-Metadaten
 *    (Latenz/Code/Hysterese, W3a).
 *  - Globale Zeilen: `unique(plugin_id, organization_id)` greift bei NULL nicht
 *    (C2). Statt Sentinel 0 — der den FK auf organizations verletzen würde
 *    (E-3, angepasst) — schließt ein partieller Unique-Index das Loch;
 *    vorher werden gesplittete NULL-Zeilen zusammengeführt (Counter summiert).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('plugin_errors', function (Blueprint $table): void {
            $table->string('error_hash', 64)->nullable()->after('context');
            $table->unsignedInteger('occurrences')->default(1)->after('error_hash');
            $table->timestamp('last_occurred_at')->nullable()->after('occurred_at');

            $table->index('error_hash');
            $table->index(['acknowledged_at', 'occurred_at']);
            $table->index(['plugin_id', 'organization_id', 'occurred_at']);
        });
        DB::table('plugin_errors')->whereNull('last_occurred_at')->update([
            'last_occurred_at' => DB::raw('occurred_at'),
        ]);

        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->timestamp('failure_window_started_at')->nullable()->after('failure_count');
            $table->unsignedInteger('last_health_latency_ms')->nullable()->after('last_health_message');
            $table->string('last_health_code', 64)->nullable()->after('last_health_latency_ms');
            $table->string('last_announced_status', 16)->nullable()->after('last_health_code');
            $table->unsignedInteger('health_streak')->default(0)->after('last_announced_status');
        });

        $this->mergeSplitGlobalRows();

        // Partieller Unique-Index (SQLite/PostgreSQL): genau eine globale Zeile
        // je Plugin. Auf Treibern ohne Partial-Index-Support übernimmt der
        // Retry-Schreibpfad des Recorders die Konfliktauflösung.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS plugin_states_global_unique ON plugin_states (plugin_id) WHERE organization_id IS NULL');
        }
    }

    public function down(): void {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS plugin_states_global_unique');
        }
        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->dropColumn(['failure_window_started_at', 'last_health_latency_ms', 'last_health_code', 'last_announced_status', 'health_streak']);
        });
        Schema::table('plugin_errors', function (Blueprint $table): void {
            $table->dropIndex(['error_hash']);
            $table->dropIndex(['acknowledged_at', 'occurred_at']);
            $table->dropIndex(['plugin_id', 'organization_id', 'occurred_at']);
            $table->dropColumn(['error_hash', 'occurrences', 'last_occurred_at']);
        });
    }

    /** Gesplittete globale Zeilen (mehrere NULL-Org-Zeilen je Plugin) zusammenführen. */
    private function mergeSplitGlobalRows(): void {
        $groups = DB::table('plugin_states')
            ->whereNull('organization_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('plugin_id');

        foreach ($groups as $rows) {
            if ($rows->count() < 2) {
                continue;
            }
            $keep = $rows->first();
            $totalFailures = (int) $rows->sum('failure_count');
            $reason = $rows->pluck('disabled_reason')->filter()->first();

            DB::table('plugin_states')->where('id', $keep->id)->update([
                'failure_count' => $totalFailures,
                'disabled_reason' => $keep->disabled_reason ?? $reason,
            ]);
            DB::table('plugin_states')
                ->whereIn('id', $rows->skip(1)->pluck('id'))
                ->delete();
        }
    }
};
