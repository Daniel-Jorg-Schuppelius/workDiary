<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110600_add_connection_health_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 067, P4 (MVP-178): Standard-Gesundheitsspalten für
 * Konnektor-Tabellen, die bisher nur `active` + last_*-Zeitstempel
 * hatten (Befund MVP-057) — Grundlage für Ablauf-/Störungswarnungen.
 * chat_webhooks/todoist_connections haben bereits eigene Felder.
 */
return new class extends Migration {
    private const TABLES = [
        'email_connections',
        'cti_connections',
        'carrier_connections',
        'caldav_connections',
        'webdav_connections',
    ];

    public function up(): void {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'consecutive_failures')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('last_error', 300)->nullable();
                $blueprint->timestamp('last_error_at')->nullable();
                $blueprint->unsignedInteger('consecutive_failures')->default(0);
                $blueprint->timestamp('disabled_at')->nullable();
            });
        }
    }

    public function down(): void {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'consecutive_failures')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['last_error', 'last_error_at', 'consecutive_failures', 'disabled_at']);
            });
        }
    }
};
