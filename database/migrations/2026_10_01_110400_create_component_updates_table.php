<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_110400_create_component_updates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 022/041, MVP-054: bekannte verfügbare Updates je Komponente
 * (App/Plugins) aus dem signierten Update-Feed bzw. Offline-Import.
 * KEIN Self-Update — reine Erkennungs- und Anzeige-Grundlage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('component_updates', function (Blueprint $table): void {
            $table->id();
            $table->string('component_type', 20); // app/plugin/component
            $table->string('component_key', 100); // z. B. app, toggl, lexoffice
            $table->string('installed_version', 50)->nullable();
            $table->string('available_version', 50);
            $table->string('channel', 20)->default('stable');
            $table->string('classification', 20)->default('normal'); // normal/recommended/security/critical
            $table->string('min_app_version', 50)->nullable();
            $table->string('max_app_version', 50)->nullable();
            $table->boolean('compatible')->default(true);
            $table->string('changelog_url', 300)->nullable();
            $table->json('requires')->nullable(); // backup/maintenance_window/migrations/manual_steps
            $table->string('source', 20)->default('remote'); // remote/offline_import
            $table->timestamp('checked_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()
                ->constrained('users', indexName: 'cup_ack_user_fk')
                ->nullOnDelete();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            $table->unique(['component_type', 'component_key'], 'cup_component_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('component_updates');
    }
};
