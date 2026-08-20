<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_21_100000_add_dial_fields_to_cti_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Click-to-Dial (Feature 056, MVP-118-Rest; Audit 2026-08, W4.5).
 *
 * Die Anbindung war bisher rein eingehend (Webhook-Token). Für den
 * ausgehenden Anruf-Start braucht es einen API-Zugang je Anbindung:
 * verschlüsseltes Token, providerabhängige Basis-URL und die eigene
 * Durchwahl, von der aus gewählt wird. `dial_enabled` ist der bewusste
 * Schalter — ohne ihn bleibt der Anruf-Knopf aus.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('cti_connections', function (Blueprint $table): void {
            $table->boolean('dial_enabled')->default(false)->after('active');
            $table->text('api_token')->nullable()->after('dial_enabled');       // encrypted-Cast
            $table->string('api_base_url', 255)->nullable()->after('api_token');
            $table->string('dial_extension', 64)->nullable()->after('api_base_url');
        });
    }

    public function down(): void {
        Schema::table('cti_connections', function (Blueprint $table): void {
            $table->dropColumn(['dial_enabled', 'api_token', 'api_base_url', 'dial_extension']);
        });
    }
};
