<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_210100_add_survey_invitation_to_learning_unit_progress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umfrage-Einheit (Feature 149, MVP-1600): die Einladung, über die eine Person
 * den Fragebogen der Einheit öffnet. Der Bezug liegt hier und nicht an der
 * Antwort — anonyme Antworten bleiben ohne Personenbezug.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_unit_progress', function (Blueprint $table): void {
            $table->foreignId('survey_invitation_id')->nullable()->after('progress_percent')
                ->constrained('survey_invitations')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('learning_unit_progress', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('survey_invitation_id');
        });
    }
};
