<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_12_100000_create_webdav_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WebDAV-Ablage je Organisation (Feature 058, MVP-127): Ziel-URL der Collection,
 * Zugangsdaten (App-Passwort, verschlüsselt at-rest, APP_KEY!) und die Regel
 * Dokumenttyp→Zielordner (`folder_map`, sonst `default_folder`). WorkDiary
 * spiegelt freigegebene DMS-Dokumente dorthin (Übergabenachweis via
 * ExternalReference); `last_mirrored_at` trägt den letzten Spiegel-Zeitpunkt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('webdav_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'webdavconn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');                 // Collection-Root, z. B. https://cloud.example.com/remote.php/dav/files/svc/WorkDiary
            $table->string('username');
            $table->text('app_password');               // encrypted at-rest
            $table->string('default_folder')->default('Dokumente');
            $table->json('folder_map')->nullable();     // { document_type: relativer Ordner }
            $table->boolean('active')->default(true);
            $table->timestamp('last_mirrored_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'webdavconn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'webdavconn_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('webdav_connections');
    }
};
