<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_100000_add_group_filter_and_private_network_to_zammad_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 137 (E18, E22): `is_limited_to_mapped_groups` beschränkt den Import auf
 * Gruppen mit Projektzuordnung (Vorgabe aus = alle Tickets, die das Token
 * sieht); `allow_private_network` ist die auditierte Freigabe privater Adressen
 * (Muster CardDAV, wirkt nur mit Betreiber-Zulassung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->boolean('is_limited_to_mapped_groups')->default(false);
            $table->boolean('allow_private_network')->default(false);
        });
    }

    public function down(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->dropColumn(['is_limited_to_mapped_groups', 'allow_private_network']);
        });
    }
};
