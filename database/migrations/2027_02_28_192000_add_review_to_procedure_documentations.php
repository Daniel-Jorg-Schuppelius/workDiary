<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_192000_add_review_to_procedure_documentations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-995: Vorlage zur Freigabe (Vier-Augen) einer Verfahrensdokumentations-Fassung. */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_documentations', function (Blueprint $table): void {
            $table->foreignId('submitter_user_id')->nullable()->after('published_by')
                ->constrained('users', indexName: 'proc_doc_submitter_fk')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitter_user_id');
            $table->string('review_note', 500)->nullable()->after('submitted_at');
        });
    }

    public function down(): void {
        Schema::table('procedure_documentations', function (Blueprint $table): void {
            $table->dropForeign('proc_doc_submitter_fk');
            $table->dropColumn(['submitter_user_id', 'submitted_at', 'review_note']);
        });
    }
};
