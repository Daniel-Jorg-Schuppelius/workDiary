<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_101300_add_profile_version_to_isms_norm_statuses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normprofil-Versionsmetadaten (Nachtrag 046a): beim Statusübergang wird
 * die Profilrevision/Stichtag eingefroren — die Konformitätsseite zeigt
 * „bewertet gegen Profilversion X" und warnt bei inzwischen neuerer
 * Profilrevision.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('isms_norm_statuses', function (Blueprint $table): void {
            $table->string('profile_version', 32)->nullable()->after('status');
            $table->date('profile_as_of')->nullable()->after('profile_version');
        });
    }

    public function down(): void {
        Schema::table('isms_norm_statuses', function (Blueprint $table): void {
            $table->dropColumn(['profile_version', 'profile_as_of']);
        });
    }
};
