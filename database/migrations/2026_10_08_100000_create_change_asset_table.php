<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_08_100000_create_change_asset_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, MVP-157: Asset-Verknüpfung am Change (CAB-Sicht „welche
 * CIs sind betroffen?"). Kurze explizite Index-/FK-Namen (MySQL-Limit,
 * FK-Präfixe DB-weit eindeutig — errno 121; SQLite-Dev verdeckt das).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('change_asset', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_id')
                ->constrained('changes', indexName: 'chas_change_fk')
                ->cascadeOnDelete();
            $table->foreignId('asset_id')
                ->constrained('assets', indexName: 'chas_asset_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['change_id', 'asset_id'], 'chas_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('change_asset');
    }
};
