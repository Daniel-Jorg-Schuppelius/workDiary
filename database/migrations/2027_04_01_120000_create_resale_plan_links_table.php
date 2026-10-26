<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_01_120000_create_resale_plan_links_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Feature 185 (MVP-1411): Zuordnung Abo ↔ Position einer Serienvorlage des
 * Buchhaltungsprogramms. Die Vorlage selbst spiegelt das Plugin; hier steht
 * nur ihr Schlüssel (`<quelle>:<externe id>`).
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('resale_plan_links')) {
            return;
        }
        Schema::create('resale_plan_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('resale_subscriptions')->cascadeOnDelete();
            $table->string('plan_key', 120);
            $table->unsignedSmallInteger('line_position');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('origin', 16)->default('proposed');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'plan_key', 'line_position'], 'resale_plan_links_sub_line_uq');
            $table->index(['organization_id', 'plan_key'], 'resale_plan_links_org_plan_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_plan_links');
    }
};
