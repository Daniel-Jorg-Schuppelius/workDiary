<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_260200_add_route_test_and_start_confirmation_to_cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Cloud-Dokumenteingang (MVP-1613): Ordnerregeln speichern ihren Testpfad,
 * eine Verbindung importiert erst nach bestätigter Erstlaufvorschau.
 * Verbindungen, die schon liefen oder aktiv waren, gelten als bestätigt —
 * sonst stünde ein laufender Eingang nach dem Update still.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cloud_document_routes', function (Blueprint $table): void {
            $table->string('test_path', 1024)->nullable()->after('target_ref_id');
            $table->timestamp('tested_at')->nullable()->after('test_path');
        });

        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->timestamp('start_confirmed_at')->nullable()->after('last_run_at');
        });

        DB::table('cloud_document_connections')
            ->whereNull('start_confirmed_at')
            ->where(fn ($q) => $q->whereNotNull('last_run_at')->orWhere('status', '!=', 'draft'))
            ->update(['start_confirmed_at' => DB::raw('COALESCE(last_run_at, updated_at, created_at)')]);
    }

    public function down(): void {
        Schema::table('cloud_document_connections', function (Blueprint $table): void {
            $table->dropColumn('start_confirmed_at');
        });

        Schema::table('cloud_document_routes', function (Blueprint $table): void {
            $table->dropColumn(['test_path', 'tested_at']);
        });
    }
};
