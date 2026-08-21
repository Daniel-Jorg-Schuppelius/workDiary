<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_30_100000_create_customer_circulars_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenrundschreiben (Feature 119, MVP-608).
 *
 * Bewusst KEIN Newsletter-Werkzeug: keine Öffnungsraten, keine Zählpixel,
 * keine Kampagnenstrecken. Was hier entsteht, ist die
 * **Geschäftsmitteilung an Bestandskunden** — eine Information aus dem
 * Vertragsverhältnis, mit Nachweis je Empfänger.
 *
 * Der Nachweis (`customer_circular_recipients`) ist der Kern: „Wir haben Sie
 * im November informiert" ist ohne ihn wertlos.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_circulars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 191);
            $table->text('body');
            // Pflichtmitteilung (z. B. geänderte Bankverbindung) geht auch an
            // Kunden mit Werbe-Opt-out — sichtbar als bewusste Entscheidung.
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('portal_notice')->default(false);
            // Filterkriterien der Empfängerauswahl (Nachvollziehbarkeit)
            $table->json('filters')->nullable();
            // draft | sending | sent
            $table->string('status', 16)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'circular_org_status_idx');
        });

        Schema::create('customer_circular_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_circular_id')->constrained('customer_circulars')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('email', 191)->nullable();
            // pending | sent | skipped | failed
            $table->string('status', 16)->default('pending');
            $table->string('reason', 191)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('communication_note_id')->nullable()->constrained('communication_notes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_circular_id', 'customer_id'], 'circular_recipient_unq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_circular_recipients');
        Schema::dropIfExists('customer_circulars');
    }
};
