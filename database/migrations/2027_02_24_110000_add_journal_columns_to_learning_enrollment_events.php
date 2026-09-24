<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_24_110000_add_journal_columns_to_learning_enrollment_events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-864 Journal-Baustein: Einschreibungsereignisse bekommen die
 * Mindestspalten `event` und `payload`; bisherige Zeilen sind Statuswechsel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_enrollment_events', function (Blueprint $table): void {
            $table->string('event', 64)->default('status_changed')->after('learning_enrollment_id');
            $table->json('payload')->nullable()->after('reason');
        });
    }

    public function down(): void {
        Schema::table('learning_enrollment_events', function (Blueprint $table): void {
            $table->dropColumn(['event', 'payload']);
        });
    }
};
