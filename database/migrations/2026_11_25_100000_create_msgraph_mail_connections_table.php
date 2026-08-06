<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_25_100000_create_msgraph_mail_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microsoft-365-MAIL-Verbindung je Organisation (Feature 102, Graph-Mail-
 * Versand): genau EINE OAuth-Verbindung je Org (unique), Tokens verschlüsselt
 * at-rest (encrypted-Cast, APP_KEY), getrennt von Kalender-/Intake-/Backup-
 * Grants (eigener Scope-Satz `Mail.Send`). `from_address` erlaubt den
 * Shared-Mailbox-/Send-As-Absender; `save_to_sent_items` steuert die Ablage
 * im Gesendet-Ordner. Gesundheits-Spalten nach HasConnectionHealth-Standard
 * (MVP-178, Auto-Disable). Kurze FK-/Index-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('msgraph_mail_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('msgmc_org_unique')->constrained('organizations', indexName: 'msgmc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('account_label')->nullable();      // bestätigte Kontoidentität (/me)
            $table->string('from_address')->nullable();       // Shared-Mailbox/Send-As-Absender (Exchange-Recht nötig)
            $table->boolean('save_to_sent_items')->default(true);
            $table->string('status', 16)->default('active');  // active / disconnected
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_error', 300)->nullable();    // gekürzte Fehlerklasse/Meldung, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'msgmc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'msgmc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('msgraph_mail_connections');
    }
};
