<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000007_create_chat_reminders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erinnerungen an Chat-Nachrichten ("Erinnere mich"). remind_at in UTC;
 * ein Cron-Command verschickt fällige Erinnerungen per Web-Push.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('chat_reminders')) {
            return;
        }
        Schema::create('chat_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('chat_channels')->cascadeOnDelete();
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['sent_at', 'remind_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_reminders');
    }
};
