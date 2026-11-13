<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_100000_add_draft_status_and_version_to_form_submissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Feature 203 (MVP-1650): Formulareinsendungen kennen einen Entwurf, der je
 * Feld gespeichert wird. `status` (Entwurf/abgesendet) und `version` für die
 * Konfliktprüfung; `submitted_at` steht erst beim Absenden. Bestehende
 * Einsendungen gelten als abgesendet (Vorgabe der Spalte).
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('form_submissions', 'status')) {
            Schema::table('form_submissions', function (Blueprint $table): void {
                $table->string('status', 20)->default('submitted')->after('values');
                $table->unsignedInteger('version')->default(1)->after('status');
                $table->index(['organization_id', 'status', 'submitted_by_user_id'], 'form_sub_org_status_user_idx');
            });
        }

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->change();
        });
    }

    public function down(): void {
        // Entwürfe haben keinen Absendezeitpunkt; vor dem Zurücksetzen fallen sie weg.
        DB::table('form_submissions')->whereNull('submitted_at')->delete();

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable(false)->change();
        });

        if (Schema::hasColumn('form_submissions', 'status')) {
            Schema::table('form_submissions', function (Blueprint $table): void {
                $table->dropIndex('form_sub_org_status_user_idx');
                $table->dropColumn(['status', 'version']);
            });
        }
    }
};
