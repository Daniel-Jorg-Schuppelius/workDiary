<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokaler Cache der Lexoffice-Artikel (Services/Produkte). Wird per
 * `php artisan lexoffice:sync-articles` aktualisiert. Pro Organisation eindeutig
 * über die `external_id` (Lexoffice-UUID).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('name');
            $table->string('article_number')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 16)->default('service'); // service|product|custom
            $table->string('unit_name', 32)->nullable();
            $table->decimal('net_unit_price', 12, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'archived_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('lexoffice_articles');
    }
};
