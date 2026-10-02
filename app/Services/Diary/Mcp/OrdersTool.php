<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrdersTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Mcp;

use App\Http\Resources\DiaryEntryResource;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Mcp\GuardedTool;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/** MCP (MVP-1063): Aufträge (Auftragsbuch) mit derselben Sichtbarkeit wie die Arbeitsliste. */
#[Name('orders')]
#[Description('Aufträge aus dem Auftragsbuch auflisten (Zeitraum, Kunde, Projekt, nur offene) oder mit `id` einen Auftrag mit Tags anzeigen.')]
#[IsReadOnly]
#[IsIdempotent]
final class OrdersTool extends GuardedTool {
    public function schema(JsonSchema $schema): array {
        return [
            'id' => $schema->string()->description('Kennung eines Auftrags für die Detailansicht.'),
            'from' => $schema->string()->format('date')->description('Beginn ab diesem Tag (JJJJ-MM-TT).'),
            'to' => $schema->string()->format('date')->description('Beginn bis zu diesem Tag (JJJJ-MM-TT).'),
            'customer' => $schema->string()->description('Kennung eines Kunden.'),
            'project' => $schema->string()->description('Kennung eines Projekts.'),
            'open_only' => $schema->boolean()->description('Nur noch nicht abgeschlossene Aufträge.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Treffer, Standard 20.'),
        ];
    }

    protected function routeName(): string {
        return 'diary.index';
    }

    protected function authorize(User $user): bool {
        return true;
    }

    protected function respond(Request $request, User $user, Organization $organization): Response|ResponseFactory {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'customer' => ['nullable', 'string', 'max:64'],
            'project' => ['nullable', 'string', 'max:64'],
            'open_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $orders = DiaryEntry::query()->where('organization_id', $organization->id)->visibleInBulkTo($user)
            ->with(['user:id,name', 'tags', 'customer:id,name', 'project:id,name']);

        if (isset($data['id'])) {
            $order = $orders->whereKey(Sqid::decode(DiaryEntry::class, $data['id']) ?? 0)->first();
            if ($order === null || ! $user->can('view', $order)) {
                return Response::error((string) __('mcp.error.not_found'));
            }

            return Response::structured(['order' => self::row($order)]);
        }

        $list = $orders
            ->where('is_archived', false)
            ->when(isset($data['from']), static fn ($q) => $q->where('start_at', '>=', DateRange::dayStart($data['from'])))
            ->when(isset($data['to']), static fn ($q) => $q->where('start_at', '<', DateRange::dayAfter($data['to'])))
            ->when(isset($data['customer']), static fn ($q) => $q->where('customer_id', Sqid::decode(Customer::class, (string) $data['customer']) ?? 0))
            ->when(isset($data['project']), static fn ($q) => $q->where('project_id', Sqid::decode(Project::class, (string) $data['project']) ?? 0))
            ->when($data['open_only'] ?? false, static fn ($q) => $q->open())
            ->orderByDesc('start_at')
            ->limit($this->limit($request))
            ->get();

        return Response::structured(['orders' => $list->map(static fn (DiaryEntry $order): array => self::row($order))->all()]);
    }

    /** @return array<string, mixed> */
    private static function row(DiaryEntry $order): array {
        return [
            ...DiaryEntryResource::make($order)->resolve(),
            'title' => $order->title,
            'customer' => $order->customer !== null ? ['id' => $order->customer->sqid, 'name' => $order->customer->name] : null,
            'project' => $order->project !== null ? ['id' => $order->project->sqid, 'name' => $order->project->name] : null,
        ];
    }
}
