<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_150000_create_resale_provider_suppliers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 185 (MVP-1419): Welcher Lieferant im Stamm ist welcher Anbieter des
 * Reselling-Registers? Über die Zuordnung übernimmt das Register die
 * Eingangsrechnungen des Lieferanten als Einkauf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_provider_suppliers', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $t->string('provider', 32);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'supplier_id'], 'resale_prov_sup_org_supplier_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_provider_suppliers');
    }
};
