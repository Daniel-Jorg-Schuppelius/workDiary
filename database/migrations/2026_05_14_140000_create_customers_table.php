<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_140000_create_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kimai-style customers: a customer owns one or more projects and provides
 * default billing data (currency, timezone, hourly rate). Inspired by the
 * Kimai (https://github.com/kimai/kimai) data model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('number', 64)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('vat_id', 64)->nullable();
            $table->string('contact_name', 200)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('mobile', 64)->nullable();
            $table->string('fax', 64)->nullable();
            $table->string('homepage')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone', 64)->nullable();
            $table->string('color', 16)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('internal_rate', 10, 2)->nullable();
            $table->text('comment')->nullable();
            $table->text('invoice_text')->nullable();
            $table->boolean('billable')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('archived_at');
            $table->unique(['organization_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
