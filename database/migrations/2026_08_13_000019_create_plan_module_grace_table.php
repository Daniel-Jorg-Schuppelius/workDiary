<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000019_create_plan_module_grace_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karenz-/Downgrade-Ledger: haelt fest, welche Module eine Organisation durch
 * einen Plan-Downgrade verloren hat und bis wann die Karenzzeit laeuft. Waehrend
 * der Karenz bleibt der Zugriff (Export), danach sperrt das Gate und – nur fuer
 * `purgeable`-Module – entfernt `plans:purge` die Daten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('plan_module_grace', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module', 64);
            $table->timestamp('lost_at');
            $table->timestamp('grace_until');
            $table->timestamp('purged_at')->nullable(); // verarbeitet (geloescht ODER bewusst behalten)
            $table->timestamps();

            $table->unique(['organization_id', 'module']);
            $table->index('grace_until');
        });
    }

    public function down(): void {
        Schema::dropIfExists('plan_module_grace');
    }
};
