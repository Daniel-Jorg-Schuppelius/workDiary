<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_29_090000_drop_incoming_invoice_exclusions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausschlussregeln des Rechnungseingangs (MVP-1208) gehen in die
 * Automationsregeln auf (Phase 150, MVP-1211): Auslöser
 * `incoming_invoice.received`, Aktionen verwerfen/ablehnen/zuordnen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('incoming_invoice_exclusions');
    }

    public function down(): void {
        Schema::create('incoming_invoice_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('party_name', 191);
            $table->string('party_name_key', 191);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'direction', 'party_name_key'], 'incoming_invoice_exclusion_org_dir_key_unique');
        });
    }
};
