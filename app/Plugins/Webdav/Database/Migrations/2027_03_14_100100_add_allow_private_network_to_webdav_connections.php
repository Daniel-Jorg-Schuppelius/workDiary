<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_100100_add_allow_private_network_to_webdav_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 137 (E22): auditierte Freigabe privater Adressen je WebDAV-Ablage
 * (Muster CardDAV) — für eine Nextcloud im eigenen Netz. Wirkt nur, wenn der
 * Betreiber das Opt-in zulässt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('webdav_connections', function (Blueprint $table): void {
            $table->boolean('allow_private_network')->default(false);
        });
    }

    public function down(): void {
        Schema::table('webdav_connections', function (Blueprint $table): void {
            $table->dropColumn('allow_private_network');
        });
    }
};
