<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_04_100000_add_portal_invite_fields_to_users.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenportal-Zugänge (MVP-510): Einladungs-Lebenszyklus an Portalkonten
 * (users mit customer_id). Der Token wird NIE im Klartext gespeichert —
 * nur sein SHA-256-Hash; Ablauf und Versandzeitpunkt machen die Zustände
 * eingeladen/abgelaufen ableitbar. Bestehende Portalkonten bleiben ohne
 * Invite-Felder schlicht „aktiv".
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('portal_invite_token_hash', 64)->nullable()->after('deactivated_at');
            $table->timestamp('portal_invite_expires_at')->nullable()->after('portal_invite_token_hash');
            $table->timestamp('portal_invited_at')->nullable()->after('portal_invite_expires_at');
            $table->index('portal_invite_token_hash', 'users_portal_invite_hash_idx');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_portal_invite_hash_idx');
            $table->dropColumn(['portal_invite_token_hash', 'portal_invite_expires_at', 'portal_invited_at']);
        });
    }
};
