<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_120000_extend_invoices_for_cancel_credit_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            // Stornierung
            $table->timestamp('cancelled_at')->nullable()->after('paid_on');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            // Korrekturrechnung / Gutschrift
            $table->string('type', 16)->default('invoice')->after('status'); // invoice | credit_note
            $table->foreignId('parent_invoice_id')->nullable()->after('type')
                ->constrained('invoices')->nullOnDelete();

            // Mailversand
            $table->timestamp('sent_at')->nullable()->after('cancel_reason');
            $table->unsignedInteger('sent_count')->default(0)->after('sent_at');

            $table->index('type');
            $table->index('parent_invoice_id');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_invoice_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason', 'type', 'sent_at', 'sent_count']);
        });
    }
};
