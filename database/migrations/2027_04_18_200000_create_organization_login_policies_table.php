<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_200000_create_organization_login_policies_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anmelderichtlinie je Organisation (Feature 205, MVP-1654–1657): Zeitfenster
 * je Wochentag mit Geltung je Rolle, erlaubte Netze, Passkey-Pflicht je Rolle
 * und der Notaus. Eine Zeile je Organisation; ohne Zeile gilt keine
 * Einschränkung. Eigene Tabelle statt `organizations.settings`: die Werte sind
 * strukturiert, der Notaus muss abfragbar sein (Plattformbetrieb hebt auf), und
 * der Scheduler löst die DNS-Namen aller Organisationen mit Netzregel auf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('organization_login_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('olp_org_uq')->constrained('organizations')->cascadeOnDelete();
            $table->string('hours_mode', 16)->default('off');
            $table->json('hours')->nullable();
            $table->json('hours_roles')->nullable();
            $table->string('network_mode', 16)->default('off');
            $table->json('networks')->nullable();
            $table->json('passkey_roles')->nullable();
            $table->dateTime('lockdown_at')->nullable();
            $table->foreignId('lockdown_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lockdown_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('lockdown_at', 'olp_lockdown_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('organization_login_policies');
    }
};
