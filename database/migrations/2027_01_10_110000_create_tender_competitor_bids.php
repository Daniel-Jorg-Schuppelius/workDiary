<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_110000_create_tender_competitor_bids.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submissionsergebnis: Konkurrenzangebote zu einem Vergabevorgang
 * (Feature 108, MVP-628).
 *
 * Bei einer Öffentlichen Ausschreibung nach VOB/A werden im Eröffnungstermin
 * die Angebotsendsummen **verlesen**; oberschwellig nennt das
 * Informationsschreiben nach § 134 GWB den vorgesehenen Zuschlagsempfänger.
 * Beides ist die einzige belastbare Quelle für den eigenen Preisabstand — und
 * die wertvollste Rückmeldung für die nächste Kalkulation.
 *
 * Die Zeilen sind **Fremdangaben aus einem Termin**, keine eigenen Belege:
 * Der Bieter steht als Freitext, denn wer beim Eröffnungstermin verlesen wird,
 * ist selten schon Stammdatensatz — und wäre er es, machte eine Verknüpfung
 * ihn zum Geschäftspartner, der er nicht ist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('tender_competitor_bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_opportunity_id')
                ->constrained('application_opportunities', indexName: 'tcbid_opp_fk')->cascadeOnDelete();

            $table->string('bidder_name', 300);
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            // Der eigene Rang ergibt sich aus den Beträgen; der verlesene Rang
            // kann davon abweichen (Nebenangebote, Wertungspunkte), deshalb
            // wird er erfasst statt gerechnet.
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('is_own')->default(false);
            $table->boolean('is_winner')->default(false);
            $table->date('recorded_on')->nullable();
            $table->string('source', 40)->default('opening');
            $table->string('note', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_opportunity_id', 'rank'], 'tcbid_opp_rank_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tender_competitor_bids');
    }
};
