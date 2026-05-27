<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_130000_make_maintenance_plans_polymorph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->string('subject_type', 64)->nullable()->after('organization_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->index(['subject_type', 'subject_id'], 'maintenance_plans_idx_subject');
        });

        // Backfill: jeder bestehende Plan ist asset-gebunden.
        DB::table('maintenance_plans')
            ->whereNotNull('asset_id')
            ->update([
                'subject_type' => Asset::class,
                'subject_id' => DB::raw('asset_id'),
            ]);

        // asset_id darf jetzt nullable sein, damit raumgebundene Pflegepläne
        // (Reinigungsintervalle) ohne Asset-Bezug möglich werden.
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('asset_id')->nullable()->change();
        });
    }

    public function down(): void {
        // Raumgebundene Pläne (ohne Asset) müssen vor dem Rollback entfernt werden.
        DB::table('maintenance_plans')->whereNull('asset_id')->delete();

        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('asset_id')->nullable(false)->change();
            $table->dropIndex('maintenance_plans_idx_subject');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
