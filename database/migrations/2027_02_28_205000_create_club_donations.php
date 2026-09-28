<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_205000_create_club_donations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1003: Spenden und Zuwendungsbestätigungen nach amtlichem Muster. */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_donation_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('receipt_no');
            $table->unsignedSmallInteger('year');
            $table->string('kind', 16);
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->json('donor_snapshot');
            $table->json('exemption_snapshot');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('issued_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'receipt_no'], 'club_don_rcpt_no_uq');
        });

        Schema::create('club_donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->string('donor_name', 200)->nullable();
            $table->text('donor_address')->nullable();
            $table->string('kind', 20)->default('donation');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('received_on');
            $table->boolean('is_expense_waiver')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('club_donation_receipt_id')->nullable()->constrained('club_donation_receipts', indexName: 'club_don_receipt_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'received_on'], 'club_don_org_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_donations');
        Schema::dropIfExists('club_donation_receipts');
    }
};
