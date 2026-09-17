<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100400_create_collections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammlungen (MVP-809, Feature 155): Ordnungsschicht über Notizen,
 * Ideenlandkarten, Wissensartikeln, Dokumenten und Lerninhalten. Die Sammlung
 * hält nur Zeiger — kein `collection_id` am Inhalt, damit ein Inhalt in
 * mehreren Sammlungen liegen kann.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'coll_org_fk')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('collections', indexName: 'coll_parent_fk')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('organization');
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'coll_created_by_fk')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'parent_id'], 'coll_org_parent_idx');
        });

        Schema::create('collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'colli_org_fk')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections', indexName: 'colli_collection_fk')->cascadeOnDelete();
            $table->string('collectable_type', 120);
            $table->unsignedBigInteger('collectable_id');
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('added_by')->nullable()->constrained('users', indexName: 'colli_added_by_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['collection_id', 'collectable_type', 'collectable_id'], 'colli_unique_item');
            $table->index(['collectable_type', 'collectable_id'], 'colli_collectable_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
