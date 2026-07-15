<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_18_100000_create_cloud_intake_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloud-Dokumenteingang (Feature 080, MVP-352): Verbindungen, priorisierte
 * Ordnerregeln und Übergabenachweise. Additiv, org-gescopt; kurze explizite
 * Index-/FK-Namen (MySQL-64-Zeichen-Limit, FK-Präfixe DB-weit eindeutig:
 * `cdc_`/`cdr_`/`cdi_`).
 *
 * Trennung der Importverbindung löscht Nachweise/Dokumente NICHT (Konzept
 * §Produktentscheidung) — deshalb KEIN cascadeOnDelete von Items auf die
 * Verbindung, sondern nullOnDelete + erhaltener Nachweis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cloud_document_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('provider', 16); // dropbox | microsoft | google
            $table->string('name', 190);

            // Bestätigte Kontoidentität (nach OAuth-Callback angezeigt/bestätigt).
            $table->string('external_account_id', 190)->nullable();
            $table->string('external_account_label', 190)->nullable();

            // Tokens verschlüsselt; Scopes zur Anzeige/Preflight-Prüfung.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();

            // Quell-Container + Stammordner (IDs sind Identität, Pfad Anzeige).
            $table->string('container_id', 512)->nullable();
            $table->string('container_label', 190)->nullable();
            $table->string('root_folder_id', 512)->nullable();
            $table->string('root_folder_path', 1024)->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('checkpoint', 2048)->nullable();
            $table->timestamp('last_run_at')->nullable();

            // Webhook-/Subscription-Verwaltung (optionales Aufwecksignal).
            $table->string('subscription_id', 190)->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('webhook_secret', 190)->nullable();

            // Health-Standard (HasConnectionHealth).
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'provider'], 'cdc_org_provider_idx');
        });

        Schema::create('cloud_document_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('cloud_document_connections', indexName: 'cdr_connection_fk')->cascadeOnDelete();

            $table->unsignedSmallInteger('priority')->default(100);
            // Pfadmuster relativ zum Stammordner: `**`-Glob + Variablen
            // ({customer_number}, {project_number}, {order_number},
            // {asset_number}, {contract_number}).
            $table->string('path_pattern', 512);
            $table->json('allowed_extensions')->nullable();
            $table->unsignedBigInteger('max_file_size')->nullable();

            $table->string('target', 32); // incoming_invoice | document
            $table->string('document_type', 64)->nullable();
            // Optionale feste Zielreferenz (statt Pfadvariable).
            $table->nullableMorphs('target_ref', 'cdr_target_ref_idx');
            // Nur ausdrücklich freigegebene Routen dürfen automatisch eine
            // neue DMS-Version anlegen; Default ist Versionsvorschlag → Inbox.
            $table->boolean('auto_version')->default(false);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['connection_id', 'priority'], 'cdr_conn_priority_idx');
        });

        Schema::create('cloud_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Nachweis überlebt die Trennung der Verbindung (nullOnDelete).
            $table->foreignId('connection_id')->nullable()->constrained('cloud_document_connections', indexName: 'cdi_connection_fk')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('cloud_document_routes', indexName: 'cdi_route_fk')->nullOnDelete();

            $table->string('provider', 16);
            $table->string('external_item_id', 512);
            $table->string('revision', 190);
            $table->string('source_path', 1024);
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->string('status', 20); // imported | inbox | rejected | duplicate | source_gone
            $table->string('status_reason', 300)->nullable();

            // Zielbezug nach erfolgreicher Übernahme.
            $table->string('target', 32)->nullable();
            $table->nullableMorphs('imported', 'cdi_imported_idx'); // Document/IncomingInvoice/DocumentVersion
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            // Unique je Org+Verbindung+Item+Revision (Konzept §Datenmodell).
            // external_item_id ist zu lang für den MySQL-Index → Hash-Spalte.
            $table->string('item_revision_hash', 64);
            $table->unique(['organization_id', 'connection_id', 'item_revision_hash'], 'cdi_org_conn_itemrev_uq');
            $table->index(['organization_id', 'sha256'], 'cdi_org_sha_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cloud_document_items');
        Schema::dropIfExists('cloud_document_routes');
        Schema::dropIfExists('cloud_document_connections');
    }
};
