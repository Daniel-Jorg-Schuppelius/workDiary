<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_24_110000_add_confidential_to_documents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vertraulichkeitsmerkmal für Dokumente (MVP-031 „Rechte für sensible
 * Dokumente"; Vollaudit 2026-07, N10) — Muster Kommunikationsnotizen:
 * vertrauliche Dokumente sehen nur Erfasser + document.confidential.manage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('confidential')->default(false)->after('customer_released_by');
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('confidential');
        });
    }
};
