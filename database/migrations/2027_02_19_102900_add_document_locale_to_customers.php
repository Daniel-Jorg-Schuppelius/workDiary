<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102900_add_document_locale_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belegsprache je Kunde (Feature 034, MVP-721; Vollscan 2026-08-23, H19):
 * NULL = Sprache der Organisation. Wirkt nur auf die Darstellung von
 * Rechnung/Angebot/AB/Mahnung/Lieferschein und den Belegversand — Snapshots,
 * Hash-Ketten und tax_context bleiben unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('document_locale', 5)->nullable()->after('timezone');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('document_locale');
        });
    }
};
