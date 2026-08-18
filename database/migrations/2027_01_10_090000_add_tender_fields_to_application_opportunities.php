<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_090000_add_tender_fields_to_application_opportunities.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vergabevorgang für öffentliche Ausschreibungen (MVP-625).
 *
 * Ein Vergabevorgang ist mehr als eine Verkaufschance: Die **Vergabestelle** ist
 * nicht der Kunde (die Kommune schreibt aus, das Bauamt zahlt), das Verfahren
 * hat eine eigene Nummer und Art, und die Fristen entscheiden über Teilnahme
 * oder Ausschluss.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('application_opportunities', function (Blueprint $table): void {
            // Die Vergabestelle schreibt aus - sie ist nicht zwingend der
            // spätere Auftraggeber und schon gar nicht der Kunde im CRM.
            $table->string('awarding_body')->nullable()->after('customer_id');
            $table->string('procedure_no', 60)->nullable()->after('awarding_body');
            $table->string('procedure_type', 40)->nullable()->after('procedure_no');
            // Ober- oder unterschwellig: Davon hängt ab, welches Regelwerk gilt
            // und welche Verfahrensarten überhaupt zulässig sind.
            $table->boolean('above_threshold')->default(false)->after('procedure_type');

            $table->string('lot_no', 40)->nullable()->after('above_threshold');
            $table->string('lot_group', 60)->nullable()->after('lot_no');

            // CPV (Leistungsgegenstand) und NUTS (Ort) sind die beiden Schlüssel,
            // nach denen Bekanntmachungen durchsucht werden.
            $table->json('cpv_codes')->nullable()->after('lot_group');
            $table->string('nuts_code', 10)->nullable()->after('cpv_codes');

            $table->string('platform', 80)->nullable()->after('nuts_code');
            $table->string('external_reference', 120)->nullable()->after('platform');
            $table->text('notice_url')->nullable()->after('external_reference');

            // Fristenset: Teilnahme und Bindefrist fehlten bisher ganz.
            $table->date('participation_deadline')->nullable()->after('question_deadline');
            $table->dateTime('opening_at')->nullable()->after('submission_deadline');
            $table->date('binding_until')->nullable()->after('opening_at');

            $table->index(['organization_id', 'submission_deadline'], 'aopp_org_subm_idx');
            $table->index(['organization_id', 'binding_until'], 'aopp_org_bind_idx');
        });
    }

    public function down(): void {
        Schema::table('application_opportunities', function (Blueprint $table): void {
            $table->dropIndex('aopp_org_subm_idx');
            $table->dropIndex('aopp_org_bind_idx');
            $table->dropColumn([
                'awarding_body', 'procedure_no', 'procedure_type', 'above_threshold',
                'lot_no', 'lot_group', 'cpv_codes', 'nuts_code',
                'platform', 'external_reference', 'notice_url',
                'participation_deadline', 'opening_at', 'binding_until',
            ]);
        });
    }
};
