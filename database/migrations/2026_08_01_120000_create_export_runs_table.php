<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_01_120000_create_export_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datentransfer — Export-Lauf je erzeugtem Export.
 *
 * Lebenszyklus: preparing → ready | failed
 *
 * Pro Entität (customers|projects|users|materials|scheduled_shifts|tours)
 * wird ein Lauf angelegt, der Format, Filter, Zeilenzahl und den Pfad der
 * erzeugten Datei für Verlauf, Audit und erneuten Download persistiert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('export_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('entity', 32);                       // customers|projects|users|materials|scheduled_shifts|tours
            $t->string('format', 8)->default('csv');        // csv|xlsx
            $t->string('state', 16)->default('preparing');  // preparing|ready|failed
            $t->json('filters')->nullable();                // angewandte Filter (z. B. Status, Zeitraum)
            $t->string('output_filename', 255);
            $t->string('storage_path', 255)->default('');   // relativer Pfad im Tenant-Storage
            $t->unsignedInteger('rows_total')->default(0);
            $t->text('error_message')->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['organization_id', 'entity', 'state'], 'export_runs_org_entity_state_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('export_runs');
    }
};
