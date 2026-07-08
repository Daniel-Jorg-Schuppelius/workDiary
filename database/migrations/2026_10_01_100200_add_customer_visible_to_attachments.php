<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100200_add_customer_visible_to_attachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundensichtbarkeit je Anhang (Feature 012, Rang 54): Fotos/Dateien werden
 * erst nach expliziter interner Freigabe im Kundenportal angezeigt
 * (Default aus — nie stillschweigend kundensichtbar).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->boolean('customer_visible')->default(false)->after('meta_type');
        });
    }

    public function down(): void {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropColumn('customer_visible');
        });
    }
};
