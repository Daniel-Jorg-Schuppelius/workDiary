<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_150000_add_signature_attachment_to_protocol_signatures.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1572: Das Unterschriftsbild (gezeichnet oder Scan der Papierabnahme) ist
 * ein Anhang am Protokoll. `signature_image_path` trug früher Werte aus der
 * Eingabe und bleibt deshalb reine Angabe — gelesen und gelöscht wird nur über
 * den Anhang.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('protocol_signatures', function (Blueprint $table): void {
            $table->foreignId('signature_attachment_id')->nullable()->after('signature_image_path')->constrained('attachments')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('protocol_signatures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signature_attachment_id');
        });
    }
};
