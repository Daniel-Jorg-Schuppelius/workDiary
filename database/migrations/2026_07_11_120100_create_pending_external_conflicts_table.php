<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_120100_create_pending_external_conflicts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konflikte beim Sync mit externen Systemen (z. B. Lexoffice) im Modus
 * "manual_review". Speichert lokale + remote Snapshots, wartet auf eine
 * Entscheidung des Anwenders.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('pending_external_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('plugin_id', 64);
            $table->string('conflict_type', 64); // contact | article | invoice ...
            $table->morphs('referenceable'); // lokales Model, das mit Remote-Daten kollidiert
            $table->string('external_id')->nullable();
            $table->json('local_snapshot');
            $table->json('remote_snapshot');
            $table->json('diff_fields')->nullable(); // Liste der abweichenden Felder
            $table->string('status', 16)->default('open'); // open | resolved_local | resolved_remote | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'plugin_id', 'status']);
            $table->index(['plugin_id', 'external_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('pending_external_conflicts');
    }
};
