<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_100000_scope_holiday_and_tag_uniques_to_organization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vollscan 2026-08-23 (F2/F3): holidays(date, is_recurring) und tags.slug waren
 * DB-weit unique, obwohl beide Modelle org-gescopt sind — die zweite
 * Organisation bekam beim gleichen Betriebsfeiertag ein 1062, gleichnamige
 * Tags wichen per Tenant-Bypass auf „wichtig-2" aus. Die Uniques gelten jetzt
 * je Organisation; bestehende „-2"-Slugs bleiben gültig.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropUnique('holidays_date_is_recurring_unique');
            $table->unique(['organization_id', 'date', 'is_recurring'], 'hol_org_date_rec_unique');
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique('tags_slug_unique');
            $table->unique(['organization_id', 'slug'], 'tags_org_slug_unique');
        });
    }

    public function down(): void {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique('tags_org_slug_unique');
            $table->unique('slug', 'tags_slug_unique');
        });

        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropUnique('hol_org_date_rec_unique');
            $table->unique(['date', 'is_recurring'], 'holidays_date_is_recurring_unique');
        });
    }
};
