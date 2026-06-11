<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_140100_create_document_versions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            // Datei-Metadaten analog attachments (disk/path/original_name/mime/size).
            // Bewusst KEIN FK auf attachments: Attachment ist ein eigenständiges
            // polymorphes Aggregat mit eigener Policy/Lösch-Route — eine Version
            // ist dagegen unveränderlicher Bestandteil der Dokument-Historie.
            // Der Storage-Mechanismus (Disk, Date-Buckets, UUID-Dateinamen,
            // MIME-Prüfung) wird identisch wiederverwendet.
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'version_no'], 'doc_versions_doc_no_unique');
            $table->index(['document_id'], 'doc_versions_doc_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('document_versions');
    }
};
