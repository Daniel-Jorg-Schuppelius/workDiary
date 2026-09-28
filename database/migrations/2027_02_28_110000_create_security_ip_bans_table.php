<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_110000_create_security_ip_bans_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-450: temporäre IP-Sperren nach Fehlversuchen (installationsweit, Standard aus). */
return new class extends Migration {
    public function up(): void {
        Schema::create('security_ip_bans', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->unsignedTinyInteger('level');
            $table->string('reason', 40);
            $table->timestamp('banned_until');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users', indexName: 'sec_ipban_releaser_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['ip', 'banned_until'], 'sec_ipban_ip_until_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('security_ip_bans');
    }
};
