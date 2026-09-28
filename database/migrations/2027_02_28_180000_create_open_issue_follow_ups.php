<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_180000_create_open_issue_follow_ups.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** MVP-991: beliebig viele Folgeaufträge je offenem Punkt statt einer FK-Spalte. */
return new class extends Migration {
    public function up(): void {
        Schema::create('open_issue_follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'oi_follow_org_fk')->cascadeOnDelete();
            $table->foreignId('open_issue_id')->constrained('open_issues', indexName: 'oi_follow_issue_fk')->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->constrained('diary_entries', indexName: 'oi_follow_entry_fk')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'oi_follow_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['open_issue_id', 'diary_entry_id'], 'oi_follow_issue_entry_uq');
        });

        DB::table('open_issues')->whereNotNull('follow_up_diary_entry_id')->orderBy('id')->each(static function (object $issue): void {
            DB::table('open_issue_follow_ups')->insert([
                'organization_id' => $issue->organization_id,
                'open_issue_id' => $issue->id,
                'diary_entry_id' => $issue->follow_up_diary_entry_id,
                'created_at' => $issue->updated_at,
                'updated_at' => $issue->updated_at,
            ]);
        });

        Schema::table('open_issues', function (Blueprint $table): void {
            // SQLite kennt kein Löschen per FK-Namen und baut die Tabelle um; MySQL braucht den Namen.
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropConstrainedForeignId('follow_up_diary_entry_id');
            } else {
                $table->dropForeign('open_issues_follow_up_fk');
                $table->dropColumn('follow_up_diary_entry_id');
            }
        });
    }

    public function down(): void {
        Schema::table('open_issues', function (Blueprint $table): void {
            $table->foreignId('follow_up_diary_entry_id')->nullable()->after('closed_reason')
                ->constrained('diary_entries', indexName: 'open_issues_follow_up_fk')->nullOnDelete();
        });
        DB::table('open_issue_follow_ups')->orderBy('id')->each(static function (object $link): void {
            DB::table('open_issues')->where('id', $link->open_issue_id)->whereNull('follow_up_diary_entry_id')
                ->update(['follow_up_diary_entry_id' => $link->diary_entry_id]);
        });
        Schema::dropIfExists('open_issue_follow_ups');
    }
};
