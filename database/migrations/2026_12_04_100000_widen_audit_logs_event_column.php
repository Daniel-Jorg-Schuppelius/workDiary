<?php
/*
 * Created on   : Sun Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_04_100000_widen_audit_logs_event_column.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $table): void {
            // 25 der 383 Audit-Events sind länger als 32 Zeichen (max. 41,
            // z. B. isms.vulnerability.exploitability_decided) — auf
            // MySQL/MariaDB im strict mode crasht der Insert (1406).
            $table->string('event', 64)->change();
        });
    }

    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('event', 32)->change();
        });
    }
};
