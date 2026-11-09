<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_10_100000_create_ical_subscriptions_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1516: abonnierte iCal-Quellen als Dauer-Sync der Zeiterfassung. Die URL
 * trägt oft ein Zugriffsgeheimnis und liegt verschlüsselt; der Stand je
 * Termin (UID, SEQUENCE, LAST-MODIFIED, Abdruck) macht den Lauf inkrementell
 * und erkennt Löschungen der Quelle.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ical_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Kalender einer Person; leer = die Person kommt aus Organisator/Teilnehmer des Termins.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('import_entity', 40);
            $table->string('name', 120);
            $table->text('url');
            $table->json('category_allowlist')->nullable();
            $table->boolean('allow_private_network')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('http_etag', 191)->nullable();
            $table->string('http_last_modified', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('last_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'last_synced_at'], 'ical_subscriptions_active_synced_idx');
        });

        Schema::create('ical_subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ical_subscription_id')->constrained('ical_subscriptions')->cascadeOnDelete();
            $table->text('uid');
            $table->char('uid_hash', 64);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('source_modified_at')->nullable();
            $table->char('fingerprint', 64);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['ical_subscription_id', 'uid_hash'], 'ical_sub_items_sub_uid_unique');
            $table->index(['ical_subscription_id', 'starts_at'], 'ical_sub_items_sub_start_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ical_subscription_items');
        Schema::dropIfExists('ical_subscriptions');
    }
};
