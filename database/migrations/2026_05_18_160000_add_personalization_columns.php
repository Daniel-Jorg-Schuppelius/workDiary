<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_160000_add_personalization_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('preferences')->nullable()->after('home_lng');
        });

        Schema::table('attachments', function (Blueprint $table): void {
            // Discriminator für Spezialrollen (logo, logo_dark, avatar, ...).
            // Bleibt NULL für reguläre Anhänge → Migration ist non-destructive.
            $table->string('meta_type', 32)->nullable()->after('size');
            $table->index(['attachable_type', 'attachable_id', 'meta_type'], 'attachments_attachable_meta_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropIndex('attachments_attachable_meta_idx');
            $table->dropColumn('meta_type');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferences');
        });
    }
};
