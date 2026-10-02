<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100100_create_lexoffice_invoice_handovers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 158 (MVP-833) — Übergabestand lokal ausgestellter Belege an Lexware:
 * je Rechnung genau ein Datensatz mit Kanal (manuell/API), Status, dem Hash
 * des exportierten Originals, Export-/Bestätigungsstempeln samt Benutzer und
 * — für die spätere automatische Übergabe (MVP-834) — Datei- und Beleg-ID
 * der Schnittstelle. Der FK auf invoices ist RESTRICT: ein übergebener Beleg
 * verschwindet nicht mit seinem Nachweis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_invoice_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('channel', 16)->default('manual');
            $table->string('status', 16)->default('pending');
            $table->string('invoice_number', 64)->nullable();
            $table->char('document_sha256', 64)->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmation_note', 500)->nullable();
            $table->string('external_file_id', 64)->nullable();
            $table->string('external_voucher_id', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique('invoice_id', 'lih_invoice_unique');
            $table->index(['organization_id', 'status'], 'lih_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lexoffice_invoice_handovers');
    }
};
