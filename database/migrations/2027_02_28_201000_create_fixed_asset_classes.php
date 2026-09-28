<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_201000_create_fixed_asset_classes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-999: Anlagenklassen mit Vorgaben für Nutzungsdauer, Methode und Konten. */
return new class extends Migration {
    public function up(): void {
        Schema::create('fixed_asset_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('depreciation_method', 16)->default('linear');
            $table->foreignId('asset_account_id')->nullable()->constrained('accounting_accounts', indexName: 'fac_asset_account_fk')->nullOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounting_accounts', indexName: 'fac_depr_account_fk')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'fac_org_name_uq');
        });

        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->foreignId('fixed_asset_class_id')->nullable()->after('asset_id')->constrained('fixed_asset_classes')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fixed_asset_class_id');
        });
        Schema::dropIfExists('fixed_asset_classes');
    }
};
