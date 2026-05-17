<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_16_100000_add_parent_to_projects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt der `projects`-Tabelle eine `parent_id`-Spalte hinzu, sodass Projekte
 * einen Baum bilden können. Sub-Projekte erben Customer und Abrechnungs-
 * einstellungen vom Parent (Logik im Model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('projects')
                ->nullOnDelete();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
