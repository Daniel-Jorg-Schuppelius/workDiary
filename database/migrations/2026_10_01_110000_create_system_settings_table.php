<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110000_create_system_settings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 067, P0 (MVP-173): Systemweite Betreiber-Overrides als neue
 * System-Scope-Stufe von Ebene 2 (einstellungen-ablage.md). Enthält NUR
 * Keys, die die Settings-Registry als system-scoped deklariert; sensible
 * Werte werden anwendungsseitig verschlüsselt abgelegt (is_sensitive).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 150);
            $table->text('value')->nullable(); // JSON-encoded; bei is_sensitive verschlüsselt
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users', indexName: 'syset_user_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('key', 'syset_key_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('system_settings');
    }
};
