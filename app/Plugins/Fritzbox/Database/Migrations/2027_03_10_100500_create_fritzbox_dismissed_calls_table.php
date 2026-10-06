<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100500_create_fritzbox_dismissed_calls_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sperrmarken verworfener FRITZ!Box-Anrufe (Entscheidung 2026-10-06): je
 * Organisation ein HMAC des Anrufschlüssels und der Zeitpunkt — kein Klartext,
 * keine Rufnummer, kein Benutzer ({@see \App\Plugins\Fritzbox\Models\FritzboxDismissedCall}).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('fritzbox_dismissed_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'fdc_org_fk')->cascadeOnDelete();
            $table->string('call_hash', 64);
            $table->timestamp('dismissed_at');

            $table->unique(['organization_id', 'call_hash'], 'fdc_org_hash_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('fritzbox_dismissed_calls');
    }
};
