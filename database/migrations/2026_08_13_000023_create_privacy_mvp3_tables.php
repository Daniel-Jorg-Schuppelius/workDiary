<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000023_create_privacy_mvp3_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Datenschutzmanagement MVP 3: Datenschutzvorfaelle (Art. 33/34) mit per-Fall-
 * Krypto und Event-Hash-Kette, Massnahmenverfolgung sowie DSFA (Art. 35).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('incident_number', 32);
            $table->string('type', 16);
            $table->string('status', 16)->default('detected');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('reported_internally_at')->nullable();
            $table->timestamp('authority_deadline_at')->nullable();  // Entdeckung + 72 h
            $table->string('risk_level', 16)->nullable();
            $table->unsignedInteger('affected_count')->nullable();
            $table->boolean('notify_authority')->default(false);
            $table->boolean('notify_subjects')->default(false);
            $table->timestamp('authority_notified_at')->nullable();
            $table->timestamp('subjects_notified_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Verschluesselte Inhalte (DEK pro Fall)
            $table->text('summary_ciphertext')->nullable();
            $table->text('affected_ciphertext')->nullable();
            $table->text('measures_ciphertext')->nullable();
            $table->text('lessons_ciphertext')->nullable();
            $table->text('dek_wrapped')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'incident_number'], 'pinc_org_number_unique');
            $table->index(['organization_id', 'status']);
            $table->index('authority_deadline_at');
        });

        Schema::create('privacy_incident_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event', 64);
            $table->json('metadata')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['incident_id', 'event']);
            $table->index('hash');
        });

        Schema::create('privacy_measures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('privacy_incidents')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('privacy_processing_activities')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_at')->nullable();
            $table->string('status', 16)->default('open');  // open | done
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('privacy_dpias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('privacy_processing_activities')->cascadeOnDelete();
            $table->text('necessity')->nullable();
            $table->text('risks')->nullable();
            $table->text('mitigations')->nullable();
            $table->string('residual_risk', 16)->nullable();
            $table->string('outcome', 16)->default('open');
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'outcome']);
        });

        DB::table('audit_chain_heads')->insertOrIgnore([
            'chain' => 'privacy_incident_events',
            'head_hash' => null,
            'height' => 0,
        ]);
    }

    public function down(): void {
        DB::table('audit_chain_heads')->where('chain', 'privacy_incident_events')->delete();
        Schema::dropIfExists('privacy_dpias');
        Schema::dropIfExists('privacy_measures');
        Schema::dropIfExists('privacy_incident_events');
        Schema::dropIfExists('privacy_incidents');
    }
};
