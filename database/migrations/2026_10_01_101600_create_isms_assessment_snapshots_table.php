<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101600_create_isms_assessment_snapshots_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stichtags-Rekonstruktion (Nachtrag 046b): append-only Snapshot je
 * Bewertungsänderung (SoA-Aussagen + Norm-Konformitätsstatus). Der Stand zu
 * Datum T ist je Subjekt der letzte Snapshot ≤ T (Muster Sprint-Snapshots).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_assessment_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'ias_org_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('isms_scope_id')->nullable();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->timestamp('recorded_at');

            $table->index(['organization_id', 'subject_type', 'subject_id', 'recorded_at'], 'ias_subject_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_assessment_snapshots');
    }
};
