<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_120000_add_declining_and_special_depreciation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-980/981: degressive AfA (Satz je Anlage) und Sonder-AfA § 7g je Geschäftsjahr. */
return new class extends Migration {
    public function up(): void {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->decimal('declining_rate', 5, 2)->nullable();
        });

        Schema::create('fixed_asset_special_depreciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'fa_special_org_fk')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets', indexName: 'fa_special_asset_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('depreciation_amount', 15, 2);
            $table->char('currency', 3);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'fa_special_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'fiscal_year'], 'fa_special_asset_year_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('fixed_asset_special_depreciations');
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropColumn('declining_rate');
        });
    }
};
