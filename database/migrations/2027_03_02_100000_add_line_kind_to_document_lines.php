<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_02_100000_add_line_kind_to_document_lines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1054: Zeilenart je Belegposition (Position, Titel, Text, Alternative). */
return new class extends Migration {
    public function up(): void {
        foreach (['invoice_items', 'quote_items', 'invoice_schedule_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('line_kind', 16)->default('item');
            });
        }
    }

    public function down(): void {
        foreach (['invoice_items', 'quote_items', 'invoice_schedule_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('line_kind');
            });
        }
    }
};
