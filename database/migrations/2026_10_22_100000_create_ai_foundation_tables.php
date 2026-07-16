<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_22_100000_create_ai_foundation_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KI-Fundament (Feature 025, MVP-399):
 *
 * - ai_provider_connections: mehrere Provider-Verbindungen je Organisation
 *   und Familie (LLM/Übersetzung), API-Schlüssel verschlüsselt (Cast im
 *   Model), Health-Standardspalten (HasConnectionHealth), is_local für das
 *   Sensibilitäts-/Profil-Gate.
 * - ai_capability_settings: Opt-in + Routing je Organisation und
 *   Capability (Default-Verbindung, erlaubte Verbindungen in Reihenfolge,
 *   Nutzerwahl-Schalter).
 * - ai_usage_periods: Monatsverbrauch je Organisation und Familie
 *   (LLM: Token, Übersetzung: Zeichen) für das Budget-Gate.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('family', 16);
            $table->string('provider', 32);
            $table->string('name', 120);
            $table->string('base_url', 500)->nullable();
            $table->text('api_key')->nullable(); // encrypted-Cast im Model
            $table->string('model', 120)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_local')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('preflight_at')->nullable();
            // Health-Standard (HasConnectionHealth):
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'family'], 'aipc_org_family_idx');
            $table->unique(['organization_id', 'name'], 'aipc_org_name_uq');
        });

        Schema::create('ai_capability_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('capability', 80);
            $table->boolean('enabled')->default(false);
            $table->foreignId('default_connection_id')->nullable()
                ->constrained('ai_provider_connections')->nullOnDelete();
            $table->json('allowed_connection_ids')->nullable();
            $table->boolean('allow_user_choice')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'capability'], 'aics_org_capability_uq');
        });

        Schema::create('ai_usage_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('family', 16);
            $table->unsignedBigInteger('used_units')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'period', 'family'], 'aiup_org_period_family_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ai_usage_periods');
        Schema::dropIfExists('ai_capability_settings');
        Schema::dropIfExists('ai_provider_connections');
    }
};
