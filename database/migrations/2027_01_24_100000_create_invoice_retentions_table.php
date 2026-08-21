<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_24_100000_create_invoice_retentions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicherheitseinbehalte nach § 17 VOB/B (Feature 113, MVP-602).
 *
 * Ein Einbehalt ist **kein offener Posten**: Der Betrag ist vertragsgemäß
 * nicht fällig, sondern bis zum Ablauf der Gewährleistung oder gegen
 * Bürgschaft gestundet. Genau deshalb braucht er eine eigene Zeile — ohne sie
 * meldet der Zahlungsabgleich dauerhaft eine Unterzahlung und das Mahnwesen
 * mahnt einen Betrag an, den der Kunde zu Recht nicht zahlt.
 *
 * Betrag als DECIMAL (nicht Float): Geld wird nie in Gleitkomma geführt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_retentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // warranty | performance (Gewährleistung | Vertragserfüllung)
            $table->string('kind', 24);
            // Bemessung: Prozentsatz ODER Festbetrag — der jeweils andere Wert
            // bleibt null, damit im Nachhinein erkennbar ist, was vereinbart war.
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('base_amount', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            // Freigabetermin: ab hier wird der Einbehalt zum normalen offenen Posten.
            $table->date('due_on')->nullable();
            // open | released | secured (durch Bürgschaft abgelöst, MVP-603)
            $table->string('status', 16)->default('open');
            $table->date('released_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'due_on'], 'inv_ret_org_status_due_idx');
            $table->index(['invoice_id', 'status'], 'inv_ret_invoice_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_retentions');
    }
};
