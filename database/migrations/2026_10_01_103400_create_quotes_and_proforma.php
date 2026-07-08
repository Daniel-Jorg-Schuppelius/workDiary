<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_103400_create_quotes_and_proforma.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 066, MVP-170/171/172: Angebote (Versionen, Optionen, Bindefrist,
 * Portal-Annahme) + Pro-forma als Invoice-Typ mit eigenem Nummernkreis +
 * Widerspruchs-Dokumentation gegen umsatzsteuerliche Gutschriften.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'qte_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers', indexName: 'qte_customer_fk')
                ->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects', indexName: 'qte_project_fk')
                ->nullOnDelete();
            $table->string('number', 40);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()
                ->constrained('quotes', indexName: 'qte_prev_fk')
                ->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|approved|sent|accepted|partially_accepted|rejected|expired
            $table->date('valid_until')->nullable(); // Bindefrist
            $table->text('terms')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('acceptance_token_hash', 64)->nullable(); // Portal-Annahme
            $table->timestamp('decided_at')->nullable();
            $table->json('decision_snapshot')->nullable(); // angenommene Positionen, eingefroren
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'qte_created_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number', 'version'], 'qte_org_no_ver_unique');
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'qti_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('quote_id')
                ->constrained('quotes', indexName: 'qti_quote_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->boolean('optional')->default(false); // Option (Eventualposition)
            $table->boolean('accepted')->nullable();     // Teilannahme je Position
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // MVP-172: Widerspruch gegen umsatzsteuerliche Gutschrift.
            $table->timestamp('objection_at')->nullable();
            $table->string('objection_note', 500)->nullable();
            // MVP-170: Herkunftsbezug Angebot → Rechnung.
            $table->foreignId('quote_id')->nullable()
                ->constrained('quotes', indexName: 'inv_quote_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_id');
            $table->dropColumn(['objection_at', 'objection_note']);
        });
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
