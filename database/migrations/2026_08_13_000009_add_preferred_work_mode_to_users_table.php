<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000009_add_preferred_work_mode_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistiert den zuletzt gewählten Arbeitsmodus (legacy/new) pro User, damit
 * Dual-Access-User (z. B. Admins) nach Session-Ablauf, neuem Login oder F5
 * nicht erneut in den Default-Legacy-Modus zurückfallen. NULL = noch keine
 * Wahl getroffen → es greift der historische Legacy-Default.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('preferred_work_mode', 16)->nullable()->after('is_new_system');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferred_work_mode');
        });
    }
};
