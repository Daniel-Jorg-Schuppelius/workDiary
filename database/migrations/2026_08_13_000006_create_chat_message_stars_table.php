<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000006_create_chat_message_stars_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-Nutzer markierte (gesternte) Chat-Nachrichten – Favoriten.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('chat_message_stars')) {
            return;
        }
        Schema::create('chat_message_stars', function (Blueprint $table): void {
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['message_id', 'user_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_message_stars');
    }
};
