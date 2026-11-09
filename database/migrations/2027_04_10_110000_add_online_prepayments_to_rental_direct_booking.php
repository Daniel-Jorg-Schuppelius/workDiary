<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_10_110000_add_online_prepayments_to_rental_direct_booking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1515: Online-Vorauszahlung bei der Direktbuchung im Verleih. Eine
 * Online-Zahlung kann vor der Rechnung entstehen (Gegenstand statt Rechnung)
 * und wird später der Rechnung zugeordnet. Die Anfrage merkt sich Betrag und
 * Zahlungsfrist; das Mietobjekt kann vom Prozentsatz der Organisation abweichen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('online_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->string('subject_type', 60)->nullable()->after('invoice_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->index(['subject_type', 'subject_id'], 'online_payments_subject_idx');
        });

        Schema::table('rental_requests', function (Blueprint $table): void {
            $table->decimal('prepayment_amount', 15, 2)->nullable()->after('guests');
            $table->char('currency', 3)->nullable()->after('prepayment_amount');
            $table->timestamp('payment_due_at')->nullable()->after('currency');
            $table->index(['status', 'payment_due_at'], 'rental_requests_status_due_idx');
        });

        Schema::table('rental_profiles', function (Blueprint $table): void {
            // null = Vorgabe der Organisation, 0 = keine Vorauszahlung.
            $table->unsignedTinyInteger('prepayment_percent')->nullable()->after('portal_bookable');
        });
    }

    public function down(): void {
        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->dropColumn('prepayment_percent');
        });

        Schema::table('rental_requests', function (Blueprint $table): void {
            $table->dropIndex('rental_requests_status_due_idx');
            $table->dropColumn(['prepayment_amount', 'currency', 'payment_due_at']);
        });

        Schema::table('online_payments', function (Blueprint $table): void {
            $table->dropIndex('online_payments_subject_idx');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
