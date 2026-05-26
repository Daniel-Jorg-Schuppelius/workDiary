<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_13_120000_create_license_flag_overrides_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-047: lokale Override-Tabelle für lizenzierte Feature-Flags.
 *
 * Option A (entschieden): Ein Override-Eintrag kann ein lizenziertes
 * Feature lokal NUR deaktivieren — niemals zusätzliche Features
 * freischalten. Das Verhalten wird in {@see \App\Services\Licensing\FeatureFlagResolver}
 * abgebildet.
 *
 * `organization_id` nullable: NULL = plattformweiter Override; sonst
 * org-spezifisch. Unique über die Kombination (organization_id, flag),
 * damit pro Org und Flag genau ein Override existieren kann.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('license_flag_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('flag', 100);
            $table->text('reason')->nullable();
            $table->timestamp('disabled_at');
            $table->foreignId('disabled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'flag'], 'license_flag_overrides_unique');
            $table->index('flag');
        });
    }

    public function down(): void {
        Schema::dropIfExists('license_flag_overrides');
    }
};
