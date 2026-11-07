<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_03_120000_create_external_invoices_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extern ausgestellte Rechnungen mit Nachweisen und WorkDiary-Versand (Feature 196,
 * Nachtrag 07.11.2026, MVP-1488): Stand und Original je Anbieterrechnung, Zuordnung
 * der eingefrorenen Übergabepositionen, unveränderliche Versandfassungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_invoices', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('plugin_id', 64);
            $t->string('external_id', 128);
            $t->string('external_number', 64)->nullable();
            // Freier Status des Anbieters; der normalisierte Stand steht in `status`.
            $t->string('remote_status_code', 32)->nullable();
            $t->string('status', 16)->default('unknown');
            $t->string('kind', 16)->default('invoice');
            $t->date('issued_on')->nullable();
            $t->char('currency', 3)->default('EUR');
            $t->decimal('net_amount', 15, 2)->nullable();
            $t->decimal('gross_amount', 15, 2)->nullable();
            $t->json('lines')->nullable();
            $t->char('lines_hash', 64)->nullable();
            $t->string('original_format', 16)->nullable();
            $t->string('original_path', 255)->nullable();
            $t->string('original_mime', 100)->nullable();
            $t->char('original_sha256', 64)->nullable();
            $t->timestamp('original_fetched_at')->nullable();
            $t->string('provider_send_state', 16)->default('unknown');
            $t->timestamp('provider_sent_at')->nullable();
            // Bestätigter Abgleich gilt nur für genau diesen Stand von Positionen und Zuordnung.
            $t->char('reconciled_hash', 64)->nullable();
            $t->timestamp('reconciled_at')->nullable();
            $t->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note', 500)->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->string('sync_error', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'plugin_id', 'external_id'], 'ext_inv_org_plugin_ext_unique');
            $t->index(['organization_id', 'customer_id'], 'ext_inv_org_customer_idx');
        });

        Schema::create('external_invoice_positions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('external_invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('billing_transfer_id')->constrained()->cascadeOnDelete();
            // Eine Position gehört zu höchstens einer externen Rechnung (geteilte Übergaben je Position).
            $t->foreignId('billing_transfer_position_id')->unique('ext_inv_pos_position_unique')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['external_invoice_id', 'billing_transfer_id'], 'ext_inv_pos_invoice_transfer_idx');
        });

        Schema::create('external_invoice_packages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('external_invoice_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16)->default('initial');
            $t->string('format', 16);
            $t->string('channel', 20);
            $t->string('recipient', 500);
            // Ordner der Versanddateien, damit die Löschung der Organisation sie findet.
            $t->string('storage_path', 255);
            $t->json('files');
            $t->char('sha256', 64);
            $t->char('original_sha256', 64)->nullable();
            $t->json('evidence')->nullable();
            $t->json('report')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['external_invoice_id', 'created_at'], 'ext_inv_pkg_invoice_created_idx');
        });

        Schema::table('invoice_supporting_documents', function (Blueprint $t): void {
            $t->foreignId('external_invoice_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
            $t->unique(['external_invoice_id', 'filename_key'], 'isd_external_filename_key_unique');
            $t->index(['external_invoice_id', 'position'], 'isd_external_position_idx');
        });
    }

    public function down(): void {
        Schema::table('invoice_supporting_documents', function (Blueprint $t): void {
            $t->dropUnique('isd_external_filename_key_unique');
            $t->dropIndex('isd_external_position_idx');
            $t->dropConstrainedForeignId('external_invoice_id');
        });
        Schema::dropIfExists('external_invoice_packages');
        Schema::dropIfExists('external_invoice_positions');
        Schema::dropIfExists('external_invoices');
    }
};
