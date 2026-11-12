<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_120100_add_dispute_to_accounting_open_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Offener Posten „strittig“ (MVP-1559): Zeitpunkt und Grund; wer es war, steht im Audit-Log. */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_open_items', function (Blueprint $table): void {
            $table->timestamp('disputed_at')->nullable()->after('settled_at');
            $table->string('dispute_note', 255)->nullable()->after('disputed_at');
        });
    }

    public function down(): void {
        Schema::table('accounting_open_items', function (Blueprint $table): void {
            $table->dropColumn(['disputed_at', 'dispute_note']);
        });
    }
};
