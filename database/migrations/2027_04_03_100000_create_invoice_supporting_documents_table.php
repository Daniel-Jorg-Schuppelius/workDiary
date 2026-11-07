<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_03_100000_create_invoice_supporting_documents_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rechnungsbegründende Unterlagen einer Rechnung (BG-24, Feature 196, MVP-1485). */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_supporting_documents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Träger ist eine lokale Rechnung oder eine extern ausgestellte (external_invoice_id, Folgemigration).
            $t->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('kind', 32);
            // BT-122 bis BT-125: Kennung, Beschreibung, Link, Dateiname und MIME des Anhangs.
            $t->string('reference', 64);
            $t->string('description', 255)->nullable();
            $t->string('external_url', 2048)->nullable();
            $t->string('filename', 255)->nullable();
            // Kleinschreibung des Dateinamens: BR-DE-22 verlangt Eindeutigkeit ohne Rücksicht auf die Schreibweise.
            $t->string('filename_key', 255)->nullable();
            $t->string('mime', 128)->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->char('sha256', 64)->nullable();
            $t->boolean('is_embedded')->default(true);
            // Mit der Ausstellung unveränderlich (Zustellnachweis-Hash, GoBD).
            $t->timestamp('frozen_at')->nullable();
            $t->timestamp('transferred_at')->nullable();
            $t->string('transfer_note', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['invoice_id', 'filename_key'], 'isd_invoice_filename_key_unique');
            $t->index(['invoice_id', 'position'], 'isd_invoice_position_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_supporting_documents');
    }
};
