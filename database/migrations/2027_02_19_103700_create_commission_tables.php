<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103700_create_commission_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provisionen (Feature 146, MVP-729; Vollscan 2026-08-23, H24).
 *
 * Drei Tabellen und eine Spalte:
 *
 *  - `commission_rules` — Satz je Lead-Quelle / Produktgruppe / Vertriebs-
 *    person mit Gueltigkeitszeitraum und Prioritaet. Genau EINE Regel gewinnt
 *    je Beleg (hoechste Prioritaet, dann Spezifitaet) — keine Staffeln, keine
 *    Deckel, keine Summierung mehrerer Regeln.
 *  - `commission_settlement_runs` — Abrechnungslauf je Periode. `draft` ist
 *    die Vorschau, `closed` ist festgeschrieben; danach korrigiert nur noch
 *    eine Rueckrechnung (Reversal) in einem spaeteren Lauf.
 *  - `invoice_commissions` — die einzelne Provisionszeile je (Beleg,
 *    Vertriebsperson). Rueckrechnungen sind eigene Zeilen mit negativem
 *    Betrag und `reversal_of_id` auf die Ursprungszeile — nie ein Update der
 *    Ursprungszeile (die kann bereits in einem geschlossenen Lauf haengen).
 *  - `invoices.sales_user_id` — die **manuelle** Zuordnung Beleg →
 *    Vertriebsperson. Die automatische Herkunft kommt aus der Lead-Pipeline
 *    (Feature 091) und wird nicht materialisiert; die Spalte ueberschreibt sie.
 *
 * Bewusst KEINE Auszahlung: WorkDiary rechnet die Provision aus und exportiert
 * sie fuer die Lohnabrechnung — gezahlt wird ausserhalb.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            // all | lead_source | product_group | user (CommissionScope)
            $table->string('scope', 20)->default('all');
            // Lead-Quelle (LeadSource-Wert) bzw. Produktgruppe (articles.category).
            $table->string('scope_value', 120)->nullable();
            // Vertriebsperson bei scope=user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('rate_percent', 5, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            // Hoehere Zahl gewinnt; bei Gleichstand entscheidet die Spezifitaet
            // des Geltungsbereichs (user > product_group > lead_source > all).
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active', 'priority'], 'comm_rule_org_active_idx');
            $table->index(['organization_id', 'scope', 'scope_value'], 'comm_rule_org_scope_idx');
        });

        Schema::create('commission_settlement_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Sprechende Periode ("2026-08"); Grenzen stehen daneben, damit
            // auch Quartale/Sonderperioden moeglich sind.
            $table->string('period', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 12)->default('draft'); // draft|closed
            $table->string('currency', 3)->default('EUR');
            $table->decimal('total_base', 14, 2)->default(0);
            $table->decimal('total_commission', 14, 2)->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Eine Periode, ein Lauf je Waehrung — Nachzuegler
            // (Rueckrechnungen) landen im Lauf der Periode ihres
            // Entstehungsdatums, nicht in einem zweiten Lauf derselben Periode.
            // Die Waehrung steht im Schluessel, weil ein Lauf genau eine
            // Waehrung abrechnet (Provisionen werden nie umgerechnet).
            $table->unique(['organization_id', 'period_start', 'period_end', 'currency'], 'comm_run_org_period_uq');
            $table->index(['organization_id', 'status'], 'comm_run_org_status_idx');
        });

        Schema::create('invoice_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Regel als Beleg der Berechnung; sie darf spaeter geloescht werden,
            // der eingefrorene Satz steht in rate_percent.
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            // Herkunft der Zuordnung: lead (Lead-Pipeline) | manual.
            $table->string('assignment_source', 12)->default('lead');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('rate_percent', 5, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            // Stichtag der Periodenzuordnung: Zahltag der Rechnung bzw. Tag der
            // Stornierung/Gutschrift bei Rueckrechnungen.
            $table->date('earned_on');
            $table->string('status', 12)->default('pending'); // pending|settled|reversed
            $table->foreignId('settlement_run_id')->nullable()->constrained('commission_settlement_runs')->nullOnDelete();
            // Rueckrechnung: zeigt auf die Zeile, die sie mindert.
            $table->foreignId('reversal_of_id')->nullable()->constrained('invoice_commissions')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'earned_on'], 'inv_comm_org_status_idx');
            $table->index(['organization_id', 'user_id', 'earned_on'], 'inv_comm_org_user_idx');
            $table->index(['invoice_id', 'user_id'], 'inv_comm_invoice_user_idx');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Manuelle Vertriebszuordnung — kein Beleginhalt (steht in keiner
            // Belegdarstellung), daher auch nach dem Ausstellen aenderbar
            // (Invoice::MUTABLE_AFTER_ISSUE).
            $table->foreignId('sales_user_id')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['sales_user_id']);
            $table->dropColumn('sales_user_id');
        });
        Schema::dropIfExists('invoice_commissions');
        Schema::dropIfExists('commission_settlement_runs');
        Schema::dropIfExists('commission_rules');
    }
};
