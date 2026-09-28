<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_390000_create_crisis_room_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-963: Anwesenheit im Krisenraum und eigene Lagepunkte. */
return new class extends Migration {
    public function up(): void {
        Schema::create('crisis_room_presences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crisis_presence_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'crisis_presence_case_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'crisis_presence_user_fk')->cascadeOnDelete();
            $table->timestamp('last_seen_at');
            $table->unique(['crisis_case_id', 'user_id'], 'crisis_presence_case_user_unique');
        });

        Schema::create('crisis_map_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crisis_points_org_fk')->cascadeOnDelete();
            $table->foreignId('crisis_case_id')->constrained('crisis_cases', indexName: 'crisis_points_case_fk')->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('kind', 20);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'crisis_points_creator_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('crisis_map_points');
        Schema::dropIfExists('crisis_room_presences');
    }
};
