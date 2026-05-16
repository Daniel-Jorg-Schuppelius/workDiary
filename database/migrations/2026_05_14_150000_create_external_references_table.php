<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_150000_create_external_references_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic mapping table between local entities and identifiers in external
 * systems (managed by plugins, e.g. Lexoffice contact id, voucher id).
 *
 * Polymorphic on (referenceable_type, referenceable_id) so any model can be
 * mapped without schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('plugin_id', 64);          // e.g. "lexoffice"
            $table->string('external_type', 64);      // e.g. "contact", "voucher", "invoice"
            $table->morphs('referenceable');          // local model (Customer, TimeEntry, ...)
            $table->string('external_id');            // id returned by the remote system
            $table->json('payload')->nullable();      // last known remote payload (snapshot)
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['plugin_id', 'external_type', 'referenceable_type', 'referenceable_id'], 'extref_unique');
            $table->index(['plugin_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_references');
    }
};
