<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_000001_make_tasks_project_id_nullable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
