<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103500_create_privacy_dsar_portal_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betroffenen-Selbstmeldeportal (Feature 043, MVP-728; Vollscan 2026-08-23, G11).
 *
 * Eine Portalkonfiguration je Organisation — analog `whistleblowing_portals`:
 * `public_slug` ist bewusst NICHT aus dem Org-Slug ableitbar, damit der Link
 * unabhaengig rotiert und deaktiviert werden kann. Default ist AUS
 * (Default-Deny); ein unbekannter oder deaktivierter Slug liefert 404.
 *
 * Die Anfrage selbst bleibt der bestehende {@see \App\Models\Privacy\DataSubjectRequest}
 * (Quelle `channel = portal`); hier kommen nur die beiden Portal-Felder dazu:
 * die DEK-verschluesselte Kontaktadresse fuer Eingangsbestaetigung/Rueckfrage
 * und der Zeitpunkt, zu dem diese Adresse per Link bestaetigt wurde.
 *
 * KEIN Fristfeld: die Frist (Art. 12 Abs. 3 DSGVO) laeuft ab Eingang und liegt
 * bereits in `received_at`/`deadline_at` — die Adressbestaetigung verschiebt
 * sie bewusst nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_dsar_portals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('public_slug', 64);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('allow_attachments')->default(true);
            $table->text('intro_text')->nullable();
            $table->string('default_locale', 10)->nullable();
            $table->timestamps();

            $table->unique('public_slug', 'pdp_slug_uq');
            $table->unique('organization_id', 'pdp_org_uq');
        });

        Schema::table('privacy_data_subject_requests', function (Blueprint $table): void {
            // Kontaktadresse des Antragstellers: DEK-verschluesselt (text!),
            // damit sie am Crypto-Shredding des Falls teilnimmt.
            $table->text('contact_email_ciphertext')->nullable()->after('subject_ciphertext');
            $table->timestamp('contact_email_confirmed_at')->nullable()->after('identity_verified_at');
        });
    }

    public function down(): void {
        Schema::table('privacy_data_subject_requests', function (Blueprint $table): void {
            $table->dropColumn(['contact_email_ciphertext', 'contact_email_confirmed_at']);
        });
        Schema::dropIfExists('privacy_dsar_portals');
    }
};
