<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_180000_create_resale_ignored_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 163 (MVP-1432): Lizenzpositionen im Belegspiegel, die bewusst kein Abo erklären
// (Historie, Partner-Sammelrechnung, Produkt außerhalb des Registers) — dauerhaft ausgeblendet.
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_ignored_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('linkable_type', 64);
            $t->unsignedBigInteger('linkable_id');
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'linkable_type', 'linkable_id'], 'resale_ignored_lines_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_ignored_lines');
    }
};
