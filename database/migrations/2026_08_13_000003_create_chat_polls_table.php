<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000003_create_chat_polls_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chat_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->string('question');
            $table->boolean('multiple')->default(false);     // Mehrfachauswahl erlaubt?
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
        });

        Schema::create('chat_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained('chat_polls')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('poll_id');
        });

        Schema::create('chat_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_option_id')->constrained('chat_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_option_id', 'user_id']);
            $table->index('poll_option_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_poll_options');
        Schema::dropIfExists('chat_polls');
    }
};
