@extends('layouts.app')

@section('title', __('problemreport.title.inbox'))
@section('nav-title', __('problemreport.title.inbox'))

@section('content')
<x-index-page :subtitle="__('problemreport.title.inbox_subtitle')">
    <x-slot:actions>
        <form method="GET" action="{{ route('admin.problem-reports.index') }}" class="flex items-center gap-2">
            <select name="status" class="select select-bordered select-sm" onchange="this.form.submit()">
                <option value="">{{ __('problemreport.filter.all_statuses') }}</option>
                @foreach (\App\Enums\Support\ProblemReportStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>

    @if ($reports->isEmpty())
        <x-empty-state framed icon="flag" :title="__('problemreport.empty.inbox_title')" :message="__('problemreport.empty.inbox_message')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('problemreport.field.reference') }}</th>
                    <th>{{ __('problemreport.field.summary') }}</th>
                    <th>{{ __('problemreport.field.reporter') }}</th>
                    <th>{{ __('problemreport.field.severity') }}</th>
                    <th>{{ __('problemreport.field.status') }}</th>
                    <th>{{ __('problemreport.field.created_at') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($reports as $report)
                <tr>
                    <td class="font-mono text-sm">{{ $report->reference_no }}</td>
                    <td>{{ $report->summary }}</td>
                    <td class="text-sm">{{ $report->reporter?->name ?? '—' }}</td>
                    <td><x-status-badge size="xs" :tone="$report->severity->tone()">{{ $report->severity->label() }}</x-status-badge></td>
                    <td><x-status-badge size="xs" :tone="$report->status->tone()">{{ $report->status->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ $report->created_at?->format('d.m.Y H:i') }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="open_in_new" :href="route('admin.problem-reports.show', $report)" :label="__('problemreport.action.open')" />
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-pagination :paginator="$reports" standing />
</x-index-page>
@endsection
