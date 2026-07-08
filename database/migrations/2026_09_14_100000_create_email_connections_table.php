<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_14_100000_create_email_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMAP-Eingangspostfach je Organisation (Feature 056, MVP-117): eingehende
 * Mails werden über den Scheduler abgerufen und landen als Vorschläge in der
 * Integrations-Inbox. Zugangsdaten (Passwort) sind at-rest verschlüsselt
 * (`encrypted`-Cast, APP_KEY!). `processed_folder` = optionaler Zielordner, in
 * den verarbeitete Mails verschoben werden (nie gelöscht).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('email_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'emailconn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 8)->default('ssl');   // ssl|tls|none
            $table->string('username');
            $table->text('password');                            // encrypted at-rest
            $table->string('folder')->default('INBOX');
            $table->string('processed_folder')->nullable();      // verarbeitete Mails verschieben (nie löschen)
            $table->boolean('active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'emailconn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'emailconn_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('email_connections');
    }
};
