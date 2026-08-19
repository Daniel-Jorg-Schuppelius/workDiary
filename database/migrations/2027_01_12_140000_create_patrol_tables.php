<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_140000_create_patrol_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wächterrundgänge (Feature 089, MVP-663/664): Routen mit Kontrollpunkten,
 * Soll-Fenstern und Scan-Nachweis.
 *
 * Checkpoint-Tokens sind gehasht (Muster user_badges) — ein verlorener Tag
 * wird durch Neuausgabe des Tokens ersetzt, ohne die Route neu zu bauen.
 * Positionsdaten gibt es hier bewusst NICHT: Der Scan belegt Punkt und Zeit,
 * kein Dauer-Tracking (Datensparsamkeit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('patrol_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 160);
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('patrol_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('patrol_route_id')->constrained('patrol_routes')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('label', 160);
            $table->string('token_hash', 128);
            $table->string('token_suffix', 8);
            // Soll-Fenster relativ zum Rundgangsstart.
            $table->unsignedInteger('expected_offset_minutes')->default(0);
            $table->unsignedInteger('tolerance_minutes')->default(10);
            $table->timestamps();

            $table->unique(['organization_id', 'token_hash'], 'patrol_cp_org_token_uq');
        });

        Schema::create('patrol_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('patrol_route_id')->constrained('patrol_routes')->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('running'); // running|completed|aborted
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // Begründungspflicht bei Abweichungen - der Bericht trägt sie mit.
            $table->string('deviation_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'patrol_route_id', 'started_at'], 'patrol_runs_org_route_idx');
        });

        Schema::create('patrol_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('patrol_run_id')->constrained('patrol_runs')->cascadeOnDelete();
            $table->foreignId('patrol_checkpoint_id')->constrained('patrol_checkpoints')->cascadeOnDelete();
            $table->timestamp('scanned_at');
            // Abweichung vom Soll-Fenster in Minuten (0 = im Fenster).
            $table->integer('delta_minutes')->default(0);
            $table->boolean('in_window')->default(true);
            $table->timestamps();

            $table->unique(['patrol_run_id', 'patrol_checkpoint_id'], 'patrol_scan_run_cp_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('patrol_scans');
        Schema::dropIfExists('patrol_runs');
        Schema::dropIfExists('patrol_checkpoints');
        Schema::dropIfExists('patrol_routes');
    }
};
