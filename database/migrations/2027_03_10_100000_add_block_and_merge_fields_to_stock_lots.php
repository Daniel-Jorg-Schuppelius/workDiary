<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100000_add_block_and_merge_fields_to_stock_lots.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Chargensperre mit Begründung und Zielcharge einer Zusammenführung
 * (Feature 047/048). Für schon zusammengeführte Chargen wird die Zielcharge
 * aus den Bewertungsschichten nachgetragen; ihre Bewegungen bleiben, wie sie
 * sind — der Pickzettel rechnet ihren Bestand der Zielcharge zu.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('stock_lots', 'merged_into_lot_id')) {
            Schema::table('stock_lots', function (Blueprint $table): void {
                $table->string('blocked_reason', 500)->nullable()->after('note');
                $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
                $table->foreignId('blocked_by_user_id')->nullable()->after('blocked_at')
                    ->constrained('users')->nullOnDelete();
                $table->foreignId('merged_into_lot_id')->nullable()->after('blocked_by_user_id')
                    ->constrained('stock_lots')->nullOnDelete();
            });
        }

        $this->backfillMergeTargets();
    }

    public function down(): void {
        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->dropForeign(['blocked_by_user_id']);
            $table->dropForeign(['merged_into_lot_id']);
            $table->dropColumn(['blocked_reason', 'blocked_at', 'blocked_by_user_id', 'merged_into_lot_id']);
        });
    }

    /**
     * Das Zusammenführen hängte bisher nur die Schichten um. Die erste Schicht
     * je Zugangsbewegung der Quellcharge zeigt deshalb auf die Zielcharge
     * (abgeteilte Schichten kommen später und zählen nicht). Nur eindeutige
     * Fälle werden nachgetragen.
     */
    private function backfillMergeTargets(): void {
        $merged = DB::table('stock_lots')->where('status', 'merged')->whereNull('merged_into_lot_id')
            ->orderBy('id')->get(['id', 'article_variant_id']);

        foreach ($merged as $lot) {
            $firstLayers = DB::table('stock_valuation_layers')
                ->whereIn('source_movement_id', DB::table('stock_movements')->where('stock_lot_id', $lot->id)->select('id'))
                ->groupBy('source_movement_id')
                ->selectRaw('MIN(id) as id')
                ->pluck('id');
            if ($firstLayers->isEmpty()) {
                continue;
            }

            $targets = DB::table('stock_valuation_layers')
                ->whereIn('id', $firstLayers)
                ->whereNotNull('stock_lot_id')
                ->where('stock_lot_id', '!=', $lot->id)
                ->distinct()
                ->pluck('stock_lot_id');
            if ($targets->count() !== 1) {
                continue;
            }

            $sameVariant = DB::table('stock_lots')
                ->where('id', $targets->first())
                ->where('article_variant_id', $lot->article_variant_id)
                ->exists();
            if ($sameVariant) {
                DB::table('stock_lots')->where('id', $lot->id)->update(['merged_into_lot_id' => $targets->first()]);
            }
        }
    }
};
