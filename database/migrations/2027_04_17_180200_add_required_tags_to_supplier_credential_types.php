<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_180200_add_required_tags_to_supplier_credential_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pflicht je Gewerk/Leistungsart (Feature 117, MVP-1586): ein eigener
 * Nachweistyp kann auf Lieferanten mit bestimmten Tags begrenzt sein. Leer =
 * Pflicht für alle Lieferanten, wie bisher.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_credential_types', function (Blueprint $table): void {
            $table->json('required_tag_ids')->nullable()->after('is_required_default');
        });
    }

    public function down(): void {
        Schema::table('supplier_credential_types', function (Blueprint $table): void {
            $table->dropColumn('required_tag_ids');
        });
    }
};
