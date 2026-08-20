{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Reklamationen'))
@section('nav-title', __('Reklamationen'))

@section('content')
<x-index-page :subtitle="__('Reklamationen, Gewährleistungsfälle, Kulanz und Rückläufer als nachvollziehbare Fallakten.')">
    <x-slot:actions>
        @can('create', \App\Models\Claims\ClaimCase::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('claims.create')"
                        show-label>{{ __('Neue Reklamation') }}</x-icon-btn>
        @endcan
        <x-icon-btn icon="query_stats" size="sm" :href="route('claims.reports.index')" show-label>{{ __('Qualitätsbericht') }}</x-icon-btn>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('Offene Fälle')" :value="$openCount" />
        <x-kpi-tile :label="__('Überfällig')" :value="$overdueCount" />
    </div>

    <x-filter-bar :action="route('claims.index')" :reset="route('claims.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Claims\ClaimStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <label class="label cursor-pointer gap-2 shrink-0">
            <input type="checkbox" name="overdue" value="1" class="checkbox checkbox-sm" @checked(request()->filled('overdue'))>
            <span class="label-text text-sm">{{ __('nur überfällige') }}</span>
        </label>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-table scroll="flex" :zebra="true" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('Nummer') }}</th>
                <th>{{ __('Titel') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Priorität') }}</th>
                <th>{{ __('Frist') }}</th>
                <th>{{ __('Verantwortlich') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($cases as $case)
            <tr>
                <td><a href="{{ route('claims.show', $case) }}" class="link font-mono">{{ $case->number }}</a></td>
                <td>{{ $case->title }}</td>
                <td>{{ $case->customer->name ?? '—' }}</td>
                <td><x-status-badge size="md" outline>{{ $case->status->label() }}</x-status-badge></td>
                <td>{{ __("values.{$case->priority}") }}</td>
                <td>
                    @if ($case->due_at !== null && $case->status->isOpen() && $case->due_at->isPast())
                        <span class="text-error font-medium">{{ $case->due_at->fdatetime() }}</span>
                    @else
                        {{ optional($case->due_at)->fdatetime() ?? '—' }}
                    @endif
                </td>
                <td>{{ $case->responsible->name ?? '—' }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" :href="route('claims.show', $case)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">assignment_return</span>' :colspan="8" :title="__('Keine Reklamationen — neue Fälle entstehen über den Dialog oder das Kundenportal.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
