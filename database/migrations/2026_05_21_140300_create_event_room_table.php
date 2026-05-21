<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140300_create_event_room_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_room', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();

            // Eigene Zeitspanne pro Raum, damit z. B. ein Workshop nach Mittag
            // den Raum wechseln kann (Pivot-Zeit kann von Event-Zeit abweichen).
            $table->dateTime('started_at');
            $table->dateTime('ended_at');

            // Vor-/Nachlauf in Minuten zur Konflikterkennung (Aufbau / Reinigung).
            $table->unsignedSmallInteger('setup_minutes_before')->default(0);
            $table->unsignedSmallInteger('teardown_minutes_after')->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'room_id']);
            $table->index(['room_id', 'started_at', 'ended_at']);
            $table->index(['event_id', 'started_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_room');
    }
};
