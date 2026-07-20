<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_25_100100_create_job_application_uploads_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-437: Quarantäne für öffentlich hochgeladene Bewerbungsunterlagen.
 *
 * Bewusst getrennt von {@see \App\Models\Applications\JobApplicationDocument}
 * (DMS-gebunden): öffentliche Uploads liegen zunächst auf einem privaten Disk
 * mit Zufallsschlüssel und Status `pending` und werden erst nach erfolgreichem
 * Malware-Scan (`clean`) für Personalberechtigte bzw. das DMS freigegeben — die
 * interne Recruiting-Ablage bleibt unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('job_application_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jau_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'jau_app_fk')->cascadeOnDelete();
            $table->string('storage_disk', 40);
            $table->string('storage_key', 191);
            $table->string('original_name', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64);
            $table->string('scan_status', 16)->default('pending'); // pending|clean|rejected
            $table->timestamps();

            $table->index(['organization_id', 'scan_status'], 'jau_org_scan_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_application_uploads');
    }
};
