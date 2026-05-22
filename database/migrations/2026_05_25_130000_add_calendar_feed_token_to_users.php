<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_130000_add_calendar_feed_token_to_users.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('calendar_feed_token', 64)->nullable()->unique();
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['calendar_feed_token']);
            $table->dropColumn('calendar_feed_token');
        });
    }
};
