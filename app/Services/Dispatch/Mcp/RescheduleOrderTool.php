<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RescheduleOrderTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch\Mcp;

use App\Enums\Api\ApiAbility;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Compliance\ComplianceViolation;
use App\Services\Dispatch\DispatchConflictChecker;
use App\Services\Mcp\GuardedTool;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};

/**
 * MCP (MVP-1064): Einsatz eines Auftrags verschieben. Harte Konflikte der
 * Disposition (Überschneidung, Ruhezeit, Urlaub …) blockieren — übersteuern
 * kann nur ein Mensch in workDiary.
 */
#[Name('reschedule_order')]
#[Description('Verschiebt den Einsatz eines Auftrags auf neuen Beginn und neues Ende (ISO-8601 mit Zeitzone). Harte Planungskonflikte blockieren und werden gemeldet; Warnungen werden mitgeliefert.')]
final class RescheduleOrderTool extends GuardedTool {
    public function __construct(private readonly DispatchConflictChecker $conflicts) {}

    public function ability(): ApiAbility {
        return ApiAbility::McpWrite;
    }

    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->required()->description('Kennung des Auftrags.'),
            'start_at' => $schema->string()->format('date-time')->required()->description('Neuer Beginn, ISO-8601 mit Zeitzone.'),
            'end_at' => $schema->string()->format('date-time')->required()->description('Neues Ende, ISO-8601 mit Zeitzone.'),
        ];
    }

    protected function routeName(): string {
        return 'dispatch.conflicts';
    }

    protected function authorize(User $user): bool {
        return true;
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:64'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ]);
        $order = DiaryEntry::query()->where('organization_id', $organization->id)->whereKey(Sqid::decode(DiaryEntry::class, $data['id']) ?? 0)->first();
        if ($order === null || ! $user->can('update', $order)) {
            return Response::error((string) __('mcp.error.not_found'));
        }
        $start = Carbon::parse($data['start_at'])->utc();
        $end = Carbon::parse($data['end_at'])->utc();

        $report = $this->conflicts->check($order, null, $start, $end);
        $blocking = $this->conflicts->blockingConflicts($report);
        if ($blocking !== []) {
            return Response::error((string) __('mcp.error.conflicts', ['list' => implode('; ', array_map(static fn (ComplianceViolation $v): string => $v->message, $blocking))]));
        }
        $previous = ['start_at' => $order->start_at?->toIso8601String(), 'end_at' => $order->end_at?->toIso8601String()];
        $order->update(['start_at' => $start, 'end_at' => $end]);
        $order->audit('mcp.write', [...$this->writeContext($user), 'previous' => $previous]);

        return Response::structured([
            'id' => $order->sqid,
            'start_at' => $order->start_at?->toIso8601String(),
            'end_at' => $order->end_at?->toIso8601String(),
            'warnings' => array_map(static fn (ComplianceViolation $v): string => $v->message, $this->conflicts->warnings($report)),
        ]);
    }
}
