<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_13_100000_create_accounting_taxation_periods_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-679: Abschnitte der Versteuerungsart (Soll/Ist).
 *
 * Bewusst eine eigene Tabelle statt einer Spalte am Profil — dieselbe
 * Begründung wie bei der Buchungshoheit: Ein Wechsel erfolgt typischerweise
 * zum Jahreswechsel, und „welche Methode galt im März?" muss auch Jahre
 * später beantwortbar sein. Ein einzelnes Feld kennt nur den Jetzt-Zustand.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_taxation_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('reason')->nullable();
            // Nachweis der offenen Posten am Stichtag (§ 20 S. 3 UStG).
            $table->json('changeover')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'valid_from'], 'acc_taxm_org_from_unique');
            $table->index(['organization_id', 'valid_to'], 'acc_taxm_org_to_idx');

            $table->foreign('actor_user_id', 'acc_taxm_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_taxation_periods');
    }
};
