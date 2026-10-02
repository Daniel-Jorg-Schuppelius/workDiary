<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_29_100000_create_msgraph_contact_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microsoft-365-KONTAKT-Verbindung je Organisation (Feature 102, Schnitt D —
 * ContactSync): genau EINE OAuth-Verbindung je Org (unique), Tokens
 * verschlüsselt at-rest, fünfter Grant (`Contacts.ReadWrite`) getrennt von
 * Kalender/Intake/Backup/Mail. Push-only-Pilot: WorkDiary-Kunden →
 * Outlook-Kontakte des verbundenen Kontos (idempotent via ExternalReference).
 * Gesundheits-Spalten nach HasConnectionHealth-Standard (MVP-178).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('msgraph_contact_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('msgcc_org_unique')->constrained('organizations', indexName: 'msgcc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('account_label')->nullable();      // bestätigte Kontoidentität (/me)
            $table->string('status', 16)->default('active');  // active / disconnected
            $table->timestamp('last_pushed_at')->nullable();
            $table->string('last_error', 300)->nullable();    // gekürzte Fehlerklasse, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'msgcc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'msgcc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('msgraph_contact_connections');
    }
};
