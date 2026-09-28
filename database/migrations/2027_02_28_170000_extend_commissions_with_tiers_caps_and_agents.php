<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_170000_extend_commissions_with_tiers_caps_and_agents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-988/989: Staffeln und Jahresdeckel je Regel, Haftungsfrist,
 * Provision auf Teilzahlungen und externe Vermittler ohne Benutzerkonto.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('commission_agents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'comm_agent_org_fk')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('company', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'comm_agent_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'comm_agent_org_active_idx');
        });

        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->foreignId('commission_agent_id')->nullable()->after('user_id')
                ->constrained('commission_agents', indexName: 'comm_rule_agent_fk')->nullOnDelete();
            $table->char('currency', 3)->nullable()->after('rate_percent');
            $table->string('tier_period', 10)->nullable()->after('currency');
            $table->decimal('annual_cap_amount', 14, 2)->nullable()->after('tier_period');
            $table->unsignedSmallInteger('liability_days')->nullable()->after('annual_cap_amount');
            $table->boolean('is_partial_accrual')->default(false)->after('liability_days');
        });

        Schema::create('commission_rule_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'comm_tier_org_fk')->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->constrained('commission_rules', indexName: 'comm_tier_rule_fk')->cascadeOnDelete();
            $table->decimal('threshold_amount', 14, 2);
            $table->decimal('rate_percent', 5, 2);
            $table->timestamps();
            $table->unique(['commission_rule_id', 'threshold_amount'], 'comm_tier_rule_threshold_uq');
        });

        Schema::table('invoice_commissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('commission_agent_id')->nullable()->after('user_id')
                ->constrained('commission_agents', indexName: 'inv_comm_agent_fk')->nullOnDelete();
            $table->date('payable_on')->nullable()->after('earned_on');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('sales_agent_id')->nullable()->after('sales_user_id')
                ->constrained('commission_agents', indexName: 'invoices_sales_agent_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoices_sales_agent_fk');
            $table->dropColumn('sales_agent_id');
        });
        Schema::table('invoice_commissions', function (Blueprint $table): void {
            $table->dropForeign('inv_comm_agent_fk');
            $table->dropColumn(['commission_agent_id', 'payable_on']);
        });
        Schema::dropIfExists('commission_rule_tiers');
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->dropForeign('comm_rule_agent_fk');
            $table->dropColumn(['commission_agent_id', 'currency', 'tier_period', 'annual_cap_amount', 'liability_days', 'is_partial_accrual']);
        });
        Schema::dropIfExists('commission_agents');
    }
};
