<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_02_120000_create_backup_heartbeats_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('backup_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('manifest_hash', 64)->nullable();
            $table->string('source', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('backup_heartbeats');
    }
};
