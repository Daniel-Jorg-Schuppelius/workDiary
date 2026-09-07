<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100700_add_resale_role_to_lexoffice_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152: Einstufung je Lexoffice-Artikel für das Reselling-Register —
 * null = automatisch (Produkterkennung), 'license' = immer Abo-Produkt,
 * 'excluded' = nie Abo-Position (z. B. „Wartung Microsoft Exchange").
 * Lokale Spalte, der Artikel-Sync fasst sie nicht an.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_articles', function (Blueprint $t): void {
            $t->string('resale_role', 16)->nullable()->after('archived_at');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_articles', function (Blueprint $t): void {
            $t->dropColumn('resale_role');
        });
    }
};
