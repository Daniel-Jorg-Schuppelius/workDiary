<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_219000_document_mirror_detachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1029: „Spiegelung getrennt" je Dokument und Ablage-Ziel in einer
 * eigenen Tabelle statt einer documents-Spalte je Plugin
 * (`webdav_mirror_detached`, `sharepoint_mirror_detached`).
 */
return new class extends Migration {
    private const COLUMNS = ['webdav_mirror_detached' => 'webdav', 'sharepoint_mirror_detached' => 'sharepoint'];

    public function up(): void {
        Schema::create('document_mirror_detachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('target', 32);
            $table->timestamp('detached_at');
            $table->foreignId('detached_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'target'], 'doc_mirror_detach_doc_target_uq');
        });

        $now = now();
        foreach (self::COLUMNS as $column => $target) {
            DB::table('documents')->where($column, true)->orderBy('id')->select(['id', 'organization_id', 'updated_at'])
                ->chunkById(500, static function ($rows) use ($target, $now): void {
                    DB::table('document_mirror_detachments')->insertOrIgnore($rows->map(static fn (object $row): array => [
                        'organization_id' => $row->organization_id,
                        'document_id' => $row->id,
                        'target' => $target,
                        'detached_at' => $row->updated_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
        }

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }

    public function down(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('webdav_mirror_detached')->default(false);
            $table->boolean('sharepoint_mirror_detached')->default(false);
        });

        foreach (self::COLUMNS as $column => $target) {
            $ids = DB::table('document_mirror_detachments')->where('target', $target)->pluck('document_id');
            foreach ($ids->chunk(500) as $chunk) {
                DB::table('documents')->whereIn('id', $chunk->all())->update([$column => true]);
            }
        }

        Schema::dropIfExists('document_mirror_detachments');
    }
};
