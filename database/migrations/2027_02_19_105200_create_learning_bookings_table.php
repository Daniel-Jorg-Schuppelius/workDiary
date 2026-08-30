<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105200_create_learning_bookings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kursbuchung und Verkauf (Feature 149, MVP-744).
 *
 * Die Buchung geht der Einschreibung voraus: erst die Zusage, dann der
 * Zugang. Portal-Buchungen sind deshalb **zweiphasig** (Anfrage →
 * Bestätigung) wie die Terminbuchung aus Feature 087 — nie ein
 * Direktzugriff auf interne Kapazitäten.
 *
 * **Preis und Artikel werden bei der Bestätigung eingefroren.** Eine
 * spätere Preisänderung am Artikel darf eine erteilte Zusage nicht
 * nachträglich verteuern.
 *
 * Bewusst **keine automatische Rechnung**: die Rechnungshoheit kann bei
 * einem externen System liegen (Feature 066/110). Die Buchung markiert sich
 * als abrechenbar; fakturiert wird in einem eigenen Schritt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            // Wer bucht: Portal-/interner Nutzer, externe Person, oder ein
            // Kunde als Rechnungsempfänger (mehrere Plätze auf einmal).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('external_participant_id')->nullable()->constrained('external_participants')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // requested|confirmed|rejected|cancelled
            $table->string('status', 10)->default('requested');
            $table->unsignedSmallInteger('seats')->default(1);
            // Eingefrorene Preisangaben der Zusage.
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 500)->nullable();
            // Entsteht mit der Bestätigung.
            $table->foreignId('learning_enrollment_id')->nullable()->constrained('learning_enrollments')->nullOnDelete();
            // Abrechenbar heißt: bestätigt und mit Preis — nicht „fakturiert".
            $table->boolean('is_billable')->default(false);
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'lrn_booking_org_status_idx');
            $table->index(['learning_course_id', 'status'], 'lrn_booking_course_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_bookings');
    }
};
