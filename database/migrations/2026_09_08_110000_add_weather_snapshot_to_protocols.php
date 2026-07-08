<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_08_110000_add_weather_snapshot_to_protocols.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wetter am Tagesbericht/Bautagebuch (Feature 062, MVP-131): Verknüpfung eines
 * Protokolls mit dem unveränderlichen Wetter-Snapshot seines Tages/Orts. Der
 * Snapshot bleibt der Messwert; die manuelle Vor-Ort-Beobachtung liegt weiter
 * im Protokoll-Inhalt (Messwert ≠ Beobachtung). Nur die Verknüpfung, kein
 * Duplikat der Werte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->foreignId('weather_snapshot_id')->nullable()->after('occurred_at')
                ->constrained('weather_snapshots', indexName: 'protocol_weather_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('weather_snapshot_id');
        });
    }
};
