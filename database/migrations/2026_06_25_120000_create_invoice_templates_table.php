<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_25_120000_create_invoice_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 64);
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('accent_color', 16)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_default']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('invoice_template_id')->nullable()->after('invoice_text')
                ->constrained('invoice_templates')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_template_id');
        });
        Schema::dropIfExists('invoice_templates');
    }
};
