<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_30_100000_create_external_contacts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wiederverwendbares externes Kontakt-/Rollenprofil (Feature 033, Rang 30):
 * Stammdaten eines externen Beteiligten (Subunternehmer/Prüfer/Sachverständiger),
 * damit wiederkehrende Externe nicht bei jeder Einladung neu getippt werden.
 * E-Mail bewusst im Klartext (durchsuchbar/Versand); die Einladung
 * (external_participants) denormalisiert Name/E-Mail/Rolle weiterhin als Nachweis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email', 190)->nullable();
            $table->string('role', 120)->nullable();
            $table->string('party', 24)->default('other');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email'], 'ext_contact_org_email_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('external_contacts');
    }
};
