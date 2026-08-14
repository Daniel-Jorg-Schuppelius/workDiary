<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_01_01_000000_create_wd_schema_probe_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fixture für PluginSchemaLifecycleTest (Review 2026-08, W6). */
return new class extends Migration {
    public function up(): void {
        Schema::create('wd_schema_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('value')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('wd_schema_probe');
    }
};
