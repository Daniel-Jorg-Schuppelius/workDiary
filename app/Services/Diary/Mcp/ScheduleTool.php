<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Mcp;

use App\Models\Calendar\Event;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Mcp\GuardedTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Einsätze aus dem Auftragsbuch und eigene Kalendertermine eines Zeitraums. */
#[Name('schedule')]
#[Description('Einsätze (Aufträge mit Termin) und eigene Kalendertermine in einem Zeitraum, Standard: die nächsten sieben Tage.')]
#[IsReadOnly]
#[IsIdempotent]
final class ScheduleTool extends GuardedTool {
    public function schema(JsonSchema $schema): array {
        return [
            'from' => $schema->string()->format('date')->description('Erster Tag (JJJJ-MM-TT), Standard heute.'),
            'to' => $schema->string()->format('date')->description('Letzter Tag (JJJJ-MM-TT), Standard sechs Tage nach dem ersten.'),
            'mine' => $schema->boolean()->description('Nur eigene Einsätze.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl je Art, Standard 50.'),
        ];
    }

    protected function routeName(): string {
        return 'diary.index';
    }

    protected function authorize(User $user): bool {
        return true;
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'mine' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : Carbon::today();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : $from->copy()->addDays(6)->endOfDay();
        $limit = $this->limit($request, 50);

        $orders = DiaryEntry::query()->where('organization_id', $organization->id)->visibleInBulkTo($user)
            ->where('is_archived', false)
            ->whereNotNull('start_at')
            ->whereBetween('start_at', [$from, $to])
            ->when($data['mine'] ?? false, static fn ($q) => $q->where('user_id', $user->id))
            ->with(['user:id,name', 'customer:id,name'])
            ->orderBy('start_at')
            ->limit($limit)
            ->get()
            ->map(static fn (DiaryEntry $order): array => [
                'id' => $order->sqid,
                'title' => $order->title,
                'start_at' => $order->start_at?->toIso8601String(),
                'end_at' => $order->end_at?->toIso8601String(),
                'status' => $order->statusLabel(),
                'user' => $order->user?->name,
                'customer' => $order->customer?->name,
            ])->all();

        $calendarModule = app(ModuleRegistry::class)->moduleForRoute('calendar.index');
        $events = $calendarModule !== null && ! app(ModuleStatusResolver::class)->isActiveFor($organization, $calendarModule)
            ? []
            : Event::query()->where('organization_id', $organization->id)->forUser($user)->inRange($from, $to)
                ->whereNull('cancelled_at')
                ->orderBy('started_at')
                ->limit($limit)
                ->get()
                ->map(static fn (Event $event): array => [
                    'id' => $event->sqid,
                    'title' => $event->title,
                    'start_at' => $event->started_at->toIso8601String(),
                    'end_at' => $event->ended_at->toIso8601String(),
                    'all_day' => (bool) $event->is_all_day,
                    'topic' => $event->topic,
                ])->all();

        return Response::structured(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'orders' => $orders, 'events' => $events]);
    }
}
