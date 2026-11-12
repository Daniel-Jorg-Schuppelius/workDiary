<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_200000_change_accepted_final_by_to_signer_name.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * `accepted_final_by` trägt den Namen der abnehmenden Person beim Kunden (MVP-1596),
 * bisher stand dort die Nutzer-ID des Mitarbeiters. Die geht nicht verloren: Sie steht
 * als Akteur im Journal (`order.handover`) bzw. als Ersteller am verknüpften Protokoll.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_final_by');
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->string('accepted_final_by', 120)->nullable()->after('accepted_final_at');
        });

        DB::table('diary_entries')
            ->whereNotNull('accepted_final_at')
            ->whereNotNull('protocol_id')
            ->orderBy('id')
            ->select(['id', 'protocol_id'])
            ->chunkById(500, function ($entries): void {
                // Wie OrderService::customerSignerName(): die jüngste Kundenunterschrift (Zeitpunkt, dann ID) je Protokoll.
                $names = [];
                $signatures = DB::table('protocol_signatures')
                    ->whereIn('protocol_id', $entries->pluck('protocol_id')->all())
                    ->where('role', 'customer')
                    ->orderByDesc('signed_at')
                    ->orderByDesc('id')
                    ->get(['protocol_id', 'signer_name']);
                foreach ($signatures as $signature) {
                    $names[(int) $signature->protocol_id] ??= $signature->signer_name;
                }
                foreach ($entries as $entry) {
                    $name = trim((string) ($names[(int) $entry->protocol_id] ?? ''));
                    if ($name !== '') {
                        DB::table('diary_entries')->where('id', $entry->id)->update(['accepted_final_by' => mb_substr($name, 0, 120)]);
                    }
                }
            });
    }

    public function down(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropColumn('accepted_final_by');
        });
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->foreignId('accepted_final_by')->nullable()->after('accepted_final_at')->constrained('users')->nullOnDelete();
        });

        DB::table('diary_entries')
            ->whereNotNull('accepted_final_at')
            ->orderBy('id')
            ->select(['id', 'protocol_id'])
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $userId = DB::table('diary_entry_events')
                        ->where('diary_entry_id', $entry->id)
                        ->where('event', 'order.handover')
                        ->orderByDesc('id')
                        ->value('actor_user_id')
                        ?? ($entry->protocol_id !== null ? DB::table('protocols')->where('id', $entry->protocol_id)->value('created_by_user_id') : null);
                    if ($userId !== null && DB::table('users')->where('id', $userId)->exists()) {
                        DB::table('diary_entries')->where('id', $entry->id)->update(['accepted_final_by' => $userId]);
                    }
                }
            });
    }
};
