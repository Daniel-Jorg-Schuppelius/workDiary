<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_140000_add_procedure_backup_review_and_allow_blocked.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1566: Wer einen Backup-Nachweis erfasst hat und ob er abgelehnt wurde
 * (zweite Person bei Rückspieltest und Bestätigung durch die Leitung).
 * MVP-1571: Vorlagenversion darf an gesperrten Assets laufen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_backup_proofs', function (Blueprint $table): void {
            $table->foreignId('registered_by_user_id')->nullable()->after('external_ref')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('verified_by_user_id');
        });

        Schema::table('procedure_template_versions', function (Blueprint $table): void {
            $table->boolean('allow_blocked')->default(false)->after('applicability');
        });
    }

    public function down(): void {
        Schema::table('procedure_template_versions', function (Blueprint $table): void {
            $table->dropColumn('allow_blocked');
        });

        Schema::table('procedure_backup_proofs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registered_by_user_id');
            $table->dropColumn('rejected_at');
        });
    }
};
