<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100900_add_vacation_two_stage_and_deputy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-523 (Feature 103): mehrstufige Urlaubs-Genehmigung (Vier-Augen,
 * org-konfigurierbar) + Stellvertreter-Regelung. `first_approved_*` trägt
 * die erste Freigabe der zweistufigen Genehmigung; `users.deputy_user_id`
 * benennt die Vertretung, die während einer Abwesenheit des Genehmigers
 * dessen Urlaubs-Entscheidungen übernehmen darf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vacations', function (Blueprint $table): void {
            $table->foreignId('first_approved_by')->nullable()->after('decided_at')
                ->constrained('users', indexName: 'vac_first_appr_fk')->nullOnDelete();
            $table->timestamp('first_approved_at')->nullable()->after('first_approved_by');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('deputy_user_id')->nullable()
                ->constrained('users', indexName: 'users_deputy_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('vacations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('first_approved_by');
            $table->dropColumn('first_approved_at');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deputy_user_id');
        });
    }
};
