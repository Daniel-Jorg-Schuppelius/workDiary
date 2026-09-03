<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111300_create_reselling_reconciliation_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 151: Läufe des Lizenz-Reselling-Abgleichs aus der Oberfläche —
 * hochgeladene Exporte, Optionen, Stand und der fertige Bericht als JSON.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('reselling_reconciliation_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 16)->default('queued');   // queued|running|done|failed
            $t->date('reference_date');
            $t->unsignedSmallInteger('window_before')->default(45);
            $t->unsignedSmallInteger('window_after')->default(90);
            $t->json('files');                              // [{kind, name, path}]
            $t->json('summary')->nullable();                // Kennzahlen für die Liste
            $t->json('report')->nullable();                 // vollständiger Bericht (Serializer)
            $t->text('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();

            $t->index(['organization_id', 'created_at'], 'reselling_runs_org_created_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reselling_reconciliation_runs');
    }
};
