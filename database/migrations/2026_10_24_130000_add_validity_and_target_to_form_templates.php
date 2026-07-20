<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_24_130000_add_validity_and_target_to_form_templates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formularvorlagen: Gültigkeitszeitraum + optionale Zuordnung (Feature 032
 * MVP; Vollaudit 2026-07, M11). `target` trägt optionale Einschränkungen als
 * JSON (entry_type_id/customer_id) — leere Zuordnung = überall nutzbar.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('form_templates', function (Blueprint $table): void {
            $table->date('valid_from')->nullable()->after('fields');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->json('target')->nullable()->after('valid_until');
        });
    }

    public function down(): void {
        Schema::table('form_templates', function (Blueprint $table): void {
            $table->dropColumn(['valid_from', 'valid_until', 'target']);
        });
    }
};
