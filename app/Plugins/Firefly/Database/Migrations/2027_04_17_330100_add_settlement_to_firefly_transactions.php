<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_330100_add_settlement_to_firefly_transactions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Firefly III (MVP-1628): Bewegungen ohne Beleg lassen sich als erledigt markieren
 * (Gebühr, Privatentnahme). Der Abgleich setzt nur die Zuordnung neu, nie die Erledigung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('firefly_transactions', function (Blueprint $table): void {
            $table->timestamp('settled_at')->nullable()->after('incoming_einvoice_id');
            $table->foreignId('settled_by')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable()->after('settled_by');
        });
    }

    public function down(): void {
        Schema::table('firefly_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn(['settled_at', 'note']);
        });
    }
};
