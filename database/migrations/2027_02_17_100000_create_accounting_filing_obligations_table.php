<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_17_100000_create_accounting_filing_obligations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erledigungsstand steuerlicher Meldepflichten (Feature 125, MVP-686).
 *
 * Gespeichert wird **nur die Erledigung**, nicht der Termin: Termine sind aus
 * Meldeprofil, Periode und Feiertagskalender berechenbar. Eine Termintabelle
 * wäre ein zweiter Bestand, der bei jeder Intervalländerung falsch würde.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_filing_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            // Periodenschlüssel: 2026-M03, 2026-Q1, 2026-J — dieselbe Kennung
            // wie in der Auswertung.
            $table->string('period_key', 16);
            $table->date('due_on');
            $table->string('status', 16)->default('open');
            $table->timestamp('submitted_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'kind', 'period_key'], 'acc_fobl_org_kind_period_uq');
            $table->index(['organization_id', 'status', 'due_on'], 'acc_fobl_org_status_due_idx');
            $table->foreign('actor_user_id', 'acc_fobl_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_filing_obligations');
    }
};
