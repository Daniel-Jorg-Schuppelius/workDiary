<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100500_drop_reselling_reconciliation_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-766): Der zustandslose Lizenz-Abgleich (Feature 151) ist
 * durch das Reselling-Register abgelöst — seine Läufe samt Berichten und
 * hochgeladenen Dateien entfallen. Die Firmenzuordnungen
 * (`reselling_company_mappings`) bleiben: Import und Inbox nutzen sie weiter.
 */
return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('reselling_reconciliation_runs');
    }

    public function down(): void {
        Schema::create('reselling_reconciliation_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 16)->default('queued');
            $t->date('reference_date');
            $t->unsignedSmallInteger('window_before')->default(45);
            $t->unsignedSmallInteger('window_after')->default(90);
            $t->boolean('strict_products')->default(false);
            $t->json('files');
            $t->json('summary')->nullable();
            $t->json('report')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'created_at'], 'reselling_runs_org_created_idx');
        });
    }
};
