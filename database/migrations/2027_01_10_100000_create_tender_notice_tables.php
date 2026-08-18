<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_100000_create_tender_notice_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bekanntmachungs-Radar (MVP-629/630).
 *
 * Der Bekanntmachungsservice des Bundes veröffentlicht alle Pflicht­
 * bekanntmachungen als OpenData unter CC0 — registrierungsfrei abrufbar. Die
 * Bekanntmachungen sind **organisationsübergreifend**: Dieselbe Ausschreibung
 * interessiert mehrere Mandanten, deshalb liegen sie einmal und die Treffer je
 * Organisation daneben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('tender_notices', function (Blueprint $table): void {
            $table->id();
            // Notice-ID und Version des Herausgebers: Zusammen identifizieren
            // sie eine Fassung eindeutig - Bekanntmachungen werden berichtigt.
            $table->string('notice_id', 120);
            $table->string('version', 20)->default('1');
            $table->string('ocid', 120)->nullable();

            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->string('buyer_name', 300)->nullable();
            $table->string('procedure_method', 60)->nullable();

            $table->json('cpv_codes')->nullable();
            $table->string('nuts_code', 10)->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->date('published_on');
            $table->dateTime('submission_deadline')->nullable();
            $table->text('url')->nullable();

            // Die Rohfassung bleibt erhalten: Was der Matcher heute nicht liest,
            // liest er vielleicht morgen - erneut abrufen lässt sich ein
            // vergangener Tag nur begrenzt.
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['notice_id', 'version'], 'tnotice_id_ver_uq');
            $table->index('published_on', 'tnotice_pub_idx');
            $table->index('nuts_code', 'tnotice_nuts_idx');
        });

        Schema::create('tender_filter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('active')->default(true);

            $table->json('cpv_codes')->nullable();
            $table->json('nuts_codes')->nullable();
            $table->json('keywords')->nullable();
            // Ausschlusswörter wiegen schwerer als Stichwörter: Ein Treffer
            // hier verwirft, auch wenn alles andere passt.
            $table->json('excluded_keywords')->nullable();
            $table->decimal('min_value', 15, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'active'], 'tfprof_org_active_idx');
        });

        Schema::create('tender_notice_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_filter_profile_id')->nullable()->constrained()->nullOnDelete();
            // Stummgeschaltet heißt: gesehen und verworfen - der Treffer bleibt
            // als Beleg, verschwindet aber aus der Inbox.
            $table->string('state', 20)->default('new');
            $table->foreignId('application_opportunity_id')->nullable()
                ->constrained('application_opportunities', indexName: 'tnmatch_opp_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'tender_notice_id'], 'tnmatch_org_notice_uq');
            $table->index(['organization_id', 'state'], 'tnmatch_org_state_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tender_notice_matches');
        Schema::dropIfExists('tender_filter_profiles');
        Schema::dropIfExists('tender_notices');
    }
};
