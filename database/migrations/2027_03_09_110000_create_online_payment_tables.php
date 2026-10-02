<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_110000_create_online_payment_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1067: Online-Zahlung von Rechnungen. Der Zahlungslink ist je Rechnung
 * stabil (PDF, Mail und Portal rendern ihn jederzeit neu — daher verschlüsselt
 * statt nur als Abdruck); jede Bezahlseite beim Anbieter ist eine Zahlung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_payment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });

        Schema::create('online_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Zahlungen bleiben, solange die Rechnung besteht — ausgestellte Rechnungen werden nicht gelöscht.
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('provider_reference', 191)->nullable();
            $table->string('status', 20)->default('open');
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->char('currency', 3);
            $table->string('method', 40)->nullable();
            $table->string('checkout_url', 2048)->nullable();
            $table->timestamp('checkout_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('online_payments');
        Schema::dropIfExists('invoice_payment_links');
    }
};
