<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_140000_create_time_exports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-019 — Zeit-Export (ApprovedTimeExporter).
 *
 * Schreibt einen Datei-Export für genehmigte Monatsfreigaben:
 * - profile: generic|datev|lexware (siehe config/exports.php)
 * - scope: organization|team|user (Auswahl der Mitarbeitenden)
 * - status: preparing → ready → delivered|rejected|superseded
 * - payload_hash: SHA-256 der erzeugten Datei (Reproduzierbarkeit)
 * - file_path: relativ unter storage/exports/{org}/{year}-{month}/
 * - delivered_at/delivered_by/delivery_note: manuelle Bestätigung
 * - superseded_by_id: Verweis auf Folge-Export bei Re-Export
 *
 * Audit-Events liegen in time_export_events.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_exports', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('profile', 32);                              // generic|datev|lexware
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->string('scope', 16);                                // organization|team|user
            $t->foreignId('scope_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('scope_team_id')->nullable();    // optional, kein FK (Team-Tabelle org-abhängig)
            $t->string('status', 16)->default('preparing');
            $t->unsignedInteger('rows_count')->default(0);
            $t->json('totals')->nullable();                         // Aggregate je Lohnart
            $t->string('payload_hash', 64)->nullable();             // SHA-256
            $t->string('file_path', 255)->nullable();
            $t->string('file_format', 16)->nullable();              // csv|xml|txt
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('delivered_at')->nullable();
            $t->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('delivery_note')->nullable();
            $t->foreignId('superseded_by_id')->nullable()->constrained('time_exports')->nullOnDelete();
            $t->timestamps();

            $t->index(['organization_id', 'period_year', 'period_month'], 'time_exports_period_idx');
            $t->index(['organization_id', 'status'], 'time_exports_status_idx');
            $t->index('payload_hash', 'time_exports_hash_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_exports');
    }
};
