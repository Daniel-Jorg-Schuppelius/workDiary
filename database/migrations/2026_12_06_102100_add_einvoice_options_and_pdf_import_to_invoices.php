<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('delivery_format', 24)->default('pdf')->after('number_source');
            $table->string('buyer_reference', 100)->nullable()->after('delivery_format');
            $table->json('import_metadata')->nullable()->after('buyer_reference');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['delivery_format', 'buyer_reference', 'import_metadata']);
        });
    }
};
