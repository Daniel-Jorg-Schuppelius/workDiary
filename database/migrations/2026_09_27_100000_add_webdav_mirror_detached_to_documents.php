<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_27_100000_add_webdav_mirror_detached_to_documents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marker „WebDAV-Spiegelung für dieses Dokument getrennt" (Feature 058, MVP-127,
 * Rang 18). Wird bei der Konfliktauflösung „Spiegelung trennen" gesetzt, damit
 * der {@see \App\Plugins\Support\Mirror\Observers\MirrorDocumentObserver} das Dokument
 * nicht erneut in die Outbox einreiht (sonst wäre der Detach wirkungslos). Nur
 * dieses eine Dokument ist betroffen — die Anbindung bleibt aktiv.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('webdav_mirror_detached')->default(false)->after('current_version_id');
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('webdav_mirror_detached');
        });
    }
};
