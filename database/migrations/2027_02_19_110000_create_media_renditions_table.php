<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110000_create_media_renditions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video-Transcoding (Feature 150).
 *
 * Ein hochgeladenes Video bleibt als **Original** erhalten (Betreiber-
 * Entscheidung 2026-08-29) und bekommt abgeleitete Fassungen: eine bis drei
 * Auflösungen in H.264/AAC plus ein Vorschaubild.
 *
 * **Der Zustand gehört an den Anhang, nicht an die Ableitung.** Ein Kurs,
 * dessen Video noch rechnet, muss das sagen können — auch wenn noch keine
 * einzige Ableitung existiert. Deshalb hängt `media_state` am Anhang selbst.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('attachments', function (Blueprint $table): void {
            // pending|processing|ready|failed — nur bei Videos gesetzt.
            $table->string('media_state', 12)->nullable()->after('size');
            $table->unsignedInteger('media_duration_seconds')->nullable()->after('media_state');
            $table->unsignedSmallInteger('media_width')->nullable()->after('media_duration_seconds');
            $table->unsignedSmallInteger('media_height')->nullable()->after('media_width');
            $table->string('media_error', 255)->nullable()->after('media_height');
            $table->timestamp('media_processed_at')->nullable()->after('media_error');

            $table->index(['media_state'], 'attach_media_state_idx');
        });

        Schema::create('media_renditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            // video|poster|subtitle
            $table->string('kind', 12);
            // 480p, 720p, 1080p … bei Vorschaubild/Untertitel leer.
            $table->string('variant', 20)->nullable();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            // Sprachcode der Untertitelspur.
            $table->string('locale', 8)->nullable();
            $table->timestamps();

            $table->unique(['attachment_id', 'kind', 'variant', 'locale'], 'media_rend_uq');
            $table->index(['organization_id'], 'media_rend_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('media_renditions');

        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropIndex('attach_media_state_idx');
            $table->dropColumn([
                'media_state',
                'media_duration_seconds',
                'media_width',
                'media_height',
                'media_error',
                'media_processed_at',
            ]);
        });
    }
};
