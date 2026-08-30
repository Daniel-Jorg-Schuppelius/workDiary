<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105000_link_learning_units_to_events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Präsenz und Blended Learning (Feature 149, MVP-741).
 *
 * Ein Präsenztermin ist ein **Termin** (Feature 028), kein neues Objekt:
 * `events` trägt bereits `EventType::Training`, `max_participants`,
 * `is_mandatory` und `certificate_valid_months`, `event_participants` den
 * Status bis hin zu `attended`. Die Lerneinheit bekommt hier nur den
 * Zeiger darauf — es entsteht **kein zweiter Terminkalender**.
 *
 * Anmeldefrist und Absagefrist stehen an der Lerneinheit, weil sie zur
 * Kursorganisation gehören und nicht zum Termin selbst (derselbe Termin
 * kann in mehreren Kursen auftauchen).
 *
 * Der Check-in ist bewusst KEIN Feld hier: Anwesenheit ist ein Attribut der
 * Teilnahme (`event_participants.status = attended`), nicht der Einheit —
 * sonst gäbe es zwei Wahrheiten darüber, wer da war.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_units', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('learning_section_id')->constrained('events')->nullOnDelete();
            // Anmeldeschluss und Absagefrist in Stunden vor Terminbeginn.
            $table->unsignedSmallInteger('registration_lead_hours')->nullable()->after('event_id');
            $table->unsignedSmallInteger('cancellation_lead_hours')->nullable()->after('registration_lead_hours');
        });
    }

    public function down(): void {
        Schema::table('learning_units', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_lead_hours', 'registration_lead_hours']);
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
