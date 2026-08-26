<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_101700_create_fixed_assets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anlagenregister (Feature 133, MVP-698; Vollscan 2026-08-23, H16):
 * die BUCHHALTERISCHE Sicht auf ein Wirtschaftsgut — AK/HK, Nutzungsdauer,
 * AfA-Methode, Restwert, Konten. Das Gerät selbst bleibt beim Asset-Modell
 * (optionaler FK); nicht jede Anlage ist ein Gerät und nicht jedes Gerät
 * eine Anlage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Laufende Nummer je Org (AN-1, AN-2, …).
            $table->unsignedInteger('asset_no');
            $table->string('name', 180);
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->date('acquired_on');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('depreciation_method', 16)->default('linear');
            // Konten je Anlage — leer heißt: Buchungsregel der Rolle greift.
            $table->foreignId('asset_account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('status', 16)->default('active'); // active|disposed
            $table->date('disposed_on')->nullable();
            // Herkunft (Eingangsrechnung, Auslage, …) für den Drilldown.
            $table->nullableMorphs('source', 'fixed_assets_source_idx');
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'asset_no'], 'fixed_assets_org_no_uq');
            $table->index(['organization_id', 'status', 'acquired_on'], 'fixed_assets_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('fixed_assets');
    }
};
