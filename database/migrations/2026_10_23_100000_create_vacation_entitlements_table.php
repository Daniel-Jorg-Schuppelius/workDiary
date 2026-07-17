<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_23_100000_create_vacation_entitlements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MVP-413: Urlaubskonto — Jahresanspruch + Übertrag je Nutzer.
return new class extends Migration {
    public function up(): void {
        Schema::create('vacation_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 5, 1);
            $table->decimal('carryover_days', 5, 1)->default(0);
            // Verfallsdatum des Übertrags (BUrlG-üblich: 31.03. des Anspruchsjahres).
            $table->date('carryover_expires_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'year'], 'vac_entitlements_org_user_year_uq');
            $table->index(['organization_id', 'year'], 'vac_entitlements_org_year_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vacation_entitlements');
    }
};
