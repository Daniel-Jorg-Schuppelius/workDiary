<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_07_100000_add_subject_to_chat_channels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1061: Chat-Kanal mit Träger (Projekt oder Auftrag), höchstens einer je Träger. */
return new class extends Migration {
    public function up(): void {
        Schema::table('chat_channels', function (Blueprint $table): void {
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unique(['organization_id', 'subject_type', 'subject_id'], 'chat_channels_subject_unique');
        });
    }

    public function down(): void {
        Schema::table('chat_channels', function (Blueprint $table): void {
            $table->dropUnique('chat_channels_subject_unique');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
