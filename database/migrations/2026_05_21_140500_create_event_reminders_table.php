<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140500_create_event_reminders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // null = an alle Teilnehmer dieses Events versenden
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();

            $table->dateTime('remind_at');
            // mail | webpush | database
            $table->string('channel', 20)->default('mail');

            $table->dateTime('sent_at')->nullable();
            $table->text('error')->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['remind_at', 'sent_at']);
            $table->index(['event_id', 'remind_at']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_reminders');
    }
};
