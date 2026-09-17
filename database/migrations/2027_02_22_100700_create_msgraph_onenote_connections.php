<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100700_create_msgraph_onenote_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OneNote-Übernahme (Feature 155, MVP-815): eine lesende Graph-Verbindung je
 * Organisation mit eigenem Grant (`Notes.Read`), getrennt von Kalender, Mail,
 * Kontakten, Aufgaben und Dokumenteingang.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('msgraph_onenote_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('msgon_org_unique')->constrained('organizations', indexName: 'msgon_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('account_label')->nullable();
            $table->string('status', 16)->default('active'); // active / disconnected
            $table->timestamp('last_import_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'msgon_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'msgon_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('msgraph_onenote_connections');
    }
};
