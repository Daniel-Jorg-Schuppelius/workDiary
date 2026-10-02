<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_05_100000_create_takeoff_transfers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1059: Nachweis, wohin die Mengen eines Aufmaßes übernommen wurden (Angebot, Rechnung, LV-Leistungsstand). */
return new class extends Migration {
    public function up(): void {
        Schema::create('takeoff_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('takeoff_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->unique(['takeoff_id', 'kind']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('takeoff_transfers');
    }
};
