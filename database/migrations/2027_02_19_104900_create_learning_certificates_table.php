<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104900_create_learning_certificates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zertifikate und Qualifikations-Kopplung (Feature 149, MVP-740).
 *
 * Das Zertifikat ist der Nachweis der Lernplattform; der
 * arbeitsschutzrechtliche Nachweis bleibt die Unterweisung im Register
 * (Feature 132) und das Soll in Feature 145 — hier entsteht **keine**
 * dritte Nachweiswelt.
 *
 * `verification_code` erlaubt einem Auftraggeber die stichprobenartige
 * Prüfung, ohne bei uns anzurufen. Widerrufene Zertifikate verschwinden
 * nicht, sie zeigen den Widerruf.
 *
 * Kein SoftDelete — ein ausgestelltes Zertifikat ist ein Nachweis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->foreignId('learning_course_version_id')->nullable()->constrained('learning_course_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('external_participant_id')->nullable()->constrained('external_participants')->nullOnDelete();
            // Lückenlose Nummer je Organisation (NumberSequence).
            $table->string('number', 40);
            // Zufälliger Prüfcode für die öffentliche Verifikation.
            $table->string('verification_code', 32);
            $table->string('holder_name', 180);
            $table->date('issued_on');
            $table->date('valid_until')->nullable();
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'lrn_cert_org_no_uq');
            $table->unique('verification_code', 'lrn_cert_code_uq');
            $table->index(['organization_id', 'valid_until'], 'lrn_cert_org_valid_idx');
        });

        Schema::table('learning_courses', function (Blueprint $table): void {
            // Welche Qualifikation der Kursabschluss verleiht bzw. verlängert
            // (Feature 013). Die Sperrwirkung bleibt dort — kein zweiter Guard.
            $table->foreignId('qualification_id')->nullable()->after('training_course_id')->constrained('qualifications')->nullOnDelete();
            // Erzeugt der Abschluss einen Unterweisungsnachweis im
            // Arbeitsschutz-Register (Feature 132)?
            $table->boolean('creates_instruction_proof')->default(false)->after('certificate_enabled');
        });
    }

    public function down(): void {
        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropColumn('creates_instruction_proof');
            $table->dropConstrainedForeignId('qualification_id');
        });

        Schema::dropIfExists('learning_certificates');
    }
};
