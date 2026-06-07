<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000005_add_quote_forward_to_chat_messages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zitat-Antwort (quoted_id → andere Nachricht) und Weiterleiten
 * (forwarded_from_user_id → ursprünglicher Autor) für den Chat.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('chat_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_messages', 'quoted_id')) {
                $table->foreignId('quoted_id')->nullable()->after('parent_id')
                    ->constrained('chat_messages')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_messages', 'forwarded_from_user_id')) {
                $table->foreignId('forwarded_from_user_id')->nullable()->after('quoted_id')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('chat_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_messages', 'quoted_id')) {
                $table->dropConstrainedForeignId('quoted_id');
            }
            if (Schema::hasColumn('chat_messages', 'forwarded_from_user_id')) {
                $table->dropConstrainedForeignId('forwarded_from_user_id');
            }
        });
    }
};
