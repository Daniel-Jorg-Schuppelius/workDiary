<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_140004_add_billing_fields_to_time_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Kimai-style billing fields to time_entries.
 *
 * - billable:     entry counts toward billable time
 * - rate:         calculated revenue (minutes/60 * hourly_rate, or fixed_rate)
 * - internal_rate: internal cost equivalent
 * - hourly_rate:  resolved/used hourly rate snapshot
 * - fixed_rate:   overrides hourly rate when set (flat fee)
 * - exported:     marked as exported to payroll/invoice (locked)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->boolean('billable')->default(true)->after('kind');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('billable');
            $table->decimal('fixed_rate', 10, 2)->nullable()->after('hourly_rate');
            $table->decimal('rate', 12, 2)->default(0)->after('fixed_rate');
            $table->decimal('internal_rate', 12, 2)->default(0)->after('rate');
            $table->boolean('exported')->default(false)->after('internal_rate');

            $table->index('billable');
            $table->index('exported');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex(['billable']);
            $table->dropIndex(['exported']);
            $table->dropColumn([
                'billable',
                'hourly_rate',
                'fixed_rate',
                'rate',
                'internal_rate',
                'exported',
            ]);
        });
    }
};
