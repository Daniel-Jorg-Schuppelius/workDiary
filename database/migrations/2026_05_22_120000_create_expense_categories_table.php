<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120000_create_expense_categories_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('slug', 64);
            $table->string('label');
            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->default('primary');
            $table->text('description')->nullable();
            $table->decimal('default_tax_rate', 5, 2)->default(19);
            $table->boolean('default_billable')->default(false);
            $table->boolean('requires_receipt')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('expense_categories');
    }
};
