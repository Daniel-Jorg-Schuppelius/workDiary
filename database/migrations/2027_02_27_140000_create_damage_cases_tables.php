<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_140000_create_damage_cases_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-919: Versicherungs-/Schadensfälle an Verleih, Leasing, Reklamation und Fahrzeug. */
return new class extends Migration {
    public function up(): void {
        Schema::create('damage_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'damage_cases_org_fk')->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('kind', 24);
            $table->string('status', 24);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->string('insurer_name', 160)->nullable();
            $table->string('policy_number', 80)->nullable();
            $table->string('claim_number', 80)->nullable();
            $table->decimal('estimated_amount', 14, 2)->nullable();
            $table->decimal('settled_amount', 14, 2)->nullable();
            $table->decimal('deductible_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'damage_cases_responsible_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'damage_cases_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'damage_cases_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'damage_cases_subject_idx');
            $table->index(['organization_id', 'status'], 'damage_cases_org_status_idx');
            $table->unique(['organization_id', 'number'], 'damage_cases_org_number_uq');
        });

        Schema::create('damage_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('damage_case_id')->constrained('damage_cases', indexName: 'damage_case_events_case_fk')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'damage_case_events_actor_fk')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('damage_case_events');
        Schema::dropIfExists('damage_cases');
    }
};
