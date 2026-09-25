<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_140000_create_contract_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-893: Vertragsvorlagen (Laufzeit, Kündigung, Verlängerung, Pflichten relativ zum Beginn). */
return new class extends Migration {
    public function up(): void {
        Schema::create('contract_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('kind', 32);
            $table->string('title', 255)->nullable();
            $table->string('term_kind', 32)->nullable();
            $table->unsignedSmallInteger('min_term_months')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renew_period_months')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->string('value_period', 16)->nullable();
            $table->string('indexation_method', 32)->nullable();
            $table->text('note')->nullable();
            $table->json('obligations');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'contract_templates_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('contract_templates');
    }
};
