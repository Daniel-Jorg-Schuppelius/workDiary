<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_280100_add_external_author_to_comments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\ExternalParticipant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externe Kommentare (Feature 033): ein login-freier externer Beteiligter
 * besitzt keinen internen User. Damit seine Kommentare im Subject-Thread
 * erscheinen und dem Externen zugeordnet bleiben, wird `comments.user_id`
 * nullable und eine optionale Rückverknüpfung auf den ExternalParticipant
 * ergänzt. Rein additiv — bestehende internen Kommentare bleiben unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('comments', function (Blueprint $table): void {
            // user_id nullable: externe Kommentare haben keinen internen Autor.
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignIdFor(ExternalParticipant::class, 'external_participant_id')
                ->nullable()
                ->after('user_id')
                ->constrained('external_participants')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('external_participant_id');
        });
    }
};
