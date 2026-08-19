<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_110000_create_leads_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead-Akte (Feature 091, MVP-654): Interessenten VOR dem Kundenstatus.
 *
 * Bewusst eigene Tabelle statt „Kunde light": Leads sind personenbezogene
 * Daten ohne Vertrag — sie brauchen einen eigenen Lebenszyklus (Anonymisierung
 * nach Frist statt Aufbewahrung) und dürfen den Kundenstamm nicht verwässern.
 * Die Konvertierung erzeugt den Kunden erst nach Dublettenprüfung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('company', 160)->nullable();
            $table->string('contact_name', 160)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('source', 32)->default('other');
            $table->text('interest')->nullable();
            $table->string('status', 24)->default('new');
            $table->string('discard_reason', 500)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Nach der Konvertierung: der entstandene (oder verbundene) Kunde.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Anker der Löschfrist: 6 Monate nach dem letzten Kontakt (D-Regel
            // aus dem Feature; Muster Bewerber-Retention Feature 068).
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'leads_org_status_idx');
            $table->index(['organization_id', 'last_contact_at'], 'leads_org_contact_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('leads');
    }
};
