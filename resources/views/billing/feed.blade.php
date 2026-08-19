{{--
  Created on   : Sun Aug 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : feed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('billing.feed.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('billing.feed.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\Billing\{DocumentDirection, DocumentKind, DocumentOrigin};
    use App\Http\Controllers\Billing\DocumentFeedController;
    use App\Support\Sqid;
    use CommonToolkit\Helper\Data\NumberHelper;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $rows */
    $money = static fn (float $value): string => NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true);

    // Tab-Zähler: Summe über die Achsenkombinationen, die der Tab abdeckt.
    $countFor = static function (string $key) use ($counts): int {
        $preset = DocumentFeedController::TABS[$key];
        $total = 0;
        foreach ($counts as $bucket => $value) {
            if ($bucket === 'overdue' || ! str_contains($bucket, ':')) {
                continue;
            }
            [$direction, $kind] = explode(':', $bucket, 2);
            if ($preset['kinds'] !== [] && ! in_array($kind, $preset['kinds'], true)) {
                continue;
            }
            if ($preset['directions'] !== [] && ! in_array($direction, $preset['directions'], true)) {
                continue;
            }
            $total += $value;
        }

        return $total;
    };

    $queryBase = array_filter([
        'q' => $filters['q'] ?: null,
        'origin' => $filters['origin'] ?: null,
        'contact' => $filters['contact'] ?: null,
        'state' => $filters['state'] ?: null,
        'overdue' => $filters['overdue'] ? 1 : null,
        'archived' => $filters['archived'] ? 1 : null,
        'unlinked' => ($filters['unlinked'] ?? false) ? 1 : null,
    ]);

    $tabItems = [];
    foreach (DocumentFeedController::TABS as $key => $preset) {
        $count = $countFor($key);
        // „Weitere" nur zeigen, wenn im Zeitraum wirklich etwas liegt —
        // sonst trägt die Leiste einen dauerhaft leeren Tab.
        if ($key === 'other' && $count === 0 && $tab !== 'other') {
            continue;
        }
        if ($preset['source'] !== null && ! ($sources[$preset['source']] ?? false)) {
            continue;
        }
        $tabItems[] = [
            'route' => 'billing.feed',
            'params' => ['tab' => $key] + $queryBase,
            'label' => __('billing.feed.tab.' . $key),
            'active' => $tab === $key,
            'count' => $count > 0 ? $count : null,
        ];
    }

    // Zielseite je Quelle — die Zeile führt dorthin, wo der Vorgang lebt.
    // orgaMAX-Belege haben keine lokale Detailseite: dort führt das
    // Fremdsystem, das PDF kommt über die Admin-Route des Plugins (MVP-654).
    $rowLink = static function (object $row) use ($canOpenOrgaMax): ?array {
        if ($row->source_type === 'orgamax_invoice') {
            return $canOpenOrgaMax
                ? [route('admin.orgamax.invoices.mirror-pdf', Sqid::encode(\App\Models\OrgaMaxInvoice::class, (int) $row->link_id)), true]
                : null;
        }

        return match ($row->source_type) {
            'invoice' => [route('invoices.show', Sqid::encode(\App\Models\Invoice::class, (int) $row->link_id)), false],
            'quote' => [route('quotes.show', Sqid::encode(\App\Models\Quote::class, (int) $row->link_id)), false],
            'voucher' => [route('lexoffice.vouchers.preview', Sqid::encode(\App\Models\LexofficeVoucher::class, (int) $row->link_id)), true],
            'incoming_einvoice' => [route('finance.incoming-invoices.show', Sqid::encode(\App\Models\Document::class, (int) $row->link_id)), false],
            'expense' => [route('expenses.receipt', Sqid::encode(\App\Models\Expense::class, (int) $row->link_id)), true],
            default => null,
        };
    };

    $stateTone = static fn (string $state): string => match ($state) {
        'paid' => 'success',
        'open' => 'warning',
        'cancelled' => 'ghost',
        default => 'neutral',
    };
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('billing.feed.subtitle', ['range' => $rangeLabel ?? ''])">
    <x-slot:actions>
        @if ($sources['quote'] ?? false)
            <x-icon-btn icon="request_quote" size="sm" :href="route('quotes.create')" show-label>
                {{ __('Neues Angebot') }}
            </x-icon-btn>
        @endif
        @if ($sources['invoice'] ?? false)
            <x-icon-btn icon="document_scanner" tone="secondary" size="sm"
                        data-entry-modal-trigger :href="route('invoices.pdf-import.create')" show-label>
                {{ __('invoice-import.action') }}
            </x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger :href="route('invoices.create')" show-label>
                {{ __('Neue Rechnung') }}
            </x-icon-btn>
        @endif
    </x-slot:actions>

    <x-tab-nav :items="$tabItems" />

    {{-- Kennzahlen über die GESAMTE gefilterte Menge, je Währung getrennt:
         keine Umrechnung, damit nie eine erfundene Summe entsteht. --}}
    @foreach ($totals as $sum)
        <div class="flex flex-wrap items-stretch gap-2">
            @if (count($totals) > 1)
                <div class="flex items-center rounded-box border border-base-300 bg-base-200 px-3 text-sm font-semibold">
                    {{ $sum['currency'] }}
                </div>
            @endif
            <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.revenue')"
                        :value="$money($sum['revenue']) . ' ' . $sum['currency']" format="raw" tone="success" />
            <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.expense')"
                        :value="$money($sum['expense']) . ' ' . $sum['currency']" format="raw" tone="warning" />
            <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.balance')"
                        :value="$money($sum['balance']) . ' ' . $sum['currency']" format="raw"
                        :tone="$sum['balance'] < 0 ? 'error' : 'primary'" />
            <x-kpi-tile class="min-w-40 flex-1"
                        :label="$scopeAll ? __('billing.feed.kpi.internal_all') : __('billing.feed.kpi.internal_mine')"
                        :value="$money($sum['internal']) . ' ' . $sum['currency']" format="raw" tone="neutral"
                        :hint="$sum['internalPending'] > 0.0 ? __('billing.feed.kpi.internal_pending', ['amount' => $money($sum['internalPending'])]) : null" />
            <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.open')"
                        :value="$money($sum['open']) . ' ' . $sum['currency']" format="raw" tone="info" />
            <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.overdue')"
                        :value="$money($sum['overdue']) . ' ' . $sum['currency']" format="raw" tone="error"
                        :active="$filters['overdue']"
                        :href="route('billing.feed', ['tab' => $tab, 'overdue' => $filters['overdue'] ? null : 1] + $queryBase)"
                        :hint="trans_choice('billing.feed.kpi.overdue_count', $sum['overdueCount'], ['count' => $sum['overdueCount']])" />
            @if ($sum['neutralCount'] > 0)
                <x-kpi-tile class="min-w-40 flex-1" :label="__('billing.feed.kpi.neutral')"
                            :value="$sum['neutralCount']" tone="neutral"
                            :hint="__('billing.feed.kpi.neutral_hint')" />
            @endif
        </div>
    @endforeach

    <x-filter-bar :action="route('billing.feed')"
                  :reset="($filters['q'] !== '' || $filters['origin'] !== '' || $filters['contact'] !== '' || $filters['state'] !== '' || $filters['overdue'] || $filters['archived'] || ($filters['unlinked'] ?? false))
                      ? route('billing.feed', ['tab' => $tab]) : null">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($scopeAll)
            <input type="hidden" name="scope" value="all">
        @endif

        <x-filter-field :label="__('Suche')" for="feed-q" class="flex-1 min-w-60">
            <input id="feed-q" type="text" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ __('billing.feed.search_placeholder') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>

        {{-- Richtung nur dort, wo der Tab sie offen lässt. --}}
        @if (in_array($tab, ['all', 'credits'], true))
            <x-filter-field :label="__('billing.feed.filter.direction')" for="feed-direction" class="w-40 shrink-0">
                <select id="feed-direction" name="direction" class="select select-sm select-bordered">
                    <option value="">{{ __('Alle') }}</option>
                    @foreach (DocumentDirection::cases() as $case)
                        <option value="{{ $case->value }}" @selected(request()->string('direction')->toString() === $case->value)>
                            {{ $case->label() }}
                        </option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif

        <x-filter-field :label="__('billing.feed.filter.origin')" for="feed-origin" class="w-40 shrink-0">
            <select id="feed-origin" name="origin" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach (DocumentOrigin::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['origin'] === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Zuordnung')" for="feed-contact" class="w-40 shrink-0">
            <select id="feed-contact" name="contact" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                <option value="customer" @selected($filters['contact'] === 'customer')>{{ __('Kunde') }}</option>
                <option value="supplier" @selected($filters['contact'] === 'supplier')>{{ __('Lieferant') }}</option>
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Status')" for="feed-state" class="w-40 shrink-0">
            <select id="feed-state" name="state" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach (['draft', 'open', 'paid', 'cancelled'] as $state)
                    <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ __('billing.feed.state.' . $state) }}</option>
                @endforeach
            </select>
        </x-filter-field>

        @if ($tab === 'expenses')
            <x-filter-toggle name="unlinked" :label="__('billing.feed.filter.only_unlinked')"
                             :checked="$filters['unlinked']" tone="warning" />
        @endif
        <x-filter-toggle name="overdue" :label="__('billing.feed.filter.only_overdue')"
                         :checked="$filters['overdue']" tone="error" />
        <x-filter-toggle name="archived" :label="__('billing.feed.filter.with_archived')"
                         :checked="$filters['archived']" />
    </x-filter-bar>

    @if ($tab === 'expenses' && $mayScopeAll)
        {{-- Umfang der Auslagen: derselbe Zustand steuert Liste UND Kennzahlen,
             sonst zeigt die Kachel einen anderen Ausschnitt als die Zeilen. --}}
        <div role="tablist" class="tabs tabs-box w-fit">
            <a role="tab" href="{{ route('billing.feed', ['tab' => 'expenses'] + $queryBase) }}"
               @class(['tab', 'tab-active' => ! $scopeAll])>{{ __('billing.feed.scope.mine') }}</a>
            <a role="tab" href="{{ route('billing.feed', ['tab' => 'expenses', 'scope' => 'all'] + $queryBase) }}"
               @class(['tab', 'tab-active' => $scopeAll])>{{ __('billing.feed.scope.all') }}</a>
        </div>
    @endif

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('billing.feed')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="['tab' => $tab] + ($scopeAll ? ['scope' => 'all'] : []) + $queryBase">
        <x-slot:head>
            <tr>
                <x-table.th sort="number">{{ __('Nummer') }}</x-table.th>
                <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                <x-table.th sort="kind">{{ __('billing.feed.column.kind') }}</x-table.th>
                <x-table.th sort="contact">{{ __('Zuordnung') }}</x-table.th>
                <th>{{ __('billing.feed.column.origin') }}</th>
                <x-table.th sort="state">{{ __('Status') }}</x-table.th>
                <x-table.th sort="due">{{ __('billing.feed.column.due') }}</x-table.th>
                <x-table.th sort="amount" align="right">{{ __('Betrag') }}</x-table.th>
                <th class="text-right">{{ __('billing.feed.column.open') }}</th>
                <th class="text-right"></th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            @php
                $kind = DocumentKind::tryFrom((string) $row->kind);
                $direction = DocumentDirection::tryFrom((string) $row->direction);
                $origin = DocumentOrigin::tryFrom((string) $row->origin);
                $due = $row->due_on ? \Illuminate\Support\Carbon::parse($row->due_on) : null;
                $isOverdue = $row->state === 'open' && $due !== null && $due->isPast();
                $overdueDays = $isOverdue ? $due->diffInDays(\Illuminate\Support\Carbon::today()) : 0;
                $signed = (float) $row->sign * (float) $row->amount_gross;
                $link = $rowLink($row);
            @endphp
            <tr @class(['bg-error/5' => $isOverdue])>
                <td class="font-medium tabular-nums">
                    @if ($link)
                        <a class="link link-hover" href="{{ $link[0] }}" @if ($link[1]) data-entry-modal-trigger @endif>
                            {{ $row->number !== '' ? $row->number : '—' }}
                        </a>
                    @else
                        {{ $row->number !== '' ? $row->number : '—' }}
                    @endif
                </td>
                <td class="tabular-nums text-sm text-base-content/70">
                    {{ $row->doc_date ? \Illuminate\Support\Carbon::parse($row->doc_date)->format('d.m.Y') : '—' }}
                </td>
                <td class="whitespace-nowrap text-base-content/70">
                    @if ($direction)
                        <x-icon :name="$direction->icon()" class="align-middle text-base-content/40" />
                    @endif
                    {{ $kind?->label() ?? '—' }}
                </td>
                <td>{{ $row->contact_name ?: '—' }}</td>
                <td>
                    <span class="wd-badge badge badge-sm badge-ghost">{{ $origin?->label() ?? '—' }}</span>
                </td>
                <td>
                    <x-status-badge :tone="$stateTone((string) $row->state)" size="sm">
                        {{ __('billing.feed.state.' . $row->state) }}
                    </x-status-badge>
                </td>
                <td class="tabular-nums text-sm whitespace-nowrap">
                    @if ($due)
                        {{ $due->format('d.m.Y') }}
                        @if ($isOverdue)
                            <span class="ml-1 font-semibold text-error">+{{ $overdueDays }}&nbsp;{{ __('billing.feed.days_short') }}</span>
                        @endif
                        @if ((int) $row->dunning_level > 0)
                            <span class="wd-badge badge badge-sm badge-warning ml-1">
                                {{ __('billing.feed.dunning_level', ['level' => (int) $row->dunning_level]) }}
                            </span>
                        @endif
                    @else
                        <span class="text-base-content/40">—</span>
                    @endif
                </td>
                <td @class(['text-right tabular-nums whitespace-nowrap', 'text-error' => $signed < 0.0])>
                    @if ((int) $row->sign === 0)
                        <span class="text-base-content/40">{{ $money((float) $row->amount_gross) }}</span>
                    @else
                        {{ $money($signed) }}
                    @endif
                    {{ $row->currency }}
                </td>
                <td class="text-right tabular-nums whitespace-nowrap">
                    {{ (float) $row->open_amount > 0.0 ? $money((float) $row->open_amount) : '—' }}
                </td>
                <td class="text-right">
                    {{-- Mahnen direkt aus der Zeile: der Umweg über die
                         Detailseite war der Grund, warum Überfälliges liegen blieb. --}}
                    <div class="flex justify-end gap-1">
                        @if ($isOverdue && $row->source_type === 'invoice' && $canDunLocal && (int) $row->dunning_level < 3)
                            <x-icon-btn icon="campaign" tone="warning" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('invoices.dun.form', Sqid::encode(\App\Models\Invoice::class, (int) $row->source_id))"
                                        :label="__('billing.feed.action.dun')" />
                        @elseif ($isOverdue && $row->source_type === 'voucher' && $canDun)
                            <x-action-form :action="route('lexoffice.vouchers.dunning', Sqid::encode(\App\Models\LexofficeVoucher::class, (int) $row->source_id))"
                                           :confirm="__('billing.feed.action.dun_confirm')"
                                           :confirm-label="__('billing.feed.action.dun')">
                                <x-icon-btn icon="campaign" tone="warning" size="sm" type="submit"
                                            :label="__('billing.feed.action.dun')" />
                            </x-action-form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="10"
                :title="$filters['q'] !== '' ? __('Keine Treffer für „:q“.', ['q' => $filters['q']]) : __('billing.feed.empty')"
                compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$rows" standing />
</x-index-page>
@endsection
