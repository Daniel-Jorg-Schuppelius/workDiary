<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000027_create_privacy_attachments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anhaenge fuer Betroffenenanfragen und Datenschutzvorfaelle (polymorph). Dateien
 * liegen auf der privaten Disk; Zugriff strikt ueber die Policy der Fallakte.
 * Kurze, explizite Indexnamen (MySQL-64-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'pa_attachable_index');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_attachments');
    }
};
