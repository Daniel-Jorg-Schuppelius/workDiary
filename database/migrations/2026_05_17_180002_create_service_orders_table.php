<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_180002_create_service_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('address_line')->nullable();
            $table->string('address_zip', 16)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->string('address_country', 2)->nullable();
            $table->decimal('address_lat', 10, 7)->nullable();
            $table->decimal('address_lng', 10, 7)->nullable();

            $table->date('scheduled_for')->index();
            $table->time('time_window_start')->nullable();
            $table->time('time_window_end')->nullable();
            $table->unsignedSmallInteger('service_minutes')->default(60);

            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('planned');

            $table->foreignId('tour_id')->nullable()
                ->constrained('tours')->nullOnDelete();
            $table->unsignedSmallInteger('tour_position')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'scheduled_for']);
            $table->index(['assigned_user_id', 'scheduled_for']);
            $table->index(['tour_id', 'tour_position']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
