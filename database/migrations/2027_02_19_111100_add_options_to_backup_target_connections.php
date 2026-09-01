<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111100_add_options_to_backup_target_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Providereigene Einstellungen eines Backupziels (MVP-726).
 *
 * Endpoint, Zugangsdaten und Pfad haben eigene Spalten; S3 braucht darüber
 * hinaus **Region** und **Path-Style** (MinIO adressiert Buckets im Pfad, AWS
 * über die Subdomain). Beides in vorhandene Spalten zu quetschen — Region an
 * die URL, Path-Style in die Scopes — wäre eine Falle für den Nächsten.
 *
 * Bewusst KEINE Geheimnisse hier: die gehören in `access_token`
 * (`encrypted`-Cast). Diese Spalte ist Klartext und bleibt es.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('backup_target_connections', 'options')) {
            return;
        }

        Schema::table('backup_target_connections', function (Blueprint $table): void {
            $table->json('options')->nullable()->after('root_folder_ref');
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('backup_target_connections', 'options')) {
            return;
        }

        Schema::table('backup_target_connections', function (Blueprint $table): void {
            $table->dropColumn('options');
        });
    }
};
