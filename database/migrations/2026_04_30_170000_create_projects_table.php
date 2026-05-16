<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_04_30_170000_create_projects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->string('status', 16)->default('active'); // active|paused|archived
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('projects');
    }
};
