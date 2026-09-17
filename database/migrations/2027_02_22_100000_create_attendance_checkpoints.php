<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100000_create_attendance_checkpoints.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in-Punkte (MVP-800, Features 001/004): ein QR-Code oder NFC-Aufkleber
 * an einem Standort oder Fahrzeug. Anders als beim Terminal (Feature 061) ist
 * die Person angemeldet und stempelt mit dem eigenen Gerät; der Punkt belegt nur
 * Ort bzw. Fahrzeug. Der Token ist deshalb kein Geheimnis, sondern steht offen
 * im Code am Aufkleber — er wird im Klartext gehalten, damit der Code jederzeit
 * neu gedruckt werden kann.
 *
 * Optional ein Radius: Dann muss das Gerät beim Stempeln seine Position senden.
 * Gespeichert wird nur, an welchem Punkt gestempelt wurde — nicht die Position.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('attendance_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'attcp_org_fk')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 16);
            $table->foreignId('site_id')->nullable()->constrained('sites', indexName: 'attcp_site_fk')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles', indexName: 'attcp_vehicle_fk')->nullOnDelete();
            $table->string('token', 64)->unique('attcp_token_unique');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_m')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'attcp_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'active'], 'attcp_org_active_idx');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            if ($this->isSqlite()) {
                // Ein Fremdschlüssel zwingt SQLite, die Tabelle neu aufzubauen — und
                // dabei geht die Bedingung des Unique-Index „eine offene Anwesenheit
                // je Person" (WHERE ended_at IS NULL) verloren. Deshalb hier nur
                // Spalten mit Index; die Integrität sichern MySQL/MariaDB.
                $table->unsignedBigInteger('started_checkpoint_id')->nullable();
                $table->unsignedBigInteger('ended_checkpoint_id')->nullable();
                $table->index('started_checkpoint_id', 'att_started_cp_idx');
                $table->index('ended_checkpoint_id', 'att_ended_cp_idx');

                return;
            }
            $table->foreignId('started_checkpoint_id')->nullable()->after('ended_device')
                ->constrained('attendance_checkpoints', indexName: 'att_started_cp_fk')->nullOnDelete();
            $table->foreignId('ended_checkpoint_id')->nullable()->after('started_checkpoint_id')
                ->constrained('attendance_checkpoints', indexName: 'att_ended_cp_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            if ($this->isSqlite()) {
                $table->dropIndex('att_started_cp_idx');
                $table->dropIndex('att_ended_cp_idx');
            } else {
                $table->dropForeign('att_started_cp_fk');
                $table->dropForeign('att_ended_cp_fk');
            }
            $table->dropColumn(['started_checkpoint_id', 'ended_checkpoint_id']);
        });
        Schema::dropIfExists('attendance_checkpoints');
    }

    private function isSqlite(): bool {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
