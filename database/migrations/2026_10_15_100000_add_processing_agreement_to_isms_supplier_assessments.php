<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_15_100000_add_processing_agreement_to_isms_supplier_assessments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AVV-Kopplung ISMS-Lieferantenbewertung (Feature 044, Welle D): additiver,
 * nullable FK von der ISMS-Lieferantenbewertung auf den
 * Auftragsverarbeitungsvertrag (AVV, Feature 043 / privacy_processing_agreements).
 *
 * Ersetzt den bisher BEWUSST losen Bezug (Flag has_dpa + Freitext dpa_ref) durch
 * eine strukturierte, optionale Verknüpfung — die Freitext-Felder bleiben als
 * Fallback erhalten. Die Org-Konsistenz (verknüpftes AVV muss derselben
 * Organisation gehören) wird zusätzlich im Service/Request validiert, nicht nur
 * über den DB-FK. nullOnDelete: Wird das AVV gelöscht, bleibt die Bewertung
 * bestehen und verliert nur die Verknüpfung.
 *
 * Reihenfolge: liegt bewusst NACH 2026_08_13_000022 (privacy_processing_agreements)
 * und 2026_06_14_260000 (isms_supplier_assessments), damit der FK auf eine
 * bereits existierende Zieltabelle zeigt (MySQL errno 150 / Portabilität).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('isms_supplier_assessments', function (Blueprint $table): void {
            $table->foreignId('processing_agreement_id')
                ->nullable()
                ->after('dpa_ref')
                ->constrained('privacy_processing_agreements')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('isms_supplier_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('processing_agreement_id');
        });
    }
};
