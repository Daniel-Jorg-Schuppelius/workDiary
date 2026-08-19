<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_14_100000_add_landscape_support_to_document_design.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-652 (Issue #85): Querformat-Design. Profile tragen ihr Seitenformat
 * explizit (`page_format`), Firmenbogen-Assets ebenso — ein Hochformat-Bogen
 * ist auf einer Querformat-Seite unbrauchbar und umgekehrt. Bestand bleibt
 * A4 Hochformat (Default), die Ausgabe ändert sich dadurch nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->string('page_format', 20)->default('a4_portrait')->after('document_family');
        });

        Schema::table('letterhead_assets', function (Blueprint $table): void {
            $table->string('page_format', 20)->default('a4_portrait')->after('page_role');
        });
    }

    public function down(): void {
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->dropColumn('page_format');
        });
        Schema::table('letterhead_assets', function (Blueprint $table): void {
            $table->dropColumn('page_format');
        });
    }
};
