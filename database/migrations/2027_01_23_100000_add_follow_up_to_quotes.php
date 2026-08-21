<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_23_100000_add_follow_up_to_quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angebots-Nachfassen (Feature 112, MVP-601).
 *
 * `valid_until` ist eine Rechtsfrist, kein Vertriebstermin — deshalb ein
 * eigenes Feld. Der Index deckt den Scanner-Zugriff („fällig und noch
 * offen") ab; kurzer expliziter Name wegen der 64-Zeichen-Grenze in MySQL.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->date('follow_up_at')->nullable()->after('valid_until');
            $table->foreignId('follow_up_user_id')->nullable()->after('follow_up_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('followed_up_at')->nullable()->after('follow_up_user_id');
            $table->index(['organization_id', 'follow_up_at'], 'quotes_org_followup_idx');
        });
    }

    public function down(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex('quotes_org_followup_idx');
            $table->dropConstrainedForeignId('follow_up_user_id');
            $table->dropColumn(['follow_up_at', 'followed_up_at']);
        });
    }
};
