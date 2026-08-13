<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101700_create_approval_steps_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-531: generisches Antragsverfahren-Framework — Genehmigungsstufen als
 * append-only Journal je Antrag (Urlaub, Überstunden, Zeitkorrektur).
 * Stufenzahl je Antragstyp org-konfigurierbar; Vier-Augen wird über die
 * Schritte erzwungen (nie zwei Stufen durch dieselbe Person).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained(indexName: 'apstep_org_fk')->cascadeOnDelete();
            $table->string('approvable_type', 191);
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedSmallInteger('stage');
            $table->string('decision', 16);
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'apstep_decided_fk')->nullOnDelete();
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id'], 'apstep_approvable_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('approval_steps');
    }
};
