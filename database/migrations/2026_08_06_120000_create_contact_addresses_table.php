<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120000_create_contact_addresses_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphe Adressen für Kontakte (Customer/Supplier). Lexoffice führt je
 * Kontakt mehrere Adressen (billing/shipping), die wir hier strukturiert
 * ablegen. Die optionale `external_id` verknüpft eine Adresse mit ihrem
 * Lexoffice-Pendant für den bidirektionalen Abgleich.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('contact_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->morphs('addressable');
            $table->string('kind', 32)->default('billing'); // billing, shipping, default
            // Verschluesselte PII (Model-Cast): text statt string (vgl. widen-Migration).
            $table->text('supplement')->nullable();
            $table->text('street')->nullable();
            $table->text('zip')->nullable();
            $table->text('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('external_id', 64)->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['addressable_type', 'addressable_id', 'kind'], 'contact_addresses_kind_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('contact_addresses');
    }
};
