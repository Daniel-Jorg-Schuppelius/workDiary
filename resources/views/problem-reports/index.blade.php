{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('problemreport.title.index'))
@section('nav-title', __('problemreport.title.index'))

@section('content')
<x-index-page :subtitle="__('problemreport.title.index_subtitle')">
    <x-slot:actions>
        <x-button data-entry-modal-trigger
                  :href="route('problem-reports.create', ['route' => 'problem-reports.index', 'url' => route('problem-reports.index')])"
                  tone="warning" size="sm" icon="flag">
            {{ __('errors.report_problem') }}
        </x-button>
    </x-slot:actions>

    @if ($reports->isEmpty())
        <x-empty-state framed icon="flag" :title="__('problemreport.empty.title')" :message="__('problemreport.empty.message')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('problemreport.field.reference') }}</th>
                    <th>{{ __('problemreport.field.summary') }}</th>
                    <th>{{ __('problemreport.field.severity') }}</th>
                    <th>{{ __('problemreport.field.status') }}</th>
                    <th>{{ __('problemreport.field.created_at') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($reports as $report)
                <tr>
                    <td class="font-mono text-sm">{{ $report->reference_no }}</td>
                    <td>{{ $report->summary }}</td>
                    <td><x-status-badge size="xs" :tone="$report->severity->tone()">{{ $report->severity->label() }}</x-status-badge></td>
                    <td><x-status-badge size="xs" :tone="$report->status->tone()">{{ $report->status->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ $report->created_at?->format('d.m.Y H:i') }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif

    <x-pagination :paginator="$reports" standing />
</x-index-page>
@endsection
