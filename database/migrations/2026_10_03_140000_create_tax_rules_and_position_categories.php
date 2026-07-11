<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_03_140000_create_tax_rules_and_position_categories.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 23 (MVP-237–243): versionierte Steuerregelmatrix — Land/Region/
 * Kategorie/Satztyp mit Gültigkeitszeitraum, Quelle, Hinweistext und
 * Mandanten-Override (org NULL = ausgelieferter Katalog). Dazu
 * EN-16931-Steuerkategorie je Position (Flexibilitätsplan D6) und der
 * eingefrorene Steuerkontext am Beleg (tax_context, MVP-243).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'taxr_org_fk')->cascadeOnDelete();
            $table->char('country', 2);
            $table->string('region', 10)->nullable(); // z. B. Kanaren/Sonderregionen
            $table->string('category', 20)->default('services'); // services|goods|shipping|materials|expenses|construction|media|other
            $table->string('rate_type', 15)->default('standard'); // standard|reduced|zero|exempt|reverse_charge|export
            $table->decimal('rate', 5, 2)->default(0);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('source', 300)->nullable(); // Gesetz/Fundstelle
            $table->string('note', 500)->nullable(); // Pflichthinweis auf dem Beleg
            $table->string('status', 10)->default('active'); // active|draft|retired
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'taxr_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['country', 'category', 'rate_type', 'valid_from'], 'taxr_lookup_idx');
        });

        // EN-16931-Kategorie je Position (D6: S/AE/Z/E/G/K/O); NULL = aus
        // Kopf-/Regelkontext abgeleitet (Abwärtskompatibilität, nie migriert).
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('tax_category', 2)->nullable();
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->string('tax_category', 2)->nullable();
        });

        // Eingefrorener Steuerkontext am Beleg (MVP-243): verwendete Regel(n),
        // Auflösungsdatum und Kategorie — wird bei issue() gesetzt und fällt
        // danach unter die Ausstellungs-Unveränderlichkeit.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->json('tax_context')->nullable();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('tax_context');
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn('tax_category');
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('tax_category');
        });
        Schema::dropIfExists('tax_rules');
    }
};
