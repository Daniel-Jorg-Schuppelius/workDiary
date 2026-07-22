{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : economics-drilldown.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Beleg-Drilldown der Nachkalkulation (Feature 014, Rang 59c + MVP-332):
  Quellposten einer Report-Zelle — Nacharbeit/Kulanz (Zeiteinträge mit Grund)
  sowie Belegtiefe Zeit/Material/Spesen. Zugriff nur über signierten Link plus
  Report-Recht. Die Fußzeilen-Summe entspricht dem Zellenwert; Abweichungen
  meldet der Konsistenz-Hinweis. Listen org-gescopt, Belegtiefe paginiert
  (stehendes Footer-Panel, `page` von der Signatur ausgenommen).
--}}

@extends('layouts.app')
@section('title', __('Nachkalkulation — Belege'))
@section('nav-title', __('Nachkalkulation — Belege'))

@section('content')
@php
    $eur = fn($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' €';
    $titles = [
        'rework' => __('Nacharbeit — Belege'),
        'goodwill' => __('Kulanz — Belege'),
        'time' => __('Belegtiefe: Zeiteinträge'),
        'material' => __('Belegtiefe: Material'),
        'expense' => __('Belegtiefe: Spesen/Belege'),
        'travel' => __('Belegtiefe: Fahrten'),
    ];
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $titles[$kind] ?? __('Nachkalkulation — Belege') }}</x-slot:title>
            <x-slot:subtitle>{{ $project->name }} · {{ $from }} – {{ $to }}</x-slot:subtitle>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('reports.economics')" show-label>{{ __('Zum Report') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @unless ($consistent)
        <div role="alert" class="alert alert-warning mb-4 text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">warning</span>
            <span>{{ __('Konsistenz-Hinweis: Der Report meldet :expected, der Drilldown summiert :actual (Datenstand kann sich geändert haben).', ['expected' => $eur($expected), 'actual' => $eur($totalCost ?? 0)]) }}</span>
        </div>
    @endunless

    @if ($kind === 'rework' || $kind === 'goodwill')
        <x-card>
            @if ($entries->isEmpty())
                <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Mitarbeiter:in') }}</th>
                            <th>{{ __('Grund') }}</th>
                            <th>{{ __('Beschreibung') }}</th>
                            <th class="text-right">{{ __('Minuten') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="tabular-nums">{{ $entry->date?->fdate() }}</td>
                            <td>{{ $entry->user->name ?? '—' }}</td>
                            <td>{{ ($kind === 'rework' ? $entry->reworkReason?->label : $entry->goodwillReason?->label) ?? '—' }}</td>
                            <td class="max-w-md truncate text-sm">{{ $entry->description ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $entry->minutes }}</td>
                        </tr>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="4">{{ __('Summe') }}</td>
                            <td class="text-right tabular-nums">{{ $totalMinutes }}</td>
                        </tr>
                    </tfoot>
                </x-table>
            @endif
        </x-card>
    @elseif ($kind === 'time')
        <x-card>
            @if ($rows->total() === 0)
                <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Mitarbeiter:in') }}</th>
                            <th>{{ __('Beschreibung') }}</th>
                            <th>{{ __('Abrechenbar') }}</th>
                            <th class="text-right">{{ __('Minuten') }}</th>
                            <th class="text-right">{{ __('Erlös') }}</th>
                            <th class="text-right">{{ __('Kosten') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $entry)
                        <tr>
                            <td class="tabular-nums">{{ $entry->date?->fdate() }}</td>
                            <td>{{ $entry->user->name ?? '—' }}</td>
                            <td class="max-w-md truncate text-sm">{{ $entry->description ?? '—' }}</td>
                            <td>{{ $entry->billable ? __('Ja') : __('Nein') }}</td>
                            <td class="text-right tabular-nums">{{ $entry->minutes }}</td>
                            <td class="text-right tabular-nums">{{ $entry->billable ? $eur($entry->rate) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $eur($entry->internal_rate) }}</td>
                        </tr>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="4">{{ __('Summe') }}</td>
                            <td class="text-right tabular-nums">{{ $totalMinutes }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totalRevenue) }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totalCost) }}</td>
                        </tr>
                    </tfoot>
                </x-table>
            @endif
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @elseif ($kind === 'material')
        <x-card>
            @if ($rows->total() === 0)
                <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Material') }}</th>
                            <th class="text-right">{{ __('Menge') }}</th>
                            <th>{{ __('Einheit') }}</th>
                            <th>{{ __('Abgerechnet') }}</th>
                            <th class="text-right">{{ __('Netto') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $usage)
                        <tr>
                            <td class="tabular-nums">{{ $usage->timesheet?->work_date?->fdate() ?? '—' }}</td>
                            <td class="max-w-md truncate text-sm">{{ $usage->material->name ?? $usage->description }}</td>
                            <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $usage->quantity, 3, withThousandsSeparator: true), '0'), ',') }}</td>
                            <td>{{ $usage->unit }}</td>
                            <td>{{ $usage->billed ? __('Ja') : __('Nein') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($usage->line_total_net) }}</td>
                        </tr>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="5">{{ __('Summe') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totalCost) }}</td>
                        </tr>
                    </tfoot>
                </x-table>
                <p class="mt-2 text-xs text-base-content/60">{{ __('Davon abgerechnet (Erlös): :amount', ['amount' => $eur($totalRevenue)]) }}</p>
            @endif
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @elseif ($kind === 'travel')
        {{-- Fahrt-Dimension (Vollaudit 2026-07, M7). --}}
        <x-card>
            @if ($rows->total() === 0)
                <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Mitarbeiter:in') }}</th>
                            <th>{{ __('Zweck') }}</th>
                            <th class="text-right">{{ __('Kilometer') }}</th>
                            <th class="text-right">{{ __('Erstattung') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $log)
                        <tr>
                            <td class="tabular-nums">{{ $log->date?->fdate() }}</td>
                            <td>{{ $log->user->name ?? '—' }}</td>
                            <td class="max-w-md truncate text-sm">{{ $log->purpose ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $log->distance_km, 2, withThousandsSeparator: true), '0'), ',') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($log->reimbursement_total) }}</td>
                        </tr>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="4">{{ __('Summe') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totalCost) }}</td>
                        </tr>
                    </tfoot>
                </x-table>
            @endif
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @else
        <x-card>
            @if ($rows->total() === 0)
                <x-empty-state icon="receipt_long" :title="__('Keine Belege im Zeitraum.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Datum') }}</th>
                            <th>{{ __('Beleg') }}</th>
                            <th>{{ __('Kategorie') }}</th>
                            <th>{{ __('Abrechenbar') }}</th>
                            <th class="text-right">{{ __('Netto') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rows as $expense)
                        <tr>
                            <td class="tabular-nums">{{ $expense->date?->fdate() }}</td>
                            <td class="max-w-md truncate text-sm">{{ $expense->description }}@if(filled($expense->vendor)) <span class="text-base-content/60">({{ $expense->vendor }})</span>@endif</td>
                            <td>{{ $expense->category->label ?? '—' }}</td>
                            <td>{{ $expense->billable ? __('Ja') : __('Nein') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($expense->amount_net) }}</td>
                            <td class="text-right">
                                <a class="link link-hover text-sm" href="{{ route('expenses.edit', $expense) }}">{{ __('Beleg öffnen') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="4">{{ __('Summe') }}</td>
                            <td class="text-right tabular-nums">{{ $eur($totalCost) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </x-table>
                <p class="mt-2 text-xs text-base-content/60">{{ __('Davon abrechenbar (Erlös): :amount', ['amount' => $eur($totalRevenue)]) }}</p>
            @endif
        </x-card>
        <x-pagination :paginator="$rows" standing />
    @endif
</x-page-shell>
@endsection
