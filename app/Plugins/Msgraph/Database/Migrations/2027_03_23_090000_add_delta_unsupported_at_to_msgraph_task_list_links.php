<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_23_090000_add_delta_unsupported_at_to_msgraph_task_list_links.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Befund 2026-10-18: Graph beantwortet die Delta-Abfrage mancher To-Do-Listen
 * mit 400 „Delta query is not supported by this resource" — der Stundenlauf
 * scheiterte dauerhaft. Der Marker merkt sich die Abweisung je Listen-Link;
 * der Import holt dann die volle Sicht und probiert Delta erst nach einer
 * Frist erneut (MsgraphTaskListLink::DELTA_RETRY_DAYS).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('msgraph_task_list_links', function (Blueprint $table): void {
            $table->timestamp('delta_unsupported_at')->nullable()->after('delta_link');
        });
    }

    public function down(): void {
        Schema::table('msgraph_task_list_links', function (Blueprint $table): void {
            $table->dropColumn('delta_unsupported_at');
        });
    }
};
