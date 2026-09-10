<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101100_add_draft_reference_to_resale_periods.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (Review 2026-09-10, A4): Entwurfsstempel der Periode als
 * eigene Spalten statt in der Bemerkung — Lexoffice-Entwurfs-ID bzw. lokale
 * Rechnungsnummer und Zeitpunkt. Gestempelte offene Perioden kommen nicht
 * in einen zweiten Entwurf; ein entschiedener Bezug auf dieselbe Rechnung
 * löscht den Stempel (Entwurf wurde Rechnung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_periods', function (Blueprint $t): void {
            $t->string('draft_reference', 64)->nullable()->after('note');
            $t->timestamp('draft_created_at')->nullable()->after('draft_reference');
            $t->index(['organization_id', 'draft_reference'], 'resale_periods_org_draft_idx');
        });
    }

    public function down(): void {
        Schema::table('resale_periods', function (Blueprint $t): void {
            $t->dropIndex('resale_periods_org_draft_idx');
            $t->dropColumn(['draft_reference', 'draft_created_at']);
        });
    }
};
