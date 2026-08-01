<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_10_100000_create_print_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Druckauftrag (MVP-459, Branchenprofil Druck/Kopiershop):
 *
 *  - `print_orders`: 1:1-Spezialisierung eines `ManufacturingOrder` — kein
 *    paralleles Auftrags-/Produktionsmodell. Mengen, Material, Lager und
 *    Nachkalkulation laufen weiter über die Fertigung; hier liegen nur die
 *    drucktypischen Nachweise: Datei-Hash-Bindung, Preflight-Befunde,
 *    Freigabe (Person/Zeit/Hash/Snapshot unveränderlich), Maschinenbezug,
 *    Qualitätskontrolle, Ausgabe und Löschfrist der Produktionsdatei.
 *  - Produktionsdateien liegen im vorhandenen Dokumentenspeicher
 *    (documents/document_versions); die Löschfrist entfernt nur die Datei,
 *    nie den kaufmännischen Nachweis (Auftrag, Snapshot, Hash bleiben).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('print_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'prord_org_fk')->cascadeOnDelete();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders', indexName: 'prord_mo_fk')->cascadeOnDelete();

            $table->string('status', 24)->default('data_check');
            $table->string('output_kind', 16)->default('pickup');     // pickup | shipping | counter

            // Produktionsdatei: Dokumentenspeicher + SHA-256-Bindung. Eine neue
            // Dateiversion setzt den Auftrag zurück auf Datenprüfung.
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'prord_doc_fk')->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions', indexName: 'prord_docver_fk')->nullOnDelete();
            $table->string('file_hash', 64)->nullable();              // SHA-256 der gebundenen Datei
            $table->timestamp('file_bound_at')->nullable();

            // Preflight: providerneutraler Befund (Fehler blockieren Freigabe).
            $table->string('preflight_status', 16)->default('pending');
            $table->string('preflight_provider', 64)->nullable();
            $table->json('preflight_findings')->nullable();           // {errors: [], warnings: []}
            $table->timestamp('preflight_at')->nullable();
            $table->foreignId('preflight_by')->nullable()->constrained('users', indexName: 'prord_preflight_fk')->nullOnDelete();
            $table->text('preflight_override_reason')->nullable();
            $table->foreignId('preflight_overridden_by')->nullable()->constrained('users', indexName: 'prord_override_fk')->nullOnDelete();
            $table->timestamp('preflight_overridden_at')->nullable();

            // Freigabe: Person, Zeitpunkt, Datei-Hash und Snapshot unveränderlich.
            $table->json('production_snapshot')->nullable();          // Format/Farben/Material/Menge/Termin/…
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'prord_approver_fk')->nullOnDelete();
            $table->string('approved_file_hash', 64)->nullable();

            // Produktion: Maschine (Asset) mit Sperr-/Prüf-/Kalibrier-Gate.
            $table->foreignId('asset_id')->nullable()->constrained('assets', indexName: 'prord_asset_fk')->nullOnDelete();
            $table->timestamp('production_started_at')->nullable();
            $table->foreignId('production_started_by')->nullable()->constrained('users', indexName: 'prord_prodstart_fk')->nullOnDelete();

            // Qualitätskontrolle gegen Freigabestand und Auftragsparameter.
            $table->string('qc_status', 16)->nullable();              // passed | rework | blocked
            $table->timestamp('qc_at')->nullable();
            $table->foreignId('qc_by')->nullable()->constrained('users', indexName: 'prord_qc_fk')->nullOnDelete();
            $table->text('qc_note')->nullable();

            // Ausgabe: Abholung (Übergabenachweis), Versand oder Tresen —
            // Empfängername datensparsam und verschlüsselt (nur wenn nötig).
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users', indexName: 'prord_issuer_fk')->nullOnDelete();
            $table->text('handover_name')->nullable();
            $table->text('handover_note')->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments', indexName: 'prord_shipment_fk')->nullOnDelete();

            // Löschfrist der Produktionsdatei (kaufmännischer Nachweis bleibt).
            $table->date('files_retain_until')->nullable();
            $table->timestamp('files_purged_at')->nullable();

            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'prord_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('manufacturing_order_id', 'prord_mo_unique');
            $table->index(['organization_id', 'status'], 'prord_org_status_idx');
            $table->index(['organization_id', 'files_retain_until'], 'prord_org_retain_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('print_orders');
    }
};
