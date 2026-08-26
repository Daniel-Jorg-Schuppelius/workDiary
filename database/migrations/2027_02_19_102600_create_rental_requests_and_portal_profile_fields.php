<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102600_create_rental_requests_and_portal_profile_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenportal-Ausbau (Vollscan G7/G10, MVP-712/714).
 *
 *  - `rental_requests`: zweiphasige Verleih-Anfrage aus dem Portal (Muster
 *    AppointmentRequest, Feature 087) — die Annahme erzeugt Akte/Vormerkung,
 *    die Anfrage selbst bleibt als Nachweis mit Entscheidung stehen.
 *  - `rental_profiles.portal_bookable`: Freigabe je Gerät fürs Portal-
 *    Sortiment, Default-Deny.
 *  - `users.portal_pending_email(_requested_at)`: schwebende E-Mail-Änderung
 *    eines Portalkontos bis zur Bestätigung über die neue Adresse.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('rental_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('portal_user_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('group_code', 60)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('requested');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->unsignedBigInteger('rental_reservation_id')->nullable();
            $table->unsignedBigInteger('rental_case_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'rental_requests_org_status_idx');
            $table->index(['customer_id', 'status'], 'rental_requests_customer_status_idx');

            $table->foreign('customer_id', 'rental_req_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('portal_user_id', 'rental_req_portal_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('asset_id', 'rental_req_asset_fk')->references('id')->on('assets')->nullOnDelete();
            $table->foreign('decided_by', 'rental_req_decider_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rental_reservation_id', 'rental_req_reservation_fk')->references('id')->on('rental_reservations')->nullOnDelete();
            $table->foreign('rental_case_id', 'rental_req_case_fk')->references('id')->on('rental_cases')->nullOnDelete();
        });

        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->boolean('portal_bookable')->default(false)->after('is_rentable');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('portal_pending_email', 191)->nullable()->after('portal_invited_at');
            $table->timestamp('portal_pending_email_requested_at')->nullable()->after('portal_pending_email');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['portal_pending_email', 'portal_pending_email_requested_at']);
        });

        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->dropColumn('portal_bookable');
        });

        Schema::dropIfExists('rental_requests');
    }
};
