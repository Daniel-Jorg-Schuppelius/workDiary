<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140400_create_event_user_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // organizer | trainer | attendee | optional
            $table->string('role', 20)->default('attendee');
            // invited | accepted | declined | attended | no_show
            $table->string('status', 20)->default('invited');

            $table->dateTime('responded_at')->nullable();
            $table->dateTime('attended_at')->nullable();

            // Pflichtnachweis pro Teilnehmer
            $table->date('certificate_issued_at')->nullable();
            $table->date('certificate_expires_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'role']);
            $table->index('certificate_expires_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_user');
    }
};
