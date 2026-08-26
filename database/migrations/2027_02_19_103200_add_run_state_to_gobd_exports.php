<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103200_add_run_state_to_gobd_exports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lauf-Status der GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-722;
 * Vollscan 2026-08-23, A16). Das Paket entsteht jetzt in der Queue
 * ({@see App\Jobs\Finance\GobdExportJob}) und liegt als Datei im privaten
 * Speicher — der Nachweis führt deshalb Status, Ablage und Fehlertext.
 *
 * Bestandszeilen stammen aus dem synchronen Pfad und sind fertig (`ready`);
 * ihr Paket existiert nur noch als Hash-Nachweis, nicht als Datei
 * (`file_path` bleibt NULL, der Download meldet das).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('gobd_exports', function (Blueprint $table): void {
            $table->string('status', 16)->default('ready')->after('sections');
            $table->string('encoding', 16)->default('cp1252')->after('status');
            $table->string('file_path', 255)->nullable()->after('package_sha256');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
            $table->text('error')->nullable()->after('file_size');
            $table->timestamp('started_at')->nullable()->after('error');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->index(['organization_id', 'status'], 'gobd_exports_org_status_idx');
        });
    }

    public function down(): void {
        Schema::table('gobd_exports', function (Blueprint $table): void {
            $table->dropIndex('gobd_exports_org_status_idx');
            $table->dropColumn(['status', 'encoding', 'file_path', 'file_size', 'error', 'started_at', 'finished_at']);
        });
    }
};
