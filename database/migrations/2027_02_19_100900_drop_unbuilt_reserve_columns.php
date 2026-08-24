<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vollscan 2026-08-23, F13: Vorhalte-Spalten für nie gebaute Entitäten —
 * protocols.template_id/template_version (keine Protokoll-Vorlagen gebaut;
 * StoreProtocolRequest akzeptierte beliebige Integer), protocol_signatures.
 * signer_contact_id (kein Kontakt-Picker im Signatur-Flow), diary_entries.
 * status_legacy („für 1 Release" — das war MVP-104). BEWUSST NICHT gedroppt:
 * communication_note_participants.customer_contact_id (wird real geschrieben;
 * „bauen": Verknüpfung auf contact_persons, Produktwelle) und
 * diary_entries.planned_start_at/_end_at (Kandidat Disposition/GapFill).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropColumn(['template_id', 'template_version']);
        });
        Schema::table('protocol_signatures', function (Blueprint $table): void {
            $table->dropColumn('signer_contact_id');
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropColumn('status_legacy');
        });
    }

    public function down(): void {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('template_version')->nullable();
        });
        Schema::table('protocol_signatures', function (Blueprint $table): void {
            $table->unsignedBigInteger('signer_contact_id')->nullable();
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->integer('status_legacy')->nullable();
        });
    }
};
