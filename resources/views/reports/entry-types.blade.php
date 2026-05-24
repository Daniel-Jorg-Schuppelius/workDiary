@extends('layouts.app')
@section('title', __('Auftragstypanalyse'))
@section('nav-title', __('Auftragstypanalyse'))

@section('content')
<x-page-shell>
    <x-filter-bar :action="route('reports.entry-types')" :reset="route('reports.entry-types')">
        <x-filter-field :label="__('Kunde')" for="rep-customer">
            <select id="rep-customer" name="customer_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected($customerId === $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Mitarbeiter')" for="rep-user">
            <select id="rep-user" name="user_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($reportUsers as $reportUser)
                    <option value="{{ $reportUser->id }}" @selected($userId === $reportUser->id)>{{ $reportUser->name }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Auftragstyp')" for="rep-entry-type">
            <select id="rep-entry-type" name="entry_type_id" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach($entryTypes as $entryType)
                    <option value="{{ $entryType->id }}" @selected($entryTypeFilter === $entryType->id)>{{ $entryType->label }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Status')" for="rep-status">
            <select id="rep-status" name="status" class="select select-sm select-bordered">
                <option value="">{{ __('Alle') }}</option>
                @foreach(\App\Http\Controllers\Reporting\EntryTypeAnalysisReportController::statusOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($statusFilter === (int) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-slot:extra>
            <x-icon-btn icon="download" tone="outline" size="sm"
                        :href="route('reports.entry-types', array_filter(['customer_id' => $customerId, 'user_id' => $userId, 'entry_type_id' => $entryTypeFilter, 'status' => $statusFilter, 'export' => 'csv']))"
                        show-label>CSV</x-icon-btn>
        </x-slot:extra>
    </x-filter-bar>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3 text-xs text-base-content/60">{{ __('Zeitraum') }}: {{ $label }}</div>

        @if(empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">analytics</span>' :title="__('Keine Auftragsdaten im gewählten Zeitraum.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Auftragstyp') }}</th>
                        <th class="text-right">{{ __('Aufträge') }}</th>
                        <th class="text-right">{{ __('Ø Plan (Min.)') }}</th>
                        <th class="text-right">{{ __('Ø Ist (Min.)') }}</th>
                        <th class="text-right">{{ __('Plan/Ist') }}</th>
                        <th class="text-right">{{ __('Überzug') }}</th>
                        <th class="text-right">{{ __('Überzug %') }}</th>
                        <th class="text-right">{{ __('Nacharbeit') }}</th>
                        <th class="text-right">{{ __('Nacharbeit %') }}</th>
                        <th class="text-right">{{ __('Escalation %') }}</th>
                        <th class="text-right">{{ __('First-Time-Right %') }}</th>
                        <th class="text-right">{{ __('Median Ist') }}</th>
                        <th class="text-right">{{ __('P90 Ist') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($rows as $row)
                    @php
                        $ratio = $row['planActualRatio'];
                        $ratioClass = $ratio === null ? 'text-base-content/50' : ($ratio <= 1.0 ? 'text-success' : ($ratio <= 1.2 ? 'text-warning' : 'text-error'));
                        $drilldownHref = route('diary.index', array_filter([
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'customer' => $customerId,
                            'entry_type' => $row['entryTypeId'] > 0 ? $row['entryTypeId'] : null,
                            'status' => $statusFilter,
                        ]));
                        $reportDrilldown = array_filter([
                            'entry_type_id' => $row['entryTypeId'] > 0 ? $row['entryTypeId'] : null,
                            'customer_id' => $customerId,
                            'user_id' => $userId,
                            'status' => $statusFilter,
                        ]);
                    @endphp
                    <tr>
                        <td class="font-medium">
                            <a href="{{ $drilldownHref }}" class="link link-hover">{{ $row['entryTypeName'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ $row['entryCount'] }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['avgPlannedMinutes'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['avgActualMinutes'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums {{ $ratioClass }}">{{ $ratio === null ? '—' : number_format($ratio, 3, ',', '.') }}</td>
                        <td class="text-right tabular-nums">{{ $row['overrunCount'] }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['overrunShare'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.entry-types.drilldown.protocols', $reportDrilldown) }}" class="link link-hover">{{ $row['reworkCount'] }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ number_format($row['reworkShare'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">
                            <a href="{{ route('reports.entry-types.drilldown.open-issues', array_merge($reportDrilldown, ['escalated' => 1])) }}" class="link link-hover">{{ number_format($row['escalationShare'], 2, ',', '.') }}</a>
                        </td>
                        <td class="text-right tabular-nums">{{ number_format($row['firstTimeRightShare'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['medianActualMinutes'], 2, ',', '.') }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row['p90ActualMinutes'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
