<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_15_100000_create_accounting_migration_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-653 (Issue #86): kontrollierter Buchhaltungswechsel. Ein Lauf je
 * Organisation führt Quelle, Ziel, Stichtag, Datenbereiche und Zählwerke;
 * die Positionen halten je Datensatz die Zuordnung Quelle → lokales Objekt →
 * Ziel fest (idempotent über `dedupe_key`). Ereignisse bilden die Auditspur
 * für Freigaben, Konfliktentscheidungen, Umschaltung und Abschluss.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_migration_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_plugin', 64);
            $table->string('target_plugin', 64);
            $table->string('status', 20)->default('draft');
            $table->json('data_areas');
            // Stichtag: ab hier entstehen neue Fakturavorgänge nur im Ziel.
            $table->date('cutover_on')->nullable();
            $table->dateTime('cutover_at')->nullable();
            $table->boolean('dry_run_only')->default(true);
            $table->json('counters')->nullable();
            $table->json('checkpoints')->nullable();
            $table->json('preflight')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'amr_org_status_idx');
        });

        Schema::create('accounting_migration_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_migration_run_id')->constrained('accounting_migration_runs')->cascadeOnDelete();
            $table->string('data_area', 24);
            $table->string('status', 24)->default('pending');
            // Quelle (Lexoffice) und Ziel (orgaMAX) am selben lokalen Objekt.
            $table->string('source_external_id', 191)->nullable();
            $table->string('target_external_id', 191)->nullable();
            $table->nullableMorphs('referenceable', 'ami_ref');
            $table->string('dedupe_key', 191);
            $table->string('display_title', 191)->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('diff')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['accounting_migration_run_id', 'dedupe_key'], 'ami_run_dedupe_unique');
            $table->index(['accounting_migration_run_id', 'data_area', 'status'], 'ami_run_area_status_idx');
        });

        // Append-only Kette (Muster billing_transfer_events): bewusst OHNE
        // Fremdschlüssel/Cascade — die Kette muss scope-frei verifizierbar
        // bleiben und Einträge überdauern die Löschung von Lauf/Organisation.
        Schema::create('accounting_migration_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('accounting_migration_run_id')->nullable();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['accounting_migration_run_id', 'id'], 'ame_run_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            // Stichtags-Sperre je Kunde: ab hier nur noch das Zielsystem.
            $table->date('billing_cutover_on')->nullable()->after('billing_mode');
            // Welches System ab dem Stichtag gesperrt ist (das Quellsystem
            // des Wechsels) — hält die Sperre richtungsunabhängig.
            $table->string('billing_cutover_from', 32)->nullable()->after('billing_cutover_on');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['billing_cutover_on', 'billing_cutover_from']);
        });
        Schema::dropIfExists('accounting_migration_events');
        Schema::dropIfExists('accounting_migration_items');
        Schema::dropIfExists('accounting_migration_runs');
    }
};
