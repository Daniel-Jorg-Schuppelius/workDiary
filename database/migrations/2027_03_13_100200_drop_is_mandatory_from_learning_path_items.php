<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_13_100200_drop_is_mandatory_from_learning_path_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lernpfad-Stationen sind immer Pflicht (Phase 137, E14): Das Kennzeichen
 * wirkte nirgends — eingeschrieben wurde in alle Stationen, der Fortschritt
 * zählte alle.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_path_items', function (Blueprint $table): void {
            $table->dropColumn('is_mandatory');
        });
    }

    public function down(): void {
        Schema::table('learning_path_items', function (Blueprint $table): void {
            $table->boolean('is_mandatory')->default(true)->after('position');
        });
    }
};
