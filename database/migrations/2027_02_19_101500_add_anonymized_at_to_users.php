<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Löschkonzept Personendaten (Feature 130, MVP-694 — Vollscan H21):
 * Anonymisierungs-Marker für ausgeschiedene Mitarbeiter. Gesetzt vom
 * UserAnonymizationService nach bestätigtem Retention-Vorschlag — der
 * Datensatz bleibt (Nachweis-FKs!), der Personenbezug ist reduziert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('left_at');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
    }
};
