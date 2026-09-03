{{--
  Created on   : Thu Sep 03 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Bericht eines Abgleichslaufs (Feature 151, MVP-757): Kennzahlen,
  Preisprüfung, Perioden mit Status-/Firmenfilter, Zuordnung, Zusatz-
  positionen, Ablösungen. Alles aus dem am Lauf gespeicherten JSON — die
  Seite rechnet nichts nach.
--}}

@extends('layouts.app')

@section('title', __('reselling.title.show'))
@section('nav-title', __('reselling.title.menu'))

@php
    $summary = $report['summary'] ?? [];
    $counts = $summary['counts'] ?? [];
    $isDone = $run->status === \App\Enums\Reselling\ReconciliationRunStatus::Done;
    $kindLabel = static fn(string $kind): string => match ($kind) {
        \App\Models\Reselling\ReconciliationRun::KIND_TELEKOM, \App\Models\Reselling\ReconciliationRun::KIND_QUALITYHOSTING => __('reselling.source.' . $kind),
        \App\Models\Reselling\ReconciliationRun::KIND_PRICELIST => __('reselling.section.price'),
        default => __('reselling.dialog.map'),
    };
@endphp

@section('content')
    <x-index-page :subtitle="$run->created_at?->format('d.m.Y H:i') . ' · ' . __('reselling.field.reference') . ' ' . $run->reference_date->format('d.m.Y')">
        <x-slot:actions>
            @if ($isDone)
                <x-icon-btn icon="download" tone="ghost" size="sm"
                            :href="route('finance.reselling.download', $run->sqid)"
                            show-label>{{ __('reselling.action.download') }}</x-icon-btn>
            @else
                <x-icon-btn icon="refresh" tone="ghost" size="sm"
                            :href="route('finance.reselling.show', $run->sqid)"
                            show-label>{{ __('reselling.action.refresh') }}</x-icon-btn>
            @endif
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('finance.reselling.index')"
                        show-label>{{ __('reselling.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <x-card class="mb-4">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <x-status-badge :tone="$run->status->tone()" :label="$run->status->label()" />
                <span class="text-muted">{{ __('reselling.field.window') }}: −{{ $run->window_before }} / +{{ $run->window_after }}</span>
                @foreach ($run->files ?? [] as $file)
                    <span class="badge badge-ghost badge-sm" title="{{ $file['name'] ?? '' }}">{{ $kindLabel((string) ($file['kind'] ?? '')) }}: {{ $file['name'] ?? '' }}</span>
                @endforeach
                @if (($report['price_list']['valid_from'] ?? null) !== null)
                    <span class="text-muted">{{ __('reselling.field.valid_from') }} {{ \Carbon\CarbonImmutable::parse($report['price_list']['valid_from'])->format('d.m.Y') }}</span>
                @endif
            </div>
            @if ($run->status === \App\Enums\Reselling\ReconciliationRunStatus::Failed)
                <div class="alert alert-error mt-3 text-sm">
                    <span>{{ __('reselling.hint.run_failed') }} {{ $run->error }}</span>
                </div>
            @elseif (! $isDone)
                <div class="alert alert-info mt-3 text-sm">
                    <span>{{ __('reselling.hint.run_pending') }}</span>
                </div>
            @endif
        </x-card>

        @if ($isDone)
            <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 mb-4">
                <x-kpi-tile :label="__('reselling.field.periods')" :value="$summary['periods'] ?? 0" />
                <x-kpi-tile :label="__('reselling.field.problems')" :value="$summary['problems'] ?? 0" :tone="($summary['problems'] ?? 0) > 0 ? 'warning' : 'success'" />
                <x-kpi-tile :label="\App\Enums\Reselling\ReconciliationStatus::Missing->label()" :value="$counts['missing'] ?? 0" :tone="($counts['missing'] ?? 0) > 0 ? 'error' : 'neutral'" />
                <x-kpi-tile :label="\App\Enums\Reselling\ReconciliationStatus::Underpriced->label()" :value="$counts['underpriced'] ?? 0" :tone="($counts['underpriced'] ?? 0) > 0 ? 'warning' : 'neutral'" />
                <x-kpi-tile :label="__('reselling.field.unmapped')" :value="$summary['unmapped_companies'] ?? 0" :tone="($summary['unmapped_companies'] ?? 0) > 0 ? 'warning' : 'neutral'" />
                <x-kpi-tile :label="__('reselling.field.open_fee')" :value="$summary['open_fee']['formatted'] ?? '—'" format="raw" />
            </div>

            @if (($report['errors'] ?? []) !== [])
                <x-card :title="__('reselling.section.errors')" class="mb-4">
                    <ul class="list-disc pl-5 text-sm text-error">
                        @foreach ($report['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('reselling.section.price')" class="mb-4">
                <p class="text-xs text-muted mb-2">{{ __('reselling.hint.price') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('reselling.field.product') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.term') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.running') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.contract_price') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.list_price') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.uvp') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.sales') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.sales_range') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.margin') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.note') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($report['price_check'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            <td>{{ $row['term_months'] }} {{ __('reselling.months') }} / {{ $row['interval_label'] }}</td>
                            <td class="text-right">{{ $row['running_quantity'] }}</td>
                            <td class="text-right">
                                @if ($row['contract_min'] !== null)
                                    {{ $row['contract_min']['formatted'] }}@if ($row['contract_max'] !== null && $row['contract_max']['amount'] !== $row['contract_min']['amount']) – {{ $row['contract_max']['formatted'] }}@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">{{ $row['list_price']['formatted'] ?? '—' }}</td>
                            <td class="text-right">{{ $row['uvp']['formatted'] ?? '—' }}</td>
                            <td class="text-right">
                                @if ($row['sales_median'] !== null)
                                    {{ $row['sales_median']['formatted'] }} <span class="text-muted">({{ $row['sales_samples'] }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($row['sales_min'] !== null)
                                    {{ $row['sales_min']['formatted'] }} – {{ $row['sales_max']['formatted'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right {{ $row['margin_percent'] !== null && $row['margin_percent'] < 0 ? 'text-error font-medium' : '' }}">
                                {{ $row['margin_percent'] === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['margin_percent'], 1) . ' %' }}
                            </td>
                            <td>
                                @foreach ($row['flags'] as $flag)
                                    <x-status-badge size="xs" :tone="in_array($flag, ['below_list', 'below_uvp'], true) ? 'error' : ($flag === 'contract_above_list' ? 'warning' : 'ghost')" :label="__('reselling.price_flag.' . $flag)" />
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="10" :title="__('reselling.empty.price')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('reselling.section.findings')" class="mb-4">
                <x-filter-bar :action="route('finance.reselling.show', $run->sqid)" :reset="route('finance.reselling.show', $run->sqid)">
                    <x-filter-field :label="__('reselling.filter.status')" for="reselling-status" inline>
                        <select id="reselling-status" name="status" class="select select-bordered select-sm">
                            <option value="problems" @selected($statusFilter === 'problems')>{{ __('reselling.filter.problems') }}</option>
                            <option value="all" @selected($statusFilter === 'all')>{{ __('reselling.filter.all') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})</option>
                            @endforeach
                        </select>
                    </x-filter-field>
                    <x-filter-field :label="__('reselling.filter.company')" for="reselling-company" inline>
                        <select id="reselling-company" name="company" class="select select-bordered select-sm">
                            <option value="">{{ __('reselling.filter.all_companies') }}</option>
                            @foreach ($companies as $key => $name)
                                <option value="{{ $key }}" @selected($companyFilter === (string) $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-filter-field>
                </x-filter-bar>

                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('reselling.field.company') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.source') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.edition') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.period') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.quantity') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.purchase') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.status') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.vouchers') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.unit_net') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.note') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($findings as $finding)
                        @php
                            $status = \App\Enums\Reselling\ReconciliationStatus::tryFrom((string) $finding['status']);
                            $tone = match ($status) {
                                \App\Enums\Reselling\ReconciliationStatus::Covered => 'success',
                                \App\Enums\Reselling\ReconciliationStatus::Missing => 'error',
                                \App\Enums\Reselling\ReconciliationStatus::Unmapped => 'neutral',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td>{{ $finding['company'] }}</td>
                            <td>{{ $finding['source_label'] }}</td>
                            <td>{{ $finding['edition'] }}</td>
                            <td class="whitespace-nowrap">{{ $finding['label'] }}</td>
                            <td class="text-right">{{ $finding['quantity'] }}</td>
                            <td class="text-right whitespace-nowrap">{{ $finding['fee']['formatted'] }}</td>
                            <td><x-status-badge size="xs" :tone="$tone" :label="$finding['status_label']" /></td>
                            <td>{{ implode(', ', $finding['vouchers']) }}</td>
                            <td class="text-right whitespace-nowrap">{{ $finding['lowest_unit_net']['formatted'] ?? '' }}</td>
                            <td class="text-xs">
                                {{ $finding['note'] }}
                                @if (($finding['succession'] ?? '') !== '')
                                    <div class="text-muted">{{ $finding['succession'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="10" :title="__('reselling.empty.findings')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('reselling.section.mappings')" class="mb-4">
                @if (($summary['unmapped_companies'] ?? 0) > 0)
                    <p class="text-xs text-muted mb-2">{{ __('reselling.hint.unmapped') }}</p>
                @endif
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('reselling.field.company') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.customer') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.contact') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.mapping') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.periods') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.problems') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.candidates') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($report['mappings'] ?? [] as $mapping)
                        <tr>
                            <td>{{ $mapping['company'] }}</td>
                            <td>{{ $mapping['customer'] ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ implode(', ', $mapping['contact_ids']) ?: '—' }}</td>
                            <td>
                                @if ($mapping['resolved'])
                                    {{ $mapping['source_label'] }}
                                @else
                                    <x-status-badge size="xs" tone="warning" :label="\App\Enums\Reselling\ReconciliationStatus::Unmapped->label()" />
                                @endif
                            </td>
                            <td class="text-right">{{ $mapping['periods'] }}</td>
                            <td class="text-right">{{ $mapping['problems'] }}</td>
                            <td class="text-xs">{{ implode(' | ', $mapping['candidates']) }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" :title="__('reselling.empty.mappings')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('reselling.section.extras')" class="mb-4">
                <p class="text-xs text-muted mb-2">{{ __('reselling.hint.extras') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('reselling.field.company') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.voucher') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.date') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.position') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.remaining') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('reselling.field.unit_net') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($report['extras'] ?? [] as $extra)
                        <tr>
                            <td>{{ $extra['company'] }}</td>
                            <td>{{ $extra['voucher'] }}</td>
                            <td>{{ \Carbon\CarbonImmutable::parse($extra['date'])->format('d.m.Y') }}</td>
                            <td>{{ $extra['name'] }}</td>
                            <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $extra['remaining'], 2) }}</td>
                            <td class="text-right">{{ $extra['unit_net']['formatted'] }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('reselling.empty.extras')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('reselling.section.successions')" class="mb-4">
                <p class="text-xs text-muted mb-2">{{ __('reselling.hint.succession') }}</p>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('reselling.field.company') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.product') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.telekom_from') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.telekom_to') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.successor') }}</x-table.th>
                            <x-table.th>{{ __('reselling.field.successor_from') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($report['successions'] ?? [] as $link)
                        <tr>
                            <td>{{ $link['company'] }}</td>
                            <td>{{ $link['product'] }}</td>
                            <td>{{ \Carbon\CarbonImmutable::parse($link['from'])->format('d.m.Y') }}</td>
                            <td>{{ $link['to'] !== null ? \Carbon\CarbonImmutable::parse($link['to'])->format('d.m.Y') : '' }}</td>
                            <td class="font-mono text-xs">{{ $link['successor'] }}</td>
                            <td>{{ \Carbon\CarbonImmutable::parse($link['successor_from'])->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="6" :title="__('reselling.empty.successions')" />
                    @endforelse
                </x-table>
            </x-card>

            @if (($report['issues'] ?? []) !== [] || ($report['price_list']['issues'] ?? []) !== [])
                <x-card :title="__('reselling.section.issues')" class="mb-4">
                    <ul class="list-disc pl-5 text-sm">
                        @foreach (array_merge($report['issues'] ?? [], $report['price_list']['issues'] ?? []) as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endif
    </x-index-page>
@endsection
