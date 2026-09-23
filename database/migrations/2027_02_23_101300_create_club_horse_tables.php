<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_101300_create_club_horse_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reitbetrieb (Feature 159, MVP-854): Pferdeprofil auf einer Ressource
 * (Belegung, Sperrzeiten und Eignungsfreigaben laufen über MVP-853),
 * Zuordnung Reiter–Pferd je Reitstunde und getrennte Pferdeeinsatznachweise.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_horses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_resource_id')->unique()->constrained('club_resources')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->foreignId('owner_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->string('contact', 190)->nullable();
            // Vom Verein festgelegte Einsatzgrenze je Tag; leer = keine Grenze. Ruhepuffer liegt als Abbaupuffer an der Ressource.
            $table->unsignedSmallInteger('max_uses_per_day')->nullable();
            $table->string('suitable_for', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'club_horses_org_name_uq');
        });

        Schema::create('club_horse_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_horse_id')->constrained('club_horses')->cascadeOnDelete();
            $table->foreignId('club_group_id')->constrained('club_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['club_horse_id', 'club_group_id'], 'club_horse_groups_uq');
        });

        Schema::create('club_horse_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_horse_id')->nullable()->constrained('club_horses')->nullOnDelete();
            $table->foreignId('club_resource_booking_id')->nullable()->constrained('club_resource_bookings')->nullOnDelete();
            // Teilnahme mit eigenem Pferd ohne Vereinsprofil.
            $table->boolean('own_horse')->default(false);
            $table->string('override_note', 255)->nullable();
            $table->dateTime('needs_review_at')->nullable();
            $table->string('review_reason', 255)->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'club_member_id'], 'club_horse_assignments_uq');
            $table->index(['club_horse_id', 'event_id'], 'club_horse_assignments_horse_idx');
        });

        Schema::create('club_horse_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('club_horse_id')->constrained('club_horses')->cascadeOnDelete();
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->index(['club_horse_id', 'recorded_at'], 'club_horse_uses_horse_idx');
            $table->index(['event_id', 'club_member_id'], 'club_horse_uses_event_member_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_horse_uses');
        Schema::dropIfExists('club_horse_assignments');
        Schema::dropIfExists('club_horse_groups');
        Schema::dropIfExists('club_horses');
    }
};
