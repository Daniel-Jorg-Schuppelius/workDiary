<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102700_add_gaeb_traits_to_boq_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 108, MVP-615: GAEB-Positionsmerkmale, die bisher beim Import verloren
 * gingen — Bedarfsposition mit/ohne Gesamtbetrag, Grund-/Alternativgruppe,
 * Zuschlagsart und die Unterbeschreibungen einer Leitbeschreibung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->string('provision_kind', 20)->nullable()->after('type');       // GAEB Provis: WithTotal|WithoutTotal
            $table->string('alternative_group', 20)->nullable()->after('provision_kind'); // ALNGroupNo
            $table->unsignedSmallInteger('alternative_no')->nullable()->after('alternative_group'); // ALNSerNo (0 = Grundposition)
            $table->string('markup_type', 20)->nullable()->after('alternative_no'); // MarkupType der Zuschlagsposition
            $table->json('sub_descriptions')->nullable()->after('long_text');       // SubDescr einer Leitbeschreibung
        });
    }

    public function down(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->dropColumn(['provision_kind', 'alternative_group', 'alternative_no', 'markup_type', 'sub_descriptions']);
        });
    }
};
