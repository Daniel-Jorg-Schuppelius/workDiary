<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_11_100100_add_sharepoint_mirror_detached_to_documents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Spiegelung getrennt"-Marker des SharePoint-Zweigs (MVP-330, Bauturbo A10,
 * Konfliktauflösung Rang 18 — Pendant zu `webdav_mirror_detached`): einmal
 * getrennt, reiht der {@see \App\Plugins\Support\Mirror\Observers\MirrorDocumentObserver}
 * das Dokument für DIESES Ziel nie wieder automatisch ein; andere Ablage-Ziele
 * bleiben unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('sharepoint_mirror_detached')->default(false);
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('sharepoint_mirror_detached');
        });
    }
};
