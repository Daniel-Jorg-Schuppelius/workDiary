<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_23_200100_create_presence_statuses_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): der Stand,
 * den eine Person selbst setzt — „nicht stören“, „kurz weg“, „außer Haus“ —
 * mit optionalem Ende. Höchstens eine Zeile je Person: nur der aktuelle
 * Stand, kein Verlauf, keine Zeitstempel-Historie. Die Zeile verschwindet mit
 * dem Ende, beim Ausstempeln und beim Zurücksetzen
 * ({@see \App\Services\Attendance\PresenceStatusService}).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('presence_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->dateTime('valid_until')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'presence_statuses_user_unique');
            $table->index(['organization_id', 'valid_until'], 'presence_statuses_org_until_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('presence_statuses');
    }
};
