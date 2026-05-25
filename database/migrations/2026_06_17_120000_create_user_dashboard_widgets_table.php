<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_17_120000_create_user_dashboard_widgets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'widget_key']);
            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_dashboard_widgets');
    }
};
