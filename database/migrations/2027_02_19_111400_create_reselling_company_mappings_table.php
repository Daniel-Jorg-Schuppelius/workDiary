<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_111400_create_reselling_company_mappings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 151: In der Oberfläche gespeicherte Zuordnung Marketplace-Firma →
 * Kunde (direkt), Partner (Abrechnung über einen Kunden, Fremdkunde) oder
 * Lexoffice-Kontakt. Gilt je Organisation für alle künftigen Läufe.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('reselling_company_mappings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('company_key', 64)->nullable();     // Kundennummer der Quelle, falls bekannt
            $t->string('company_name', 255);
            $t->string('normalized_name', 255);
            $t->string('mode', 16);                          // customer|partner|contact
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('contact_external_id', 64)->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['organization_id', 'normalized_name'], 'reselling_map_org_name_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reselling_company_mappings');
    }
};
