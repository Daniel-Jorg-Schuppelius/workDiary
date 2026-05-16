<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_140001_add_kimai_fields_to_projects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Kimai-style billing/budget fields to projects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained('customers')->nullOnDelete();
            $table->string('number', 64)->nullable()->after('slug');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('color');
            $table->decimal('internal_rate', 10, 2)->nullable()->after('hourly_rate');
            $table->unsignedInteger('time_budget')->default(0)->after('internal_rate');
            $table->decimal('budget', 12, 2)->default(0)->after('time_budget');
            $table->string('budget_type', 16)->nullable()->after('budget'); // null|month|year
            $table->boolean('billable')->default(true)->after('budget_type');
            $table->text('invoice_text')->nullable()->after('description');
            $table->boolean('global_activities')->default(true)->after('billable');
            $table->timestamp('archived_at')->nullable()->after('ends_on');

            $table->index('customer_id');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn([
                'customer_id',
                'number',
                'hourly_rate',
                'internal_rate',
                'time_budget',
                'budget',
                'budget_type',
                'billable',
                'invoice_text',
                'global_activities',
                'archived_at',
            ]);
        });
    }
};
