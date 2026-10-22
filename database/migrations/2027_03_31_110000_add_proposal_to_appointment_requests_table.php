<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_110000_add_proposal_to_appointment_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ersatztermin vorschlagen (MVP-1287): die Disposition schlägt eine andere
 * Zeit vor, der Kunde nimmt sie im Portal an oder lehnt ab.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->timestamp('proposed_start_at')->nullable();
            $table->timestamp('proposed_end_at')->nullable();
            $table->string('proposal_note', 500)->nullable();
            $table->foreignId('proposer_user_id')->nullable()->constrained('users', indexName: 'apr_proposer_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropForeign('apr_proposer_fk');
            $table->dropColumn(['proposed_start_at', 'proposed_end_at', 'proposal_note', 'proposer_user_id']);
        });
    }
};
