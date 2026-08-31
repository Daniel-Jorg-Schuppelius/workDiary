<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_110600_create_audit_redactions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll der Audit-Schwärzungen (Sicherheitsscan 2026-08-23, S-21).
 *
 * Das Audit-Protokoll ist append-only und hash-verkettet: ein Wert, der einmal
 * darin steht, lässt sich nicht mehr entfernen, ohne die Kette zu brechen.
 * Für ein Löschverlangen nach Art. 17 DSGVO braucht es dennoch einen Weg —
 * und der ist nur dann etwas wert, wenn der Eingriff selbst nachweisbar ist.
 *
 * Deshalb wird beim Schwärzen die Kette ab der betroffenen Zeile neu gerechnet
 * (`audit:verify` bleibt damit grün) und der Vorgang hier festgehalten: was,
 * wann, durch wen, aus welchem Anlass, mit welchem Kettenkopf davor und
 * danach. Diese Tabelle ist selbst hash-verkettet und append-only — wer eine
 * Schwärzung verbergen will, müsste beide Ketten fälschen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('audit_redactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->string('chain', 64)->comment('Geschwärzte Kette, z. B. audit_logs:7');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('fields')->comment('Geschwärzte Feldnamen');

            $table->unsignedInteger('rows_affected');
            $table->unsignedBigInteger('first_audit_log_id');
            $table->unsignedBigInteger('last_audit_log_id');

            $table->text('reason');
            $table->string('request_reference')->nullable()->comment('Aktenzeichen des Betroffenenverlangens');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('head_before', 64)->nullable();
            $table->string('head_after', 64)->nullable();

            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'audit_redactions_subject_idx');
            $table->index('chain', 'audit_redactions_chain_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('audit_redactions');
    }
};
