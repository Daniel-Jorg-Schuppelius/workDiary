{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : periods.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abrechnungsperioden (Feature 152, MVP-761): was berechnet ist und was
  nicht — fällige Perioden aller Abos mit Bezügen, Vorschlägen und
  Entscheidungen. Standardfilter: unbestätigte Vorschläge (Feature 185);
  Sammelaktionen über die Auswahlspalte (bestätigen, verzichten, Entwurf).
--}}
@extends('layouts.app')
@section('title', __('resale.periods.title'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.periods.title')" :subtitle="__('resale.periods.subtitle')">
        <x-slot:actions>
            @if ($canManage)
                <form method="POST" action="{{ route('finance.resale.periods.propose') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="primary" size="sm" type="submit" show-label>{{ __('resale.link.action.propose') }}</x-icon-btn>
                </form>
            @endif
            @can(\App\Enums\User\Permission::ResellingInvoice->value)
                <x-icon-btn icon="receipt_long" tone="ghost" size="sm" data-entry-modal-trigger :href="route('finance.resale.periods.draft.create')" show-label>{{ __('resale.draft.action') }}</x-icon-btn>
            @endcan
            <x-icon-btn icon="compare_arrows" tone="ghost" size="sm" :href="route('finance.resale.reconcile.index')" show-label>{{ __('resale.reconcile.title') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm" :href="route('finance.resale.report.export')" show-label>{{ __('resale.export.action') }}</x-icon-btn>
        </x-slot:actions>

        @include('finance.resale._tabs')

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
            @foreach ($statuses as $status)
                <x-kpi-tile :label="$status->label()" :value="$counts[$status->value] ?? 0" :tone="($counts[$status->value] ?? 0) > 0 ? $status->tone() : 'neutral'"
                            :href="route('finance.resale.periods.index', ['status' => $status->value])" :active="$filters['status'] === $status->value" />
            @endforeach
        </div>

        @if ($unlinked->isNotEmpty())
            {{-- Lizenzpositionen ohne Abo: fehlende Abos oder falsche Halter finden (die neuesten 100, Gesamtzahl im Titel). --}}
            <details id="unlinked" class="collapse collapse-arrow border border-base-300 bg-base-100 mb-4" {{ request()->boolean('unlinked') ? 'open' : '' }}>
                <summary class="collapse-title text-sm font-medium">{{ trans_choice('resale.unlinked.title', $unlinkedTotal, ['count' => $unlinkedTotal]) }}</summary>
                <div class="collapse-content p-0">
                    <p class="px-4 pb-2 text-xs text-muted">{{ __('resale.unlinked.hint') }}@if ($unlinkedTotal > $unlinked->count()) {{ __('resale.link_ui.unlinked_truncated', ['shown' => $unlinked->count()]) }}@endif</p>
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('resale.invoices.voucher') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.billed_to') }}</x-table.th>
                                <x-table.th>{{ __('resale.field.article') }}</x-table.th>
                                <x-table.th class="text-right">{{ __('resale.field.quantity') }}</x-table.th>
                                <x-table.th>{{ __('resale.unlinked.text') }}</x-table.th>
                                <x-table.th class="text-right"></x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($unlinked as $line)
                            <tr>
                                <td class="whitespace-nowrap"><span class="font-mono text-xs">{{ $line->voucherNumber }}</span> <span class="text-xs text-muted tabular-nums">{{ $line->voucherDate?->fdate() }}</span></td>
                                <td class="text-sm">
                                    @if ($line->recipientCustomerId !== null)
                                        <a href="{{ route('finance.resale.reconcile.show', \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $line->recipientCustomerId)) }}" class="link link-hover">{{ $line->recipientName ?? '—' }}</a>
                                    @else
                                        {{ $line->recipientName ?? '—' }}
                                    @endif
                                </td>
                                <td class="text-sm">{{ $line->label() }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap"><x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }}</td>
                                <td class="text-xs max-w-xs truncate" title="{{ $line->voucherText }}">{{ $line->voucherTextHint ?? \Illuminate\Support\Str::limit((string) $line->voucherText, 70) }}</td>
                                <td class="text-right">
                                    @if ($canManage)
                                        {{-- Abo anlegen (vorbelegt aus der Position) oder dauerhaft ausblenden (Phase 163, MVP-1432). --}}
                                        <div class="flex items-center justify-end gap-1">
                                            <x-icon-btn icon="add" size="xs" tone="primary" data-entry-modal-trigger :href="route('finance.resale.create', ['line' => $line->key])" :title="__('resale.unlinked.create')" />
                                            <form method="POST" action="{{ route('finance.resale.periods.unlinked.ignore') }}" class="inline" data-confirm-dialog data-confirm-message="{{ __('resale.unlinked.ignore_confirm') }}" data-confirm-label="{{ __('resale.unlinked.ignore') }}">
                                                @csrf
                                                <input type="hidden" name="key" value="{{ $line->key }}">
                                                <x-icon-btn icon="visibility_off" size="xs" tone="ghost" type="submit" :title="__('resale.unlinked.ignore')" />
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            </details>
        @endif
        @if ($ignored->isNotEmpty())
            <details class="collapse collapse-arrow border border-base-300 bg-base-100 mb-4">
                <summary class="collapse-title text-sm font-medium text-muted">{{ trans_choice('resale.unlinked.ignored_title', $ignored->count(), ['count' => $ignored->count()]) }}</summary>
                <div class="collapse-content p-0">
                    <x-table bare>
                        @foreach ($ignored as $row)
                            @php $line = $ignoredLines[$row->linkable_type . ':' . $row->linkable_id] ?? null; @endphp
                            <tr>
                                <td class="whitespace-nowrap"><span class="font-mono text-xs">{{ $line?->voucherNumber ?? '—' }}</span> <span class="text-xs text-muted tabular-nums">{{ $line?->voucherDate?->fdate() }}</span></td>
                                <td class="text-sm">{{ $line?->label() ?? __('resale.unlinked.error.missing') }}</td>
                                <td class="text-xs text-muted">{{ $row->note }}@if ($row->creator !== null) · {{ $row->creator->name }}@endif · {{ $row->created_at?->fdate() }}</td>
                                <td class="text-right">
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('finance.resale.periods.unlinked.restore', $row->sqid) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-btn icon="visibility" size="xs" tone="ghost" type="submit" :title="__('resale.unlinked.restore')" />
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            </details>
        @endif

        <x-filter-bar :action="route('finance.resale.periods.index')" :reset="route('finance.resale.periods.index')">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="input input-sm input-bordered w-48"
                   placeholder="{{ __('resale.filter.search') }}" aria-label="{{ __('resale.filter.search') }}">
            <select name="status" class="select select-sm select-bordered w-56" aria-label="{{ __('resale.field.status') }}">
                <option value="problems" @selected($filters['status'] === 'problems')>{{ __('resale.periods.filter_problems') }}</option>
                <option value="proposed" @selected($filters['status'] === 'proposed')>{{ __('resale.periods.filter_proposed') }}</option>
                <option value="all" @selected($filters['status'] === 'all')>{{ __('resale.periods.filter_all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($filterCustomer !== null)
                <input type="hidden" name="customer" value="{{ $filterCustomer->sqid }}">
                <x-status-badge tone="plain" outline>{{ $filterCustomer->name }}</x-status-badge>
            @endif
        </x-filter-bar>

        {{-- Sammelaktionen (MVP-1415): Wurzel ist ein div, damit die Zeilen ihre eigenen Formulare behalten;
             die Toolbar-Form bekommt die gewählten ids[] vom Skript (data-bulk-ids-form). --}}
        <div data-bulk-form class="contents">
        @if ($canManage)
            <form method="POST" action="{{ route('finance.resale.periods.bulk.confirm') }}" data-bulk-ids-form class="contents">
                @csrf
                <x-bulk-toolbar :label="__('resale.bulk.selected')">
                    <x-slot:actions>
                        <x-button type="submit" tone="success" size="sm" icon="task_alt" formaction="{{ route('finance.resale.periods.bulk.confirm') }}">{{ __('resale.bulk.confirm') }}</x-button>
                        <x-button :href="route('finance.resale.periods.bulk.waive.create')" tone="ghost" size="sm" icon="do_not_disturb_on" data-entry-modal-trigger data-bulk-dialog-link>{{ __('resale.bulk.waive') }}</x-button>
                        @can(\App\Enums\User\Permission::ResellingInvoice->value)
                            <x-button type="submit" tone="ghost" size="sm" icon="receipt_long" formaction="{{ route('finance.resale.periods.bulk.draft') }}">{{ __('resale.bulk.draft') }}</x-button>
                        @endcan
                    </x-slot:actions>
                </x-bulk-toolbar>
            </form>
        @endif
        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    @if ($canManage)
                        <th class="w-8"><input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all aria-label="{{ __('Alle auswählen') }}"></th>
                    @endif
                    <x-table.th sort type="string">{{ __('resale.field.label') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.field.holder') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('resale.field.period') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.field.quantity') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.field.expected_sale') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.link.covered') }}</x-table.th>
                    <x-table.th>{{ __('resale.link.links') }}</x-table.th>
                    <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    <x-table.th class="text-right"></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($periods as $period)
                @include('finance.resale._period_row', ['period' => $period, 'subscription' => $period->subscription, 'showSubscription' => true, 'canManage' => $canManage, 'today' => $today, 'bulk' => $canManage, 'plannedNext' => $plannedNext])
            @empty
                <x-table.empty :colspan="$canManage ? 10 : 9" icon="task_alt" :title="__('resale.periods.empty')" compact />
            @endforelse
        </x-table>
        </div>
        <x-pagination :paginator="$periods" standing />
    </x-index-page>
@endsection
