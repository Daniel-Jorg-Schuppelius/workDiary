<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_150000_create_boq_change_orders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachtragsköpfe eines Leistungsverzeichnisses (GAEB `COInfo`).
 *
 * Ein Bauvorhaben sammelt über die Laufzeit mehrere Nachträge nebeneinander:
 * N1 kann genehmigt sein, während N2 noch angeboten und N3 abgelehnt ist. Jeder
 * trägt eigene Begründung, eigenes Datum und eigenen Status — deshalb eine
 * eigene Tabelle statt Spalten am LV. Die Positionen hängen über ihre
 * `change_order_no` daran; ihr Status hat dabei Vorrang vor dem des Kopfes.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('boq_change_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'boqco_org_fk')->cascadeOnDelete();
            $table->foreignId('bill_of_quantity_id')->constrained('bill_of_quantities', indexName: 'boqco_boq_fk')->cascadeOnDelete();

            $table->string('number', 30);                       // CONo
            $table->string('phase', 20)->nullable();            // COPhase
            $table->string('status', 20)->nullable();           // COStatus
            $table->string('initiator', 20)->nullable();        // COInit
            $table->text('reason')->nullable();                 // COReas
            $table->string('contract_reference', 60)->nullable(); // RefBoQCOInfo
            $table->date('date')->nullable();                   // CODate
            $table->timestamps();

            $table->unique(['bill_of_quantity_id', 'number'], 'boqco_boq_number_uq');
            $table->index(['organization_id', 'status'], 'boqco_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boq_change_orders');
    }
};
