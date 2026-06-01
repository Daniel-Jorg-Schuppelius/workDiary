<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_07_120000_create_foreign_customers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fremdkunden (Endkunden): gehören zu einem Customer (Firma) und bilden dessen
 * eigene Kundschaft ab — z. B. beim Toggl-Import von Kunden-Workspaces. Bewusst
 * leichtgewichtig (Kontaktstammdaten, keine eigenen Sätze/Bankdaten); die
 * Abrechnung läuft über die Firma. Projekte verweisen optional per
 * `foreign_customer_id` auf einen Fremdkunden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('foreign_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('number', 64)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('contact_name', 200)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('mobile', 64)->nullable();
            $table->string('homepage')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('customer_id');
            $table->index('archived_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('foreign_customers');
    }
};
