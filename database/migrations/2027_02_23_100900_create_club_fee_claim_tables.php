<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100900_create_club_fee_claim_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beitragslauf und Forderungen (Feature 159, MVP-850): der Lauf friert die
 * Vorschau eines Abrechnungsmonats ein; die Freigabe erzeugt je Konto eine
 * Beitragsforderung mit Positionen. Die Forderung ist die führende Quelle —
 * Mitteilung, offener Posten und Zahlung (851) verweisen auf sie. Der
 * fachliche Quellschlüssel je Position ist je Organisation eindeutig; Storno
 * gibt ihn frei, indem er umbenannt wird.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_fee_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 16)->default('draft'); // draft|released|cancelled
            $table->json('positions')->nullable(); // eingefrorene Vorschau
            $table->json('issues')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('claims_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'year', 'month'], 'club_fee_runs_org_month_idx');
        });

        Schema::create('club_fee_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_run_id')->nullable()->constrained('club_fee_runs')->nullOnDelete();
            $table->foreignId('club_fee_account_id')->constrained('club_fee_accounts')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('number', 32);
            $table->string('kind', 16)->default('claim'); // claim|correction
            $table->foreignId('corrects_claim_id')->nullable()->constrained('club_fee_claims')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issued_on');
            $table->date('due_on');
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            // Adress-Schnappschuss der zahlungspflichtigen Person zum Ausstellungszeitpunkt.
            $table->json('payer_snapshot')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('dunning_level')->default(0);
            $table->timestamp('dunned_at')->nullable();
            $table->timestamp('dunning_blocked_at')->nullable();
            $table->string('dunning_block_reason', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'sequence'], 'club_fee_claims_org_seq_uq');
            $table->index(['club_fee_account_id', 'status'], 'club_fee_claims_account_status_idx');
            $table->index(['organization_id', 'status', 'due_on'], 'club_fee_claims_org_status_due_idx');
        });

        Schema::create('club_fee_claim_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_claim_id')->constrained('club_fee_claims')->cascadeOnDelete();
            $table->foreignId('club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->string('kind', 16); // base|family|surcharge|admission|correction
            // Fachlicher Quellschlüssel (Quelle + Periode) — je Organisation nur einmal aktiv.
            $table->string('source_key', 191);
            $table->string('label', 160);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->json('basis')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'source_key'], 'club_fee_items_org_source_uq');
            $table->index(['club_fee_claim_id'], 'club_fee_items_claim_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_fee_claim_items');
        Schema::dropIfExists('club_fee_claims');
        Schema::dropIfExists('club_fee_runs');
    }
};
