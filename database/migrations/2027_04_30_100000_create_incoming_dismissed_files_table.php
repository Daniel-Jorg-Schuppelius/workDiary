<?php
/*
 * Created on   : Wed Nov 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_30_100000_create_incoming_dismissed_files_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rechnungseingang (MVP-1808): von Hand als „keine Rechnung“ gelöschte Dateien.
 * Dieselbe Datei (SHA-256) nimmt das Rechnungspostfach nicht wieder auf.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('incoming_dismissed_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->char('sha256', 64);
            $table->string('file_name')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'sha256'], 'incoming_dismissed_files_org_sha_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('incoming_dismissed_files');
    }
};
