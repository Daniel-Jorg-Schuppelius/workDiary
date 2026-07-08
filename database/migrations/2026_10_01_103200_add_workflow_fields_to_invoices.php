<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_103200_add_workflow_fields_to_invoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 066, MVP-163: Faktura-Workflow — optionale Prüfung/Freigabe vor
 * der Ausstellung und Mahnstatus (Stufe + Zeitpunkt) für die Fälligkeit.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users', indexName: 'inv_approved_by_fk')
                ->nullOnDelete();
            $table->unsignedTinyInteger('dunning_level')->default(0); // 0–3
            $table->timestamp('dunned_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'dunning_level', 'dunned_at']);
        });
    }
};
