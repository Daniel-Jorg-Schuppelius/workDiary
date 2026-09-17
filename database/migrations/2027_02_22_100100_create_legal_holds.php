<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100100_create_legal_holds.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal Hold (MVP-801, Feature 130): Sperrvermerk an Person oder Kunde für die
 * Dauer eines Betroffenen- oder Rechtsverfahrens. Solange ein Vermerk aktiv ist,
 * schlagen Löschkonzept, Anonymisierung und Aufräumläufe für diese Daten nichts
 * vor und löschen nichts.
 *
 * Eigene Tabelle statt Flag: Mehrere Verfahren können parallel laufen, und wer
 * wann warum gesperrt und wieder freigegeben hat, gehört zum Nachweis.
 * Begründungen werden verschlüsselt gespeichert — sie nennen oft das Verfahren.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'lhold_org_fk')->cascadeOnDelete();
            $table->string('holdable_type', 120);
            $table->unsignedBigInteger('holdable_id');
            $table->text('reason');
            $table->string('reference', 120)->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users', indexName: 'lhold_placed_by_fk')->nullOnDelete();
            $table->timestamp('placed_at');
            $table->foreignId('released_by')->nullable()->constrained('users', indexName: 'lhold_released_by_fk')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->index(['holdable_type', 'holdable_id', 'released_at'], 'lhold_holdable_idx');
            $table->index(['organization_id', 'released_at'], 'lhold_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('legal_holds');
    }
};
