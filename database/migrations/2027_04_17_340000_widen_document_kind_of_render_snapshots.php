<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_340000_widen_document_kind_of_render_snapshots.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1624: `construction_obstruction_notice` hat 31 Zeichen — der Snapshot der
 * Behinderungsanzeige passte nicht in `document_kind` (30). Versandte Schreiben
 * frieren ihre Gestaltung jetzt ein; dafür reicht die Spalte nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('document_render_snapshots', function (Blueprint $table): void {
            $table->string('document_kind', 50)->change();
        });
    }

    public function down(): void {
        Schema::table('document_render_snapshots', function (Blueprint $table): void {
            $table->string('document_kind', 30)->change();
        });
    }
};
