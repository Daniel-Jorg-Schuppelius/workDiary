<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_03_100000_create_service_calculation_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1055: Kalkulation — Lohngruppen, Kalkulationsschema je Organisation mit
 * Zuschlägen je Kostenart, Kostenansätze an Leistungsartikeln und die
 * Einzelkosten als Schnappschuss an Angebots- und Rechnungsposition.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('wage_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('hourly_wage_amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('calculation_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('average_wage_amount', 10, 2)->nullable();
            $table->decimal('wage_related_percent', 6, 2)->default(0);
            $table->decimal('wage_ancillary_amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('calculation_scheme_markups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calculation_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('cost_kind', 16);
            $table->decimal('site_overhead_percent', 6, 2)->default(0);
            $table->decimal('general_overhead_percent', 6, 2)->default(0);
            $table->decimal('risk_profit_percent', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['calculation_scheme_id', 'cost_kind'], 'calc_scheme_markups_scheme_kind_unique');
        });

        Schema::create('article_cost_approaches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('cost_kind', 16);
            $table->string('description', 255)->nullable();
            $table->foreignId('component_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('wage_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('unit', 32)->nullable();
            $table->decimal('minutes', 10, 2)->nullable();
            $table->decimal('unit_cost_amount', 12, 4)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['article_id', 'position']);
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->decimal('unit_cost_amount', 12, 4)->nullable();
            $table->json('calculation')->nullable();
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('unit_cost_amount', 12, 4)->nullable();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('unit_cost_amount');
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost_amount', 'calculation']);
        });
        Schema::dropIfExists('article_cost_approaches');
        Schema::dropIfExists('calculation_scheme_markups');
        Schema::dropIfExists('calculation_schemes');
        Schema::dropIfExists('wage_groups');
    }
};
