<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101200_create_club_resource_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sportstätten und Ressourcen (Feature 159, MVP-853): Ressourcenbaum
 * (Halle → Teilflächen/Tische, Plätze, Bahnen, Boote/Geräte) mit Anbindung an
 * Räume und Assets, Belegungen je Termin, Sperrzeiten und Einweisungsfreigaben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('club_resources')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('name', 120);
            $table->string('kind', 30);
            // Parallel nutzbare Einheiten (z. B. 4 Bahnen als eine Ressource); 1 = exklusiv.
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->unsignedSmallInteger('setup_minutes')->default(0);
            $table->unsignedSmallInteger('teardown_minutes')->default(0);
            $table->boolean('requires_clearance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'club_resources_org_name_uq');
            $table->index(['organization_id', 'parent_id'], 'club_resources_org_parent_idx');
        });

        Schema::create('club_resource_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_resource_id')->constrained('club_resources')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('setup_minutes')->default(0);
            $table->unsignedSmallInteger('teardown_minutes')->default(0);
            $table->string('note', 255)->nullable();
            // Sperrzeit (Witterung, Wartung) markiert betroffene Belegungen zur Neuplanung, statt sie zu löschen.
            $table->dateTime('flagged_at')->nullable();
            $table->string('flag_reason', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'club_resource_id'], 'club_resource_bookings_uq');
            $table->index(['club_resource_id', 'starts_at', 'ends_at'], 'club_resource_bookings_window_idx');
        });

        Schema::create('club_resource_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_resource_id')->constrained('club_resources')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_resource_id', 'starts_at', 'ends_at'], 'club_resource_closures_window_idx');
        });

        Schema::create('club_resource_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_resource_id')->constrained('club_resources')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->date('granted_on');
            $table->date('valid_to')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['club_resource_id', 'club_member_id'], 'club_resource_clearances_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_resource_clearances');
        Schema::dropIfExists('club_resource_closures');
        Schema::dropIfExists('club_resource_bookings');
        Schema::dropIfExists('club_resources');
    }
};
