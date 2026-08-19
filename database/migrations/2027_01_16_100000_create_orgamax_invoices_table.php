<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_16_100000_create_orgamax_invoices_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Lokaler Belegspiegel für orgaMAX-Rechnungen (Feature 077-Fix, MVP-653).
 *
 * **Behobener Bestandsfehler:** Die Belegprojektion hängte bisher JEDE
 * Rechnung als {@see \App\Models\ExternalReference} an dieselbe
 * `OrgaMaxConnection`. Der Unique-Index `extref_unique`
 * (plugin_id, external_type, referenceable_type, referenceable_id) lässt je
 * Zielmodell aber nur EINE Referenz zu — ab der zweiten Rechnung brach die
 * Projektion mit einem Constraint-Fehler ab. Jede Rechnung bekommt jetzt ein
 * eigenes lokales Objekt (Muster `lexoffice_vouchers`); die Referenz zeigt
 * darauf, der Index passt wieder, und die Historie ist unabhängig von der
 * API lesbar (Grundlage für den Buchhaltungswechsel in beide Richtungen).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('orgamax_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_external_id', 64)->nullable();
            $table->string('customer_name', 191)->nullable();
            $table->string('invoice_type', 32)->nullable();
            $table->string('invoice_status', 32)->nullable();
            $table->string('invoice_number', 64)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('total_net', 14, 2)->nullable();
            $table->decimal('total_gross', 14, 2)->nullable();
            $table->decimal('outstanding_amount', 14, 2)->nullable();
            // orgaMAX rechnet ausschließlich in Euro (die API führt kein Feld).
            $table->string('currency', 3)->default('EUR');
            $table->json('payload')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id'], 'omi_org_external_unique');
            $table->index(['organization_id', 'invoice_status'], 'omi_org_status_idx');
        });

        $this->migrateExistingProjections();
    }

    public function down(): void {
        Schema::dropIfExists('orgamax_invoices');
    }

    /**
     * Bestandsprojektionen (die eine Rechnung, die der Fehler durchgelassen
     * hat) in den Spiegel überführen und die Referenz umhängen.
     */
    private function migrateExistingProjections(): void {
        $references = DB::table('external_references')
            ->where('plugin_id', 'orgamax')
            ->where('external_type', 'orgamax_invoice')
            ->get();

        foreach ($references as $reference) {
            $payload = json_decode((string) ($reference->payload ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];

            $invoiceId = DB::table('orgamax_invoices')->insertGetId([
                'organization_id' => $reference->organization_id,
                'external_id' => (string) $reference->external_id,
                'customer_external_id' => $this->stringOrNull($payload['customer_id'] ?? null),
                'customer_name' => $this->stringOrNull($payload['customer'] ?? null),
                'invoice_type' => $this->stringOrNull($payload['type'] ?? null),
                'invoice_status' => $this->stringOrNull($payload['status'] ?? null),
                'invoice_number' => $this->stringOrNull($payload['number'] ?? null),
                'invoice_date' => $this->dateOrNull($payload['date'] ?? null),
                'due_on' => $this->dateOrNull($payload['due_on'] ?? null),
                'total_net' => $this->numberOrNull($payload['total_net'] ?? null),
                'total_gross' => $this->numberOrNull($payload['total_gross'] ?? null),
                'outstanding_amount' => $this->numberOrNull($payload['outstanding_amount'] ?? null),
                'currency' => $this->stringOrNull($payload['currency'] ?? null) ?? 'EUR',
                'payload' => (string) ($reference->payload ?? null),
                'synced_at' => $reference->synced_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('external_references')->where('id', $reference->id)->update([
                'referenceable_type' => 'App\Models\OrgaMaxInvoice',
                'referenceable_id' => $invoiceId,
            ]);
        }
    }

    private function stringOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, 191);
    }

    private function dateOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function numberOrNull(mixed $value): ?float {
        return is_numeric($value) ? (float) $value : null;
    }
};
