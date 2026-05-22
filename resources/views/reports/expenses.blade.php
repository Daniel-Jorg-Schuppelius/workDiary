@extends('layouts.app')
@section('title', __('Spesen-Report'))
@section('nav-title', __('Spesen-Report'))

@section('content')
<x-page-shell>

    <x-filter-bar :action="route('reports.expenses')" :reset="route('reports.expenses')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur eigene') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamte Organisation') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('Status')" for="rep-status">
            <select id="rep-status" name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="">{{ __('Alle') }}</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt->value }}" @selected($statusFilter === $opt->value)>{{ $opt->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Summe (Brutto)') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ number_format($grandTotal, 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ count($totalsPerUser) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Kategorien') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ count($totalsPerCategory) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Monate') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ count($months) }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs overflow-x-auto">
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>'
                           :title="__('Keine Spesen im gewählten Zeitraum.')" />
        @else
            <table class="table table-sm table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('Mitarbeiter') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        @foreach ($months as $m)
                            <th class="text-right whitespace-nowrap">{{ \Carbon\Carbon::createFromFormat('Y-m', $m)->locale(app()->getLocale())->translatedFormat('M Y') }}</th>
                        @endforeach
                        <th class="text-right">{{ __('Summe') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-semibold whitespace-nowrap">{{ $row['user'] }}</td>
                            <td>
                                <span class="inline-flex items-center gap-1">
                                    @if (! empty($row['icon']))
                                        <x-icon :name="$row['icon']" class="text-{{ $row['color'] ?: 'primary' }}" />
                                    @endif
                                    {{ $row['category'] }}
                                </span>
                            </td>
                            @foreach ($months as $m)
                                <td class="text-right tabular-nums">
                                    @if (isset($row['months'][$m]))
                                        {{ number_format($row['months'][$m], 2, ',', '.') }}
                                    @else
                                        <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-right tabular-nums font-semibold">{{ number_format($row['total'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="2">{{ __('Gesamt') }}</td>
                        @foreach ($months as $m)
                            <td class="text-right tabular-nums">{{ number_format($totalsPerMonth[$m] ?? 0, 2, ',', '.') }}</td>
                        @endforeach
                        <td class="text-right tabular-nums">{{ number_format($grandTotal, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    @if (! empty($totalsPerCategory))
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h3 class="font-['Space_Grotesk'] text-lg font-semibold mb-3">{{ __('Top-Kategorien') }}</h3>
            <div class="grid gap-2 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                @php arsort($totalsPerCategory); @endphp
                @foreach ($totalsPerCategory as $cat => $sum)
                    <div class="flex items-center justify-between rounded-box bg-base-200/50 px-3 py-2">
                        <span class="truncate">{{ $cat }}</span>
                        <span class="tabular-nums font-semibold">{{ number_format($sum, 2, ',', '.') }} €</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</x-page-shell>
@endsection
