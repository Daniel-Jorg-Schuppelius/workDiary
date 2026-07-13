<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_10_100000_create_carddav_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CardDAV-Lesegateway je Organisation (Bauturbo A9, MVP-329):
 *
 * - `carddav_connections`: Basis-URL, Zugangsdaten (App-Passwort verschlüsselt
 *   at-rest, APP_KEY!), gewähltes Adressbuch (RFC-6764-Discovery), Sync-Token
 *   (RFC 6578) sowie die Standard-Gesundheitsspalten (HasConnectionHealth).
 *   `allow_private_network` ist das auditierte SSRF-Opt-in für self-hosted
 *   Server im eigenen Netz (Muster JTL-Wawi).
 * - `carddav_cards`: lokaler Sync-Spiegel (href → UID/ETag) je Verbindung —
 *   Grundlage für den ETag-Fallback (Server ohne sync-collection) und die
 *   Idempotenz (unveränderte Karten überspringen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('carddav_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'carddavconn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');                     // z. B. https://cloud.example.com/remote.php/dav
            $table->string('username');
            $table->text('app_password');                   // encrypted at-rest
            $table->string('addressbook_url')->nullable();  // per Discovery gewähltes Adressbuch (absolute URL)
            $table->string('addressbook_name')->nullable();
            $table->text('sync_token')->nullable();         // RFC-6578-Sync-Token (kann URI-Form haben)
            $table->boolean('allow_private_network')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'carddavconn_creator_fk')->nullOnDelete();
            // Standard-Gesundheitsspalten (Feature 067, MVP-178).
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index('organization_id', 'carddavconn_org_idx');
        });

        Schema::create('carddav_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'carddavcard_org_fk')->cascadeOnDelete();
            $table->foreignId('carddav_connection_id')->constrained('carddav_connections', indexName: 'carddavcard_conn_fk')->cascadeOnDelete();
            $table->string('href');            // Objekt-URI (Pfad) innerhalb des Adressbuchs
            $table->string('uid')->nullable(); // vCard-UID (stabile Fremd-ID fürs Matching)
            $table->string('etag');
            $table->timestamps();

            $table->unique(['carddav_connection_id', 'href'], 'carddavcard_conn_href_uq');
            $table->index('organization_id', 'carddavcard_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carddav_cards');
        Schema::dropIfExists('carddav_connections');
    }
};
