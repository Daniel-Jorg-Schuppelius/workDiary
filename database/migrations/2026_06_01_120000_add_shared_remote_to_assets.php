<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_01_120000_add_shared_remote_to_assets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennzeichnet Geräte, die für mehrere Kunden genutzt werden (z. B. ein zentraler
 * Fernwartungs-PC). Deren Fernwartungs-Sitzungen werden nicht automatisch auf den
 * Asset-Kunden gebucht, sondern landen in der Inbox zur Zuordnung je Sitzung.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('assets') || Schema::hasColumn('assets', 'shared_remote')) {
            return;
        }
        Schema::table('assets', function (Blueprint $table): void {
            $table->boolean('shared_remote')->default(false)->after('customer_id');
        });
    }

    public function down(): void {
        if (! Schema::hasTable('assets') || ! Schema::hasColumn('assets', 'shared_remote')) {
            return;
        }
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('shared_remote');
        });
    }
};
