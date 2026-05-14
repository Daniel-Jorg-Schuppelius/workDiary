<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->string('unit', 20)->default('Stk.');
            $table->decimal('default_unit_price', 10, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('external_provider', 30)->nullable();
            $table->string('external_id', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'sku']);
            $table->index(['external_provider', 'external_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
