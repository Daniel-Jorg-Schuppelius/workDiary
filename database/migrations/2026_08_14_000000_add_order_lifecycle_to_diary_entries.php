<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_14_000000_add_order_lifecycle_to_diary_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->tinyInteger('status_legacy')->nullable()->after('status');
            $table->timestamp('planned_start_at')->nullable()->after('status_legacy');
            $table->timestamp('planned_end_at')->nullable()->after('planned_start_at');
            $table->unsignedInteger('planned_duration_min')->nullable()->after('planned_end_at');
            $table->timestamp('accepted_at')->nullable()->after('planned_duration_min');
            $table->foreignId('accepted_by_user_id')->nullable()->after('accepted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->after('accepted_by_user_id');
            $table->timestamp('paused_at')->nullable()->after('started_at');
            $table->string('pause_reason', 32)->nullable()->after('paused_at');
            $table->text('pause_note')->nullable()->after('pause_reason');
            $table->timestamp('resumed_at')->nullable()->after('pause_note');
            $table->unsignedBigInteger('wait_seconds_total')->default(0)->after('resumed_at');
            $table->timestamp('completed_at')->nullable()->after('wait_seconds_total');
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->text('completion_summary')->nullable()->after('completed_by_user_id');
            $table->timestamp('accepted_final_at')->nullable()->after('completion_summary');
            $table->foreignId('accepted_final_by')->nullable()->after('accepted_final_at')->constrained('users')->nullOnDelete();
            $table->foreignId('signature_attachment_id')->nullable()->after('accepted_final_by')->constrained('attachments')->nullOnDelete();
            $table->foreignId('protocol_id')->nullable()->after('signature_attachment_id')->constrained('protocols')->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable()->after('protocol_id');
            $table->string('invoice_reference', 120)->nullable()->after('invoiced_at');
            $table->timestamp('cancelled_at')->nullable()->after('invoice_reference');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_user_id');

            $table->index(['organization_id', 'status', 'planned_start_at'], 'diary_lifecycle_status_idx');
        });

        Schema::create('diary_entry_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_entry_id')->constrained('diary_entries')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_kind', 16)->default('user');
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['diary_entry_id', 'occurred_at'], 'diary_events_entry_idx');
            $table->index(['organization_id', 'event', 'occurred_at'], 'diary_events_org_idx');
        });

        DB::table('diary_entries')->orderBy('id')->chunkById(200, function ($entries): void {
            foreach ($entries as $entry) {
                $status = (int) $entry->status;
                $signedProtocol = $status === -1
                    ? DB::table('protocols')
                        ->where('subject_type', \App\Models\DiaryEntry::class)
                        ->where('subject_id', $entry->id)
                        ->where('status', 'signed')
                        ->latest('signed_at')
                        ->first()
                    : null;
                $toStatus = match ($status) {
                    -1 => $signedProtocol ? 'accepted_final' : 'completed',
                    1 => 'in_progress',
                    3 => 'waiting_customer',
                    default => $entry->assigned_user_id ? 'accepted' : 'planned',
                };
                $newStatus = match (true) {
                    $status === 2 && $entry->assigned_user_id => 4,
                    $status === -1 && $signedProtocol !== null => 6,
                    default => $status,
                };
                $occurredAt = $entry->updated_at ?? $entry->created_at ?? now();

                $attributes = [
                    'status_legacy' => $status,
                    'status' => $newStatus,
                    'planned_start_at' => $entry->start_at,
                    'planned_end_at' => $entry->end_at,
                    'planned_duration_min' => $entry->planned_minutes ?? $entry->service_minutes,
                ];
                if ($newStatus === 4) {
                    $attributes['accepted_at'] = $occurredAt;
                    $attributes['accepted_by_user_id'] = $entry->assigned_user_id;
                } elseif ($status === 1) {
                    $attributes['started_at'] = DB::table('time_entries')
                        ->where('diary_entry_id', $entry->id)
                        ->min('started_at') ?? $entry->start_at ?? $occurredAt;
                } elseif ($status === -1) {
                    $attributes['completed_at'] = $entry->end_at ?? $occurredAt;
                    $attributes['completed_by_user_id'] = $entry->user_id;
                    if ($signedProtocol !== null) {
                        $attributes['accepted_final_at'] = $signedProtocol->signed_at ?? $occurredAt;
                        $attributes['accepted_final_by'] = $signedProtocol->created_by_user_id;
                        $attributes['protocol_id'] = $signedProtocol->id;
                    }
                } elseif ($status === 3) {
                    $attributes['paused_at'] = $occurredAt;
                    $attributes['pause_reason'] = 'other';
                    $attributes['pause_note'] = 'Aus dem bisherigen Status „Problem“ übernommen.';
                }

                DB::table('diary_entries')->where('id', $entry->id)->update($attributes);
                DB::table('diary_entry_events')->insert([
                    'diary_entry_id' => $entry->id,
                    'organization_id' => $entry->organization_id,
                    'event' => 'order.migrated',
                    'from_status' => match ($status) {
                        -1 => 'done',
                        1 => 'in_progress',
                        3 => 'problem',
                        default => 'open',
                    },
                    'to_status' => $toStatus,
                    'actor_user_id' => null,
                    'actor_kind' => 'system',
                    'note' => 'Automatisch in den Auftragslebenszyklus übernommen.',
                    'payload' => json_encode(['legacy_status' => $status], JSON_THROW_ON_ERROR),
                    'occurred_at' => $occurredAt,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void {
        Schema::dropIfExists('diary_entry_events');

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropIndex('diary_lifecycle_status_idx');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropConstrainedForeignId('protocol_id');
            $table->dropConstrainedForeignId('signature_attachment_id');
            $table->dropConstrainedForeignId('accepted_final_by');
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropConstrainedForeignId('accepted_by_user_id');
            $table->dropColumn([
                'status_legacy',
                'planned_start_at',
                'planned_end_at',
                'planned_duration_min',
                'accepted_at',
                'started_at',
                'paused_at',
                'pause_reason',
                'pause_note',
                'resumed_at',
                'wait_seconds_total',
                'completed_at',
                'completion_summary',
                'accepted_final_at',
                'invoiced_at',
                'invoice_reference',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
