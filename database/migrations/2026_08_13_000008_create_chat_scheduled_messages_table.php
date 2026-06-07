<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000008_create_chat_scheduled_messages_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geplante Chat-Nachrichten. Die eigentliche Nachricht wird erst zur
 * scheduled_at-Zeit per Cron-Command erzeugt (korrekte Reihenfolge/Echtzeit).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('chat_scheduled_messages')) {
            return;
        }
        Schema::create('chat_scheduled_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained('chat_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamps();
            $table->index(['scheduled_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_scheduled_messages');
    }
};
