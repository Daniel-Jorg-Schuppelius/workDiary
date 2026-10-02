<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_11_100000_create_sharepoint_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SharePoint-Ablage je Organisation (MVP-330, Bauturbo A10): genau EINE
 * OAuth-Verbindung je Org (unique), Tokens verschlüsselt at-rest
 * (encrypted-Cast, APP_KEY). Ziel = Site + Dokumentbibliothek (Drive) über
 * Microsoft Graph; `folder_map`/`default_folder`/`sources` wie die
 * WebDAV-Ablage (gleiche Pfadlogik). Gesundheits-Spalten nach dem
 * HasConnectionHealth-Standard (MVP-178, Auto-Disable). Kurze, explizite
 * FK-/Index-Namen (MySQL-64-Zeichen-Limit, FK-Namen DB-weit eindeutig).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sharepoint_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('spc_org_unique')->constrained('organizations', indexName: 'spc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('site_id', 512)->nullable();   // Graph-Site-IDs sind lang (host,guid,guid)
            $table->string('site_name')->nullable();
            $table->string('drive_id', 512)->nullable();  // Dokumentbibliothek (Drive)
            $table->string('drive_name')->nullable();
            $table->string('default_folder')->default('Dokumente');
            $table->json('folder_map')->nullable();       // { document_type: relativer Ordner }
            $table->json('sources')->nullable();          // document / invoice_pdf / protocol_pdf
            $table->boolean('active')->default(true);     // Spiegelung pausierbar ohne Token-Verlust
            $table->string('status', 16)->default('active'); // active / disconnected (OAuth-Zustand)
            $table->timestamp('last_mirrored_at')->nullable();
            $table->string('last_error', 300)->nullable();   // gekürzte Fehlerklasse/Meldung, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'spc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'spc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sharepoint_connections');
    }
};
