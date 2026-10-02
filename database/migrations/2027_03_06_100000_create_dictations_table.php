<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_06_100000_create_dictations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1060: Sprachdiktat — Audio wird lokal transkribiert und danach gelöscht;
 * Transkript und Gliederung liegen verschlüsselt bis zur Aufbewahrungsfrist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('dictations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context', 32);
            $table->string('status', 16)->default('pending');
            $table->string('locale', 8)->default('de');
            $table->string('audio_disk', 32)->nullable();
            $table->string('audio_path', 255)->nullable();
            $table->text('transcript')->nullable();
            $table->text('structured')->nullable();
            $table->string('failure', 64)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('dictations');
    }
};
