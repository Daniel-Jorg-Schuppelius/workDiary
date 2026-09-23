<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_23_100800_create_club_fee_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beitragstarife und Beitragskonten (Feature 159, MVP-849): versionierte
 * Tarife mit Sätzen je Gültigkeitsdatum, Abteilungszuschläge, Beitragskonten
 * am Kunden-/Debitorenstamm, zeitliche Zuordnung Mitglied → Konto/Tarif,
 * Befreiungen mit Zeitraum und Grund. Keine Vereinssätze im Code.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('club_fee_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 16)->default('individual'); // individual|family
            $table->text('description')->nullable();
            // Altersgrenzen inklusive; Abweichung erzeugt einen Wechselvorschlag, keinen automatischen Wechsel.
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'club_fee_tariffs_org_active_idx');
        });

        Schema::create('club_fee_tariff_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_tariff_id')->constrained('club_fee_tariffs')->cascadeOnDelete();
            $table->date('valid_from');
            $table->string('interval', 16); // monthly|quarterly|semi_annually|annually
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            // Abrechnungsanker: Monat, in dem ein Jahres-/Halbjahres-/Quartalsintervall beginnt.
            $table->unsignedTinyInteger('anchor_month')->default(1);
            $table->unsignedSmallInteger('due_days')->default(14);
            $table->string('proration', 8)->default('full'); // full|daily
            $table->decimal('admission_fee', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['club_fee_tariff_id', 'valid_from'], 'club_fee_rates_tariff_from_uq');
        });

        Schema::create('club_fee_surcharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_department_id')->constrained('club_departments')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('interval', 16);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedTinyInteger('anchor_month')->default(1);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'club_department_id'], 'club_fee_surcharges_org_dept_idx');
        });

        Schema::create('club_fee_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Zahlungspflichtige/r im Kunden-/Debitorenstamm — explizit zugeordnet, nie automatisch.
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('customer_id', 'club_fee_accounts_customer_uq');
        });

        Schema::create('club_fee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_fee_account_id')->constrained('club_fee_accounts')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('club_fee_tariff_id')->constrained('club_fee_tariffs')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('discount_reason', 255)->nullable();
            // Altersbedingter Wechselvorschlag: gesetzt vom täglichen Scan, wirksam erst nach Bestätigung.
            $table->timestamp('review_required_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamps();

            $table->index(['club_member_id', 'valid_from'], 'club_fee_assign_member_from_idx');
            $table->index(['club_fee_account_id', 'valid_from'], 'club_fee_assign_account_from_idx');
        });

        Schema::create('club_fee_exemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->string('kind', 16)->default('exemption'); // exemption|reduction
            $table->decimal('percent', 5, 2)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason', 255);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_member_id', 'starts_on'], 'club_fee_exempt_member_from_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('club_fee_exemptions');
        Schema::dropIfExists('club_fee_assignments');
        Schema::dropIfExists('club_fee_accounts');
        Schema::dropIfExists('club_fee_surcharges');
        Schema::dropIfExists('club_fee_tariff_rates');
        Schema::dropIfExists('club_fee_tariffs');
    }
};
