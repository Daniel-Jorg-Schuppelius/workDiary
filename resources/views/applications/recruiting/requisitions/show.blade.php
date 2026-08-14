{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Stelle: :title', ['title' => $requisition->title]))
@section('nav-title', $requisition->title)

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :badge="__('values.' . $requisition->status)" badge-tone="outline">
            <div class="text-sm text-base-content/70">
                {{ __("values.{$requisition->employment_type}") }} · {{ __(':count Stelle(n)', ['count' => $requisition->headcount]) }}
                @if ($requisition->department) · {{ $requisition->department }} @endif
            </div>
            <x-slot:actions>
                @can('update', $requisition)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('recruiting.requisitions.edit', $requisition)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    <form method="POST" action="{{ route('recruiting.requisitions.status', $requisition) }}" class="flex items-center gap-1">
                        @csrf
                        <select name="status" class="select select-sm select-bordered" data-autosubmit aria-label="{{ __('Status') }}">
                            @foreach (\App\Models\Applications\JobRequisition::STATUSES as $status)
                                <option value="{{ $status }}" @selected($requisition->status === $status)>{{ __("values.$status") }}</option>
                            @endforeach
                        </select>
                    </form>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Profil')">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Verantwortlich')">{{ $requisition->responsible->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Zielstart')">{{ optional($requisition->target_start_on)->fdate() ?? '—' }}</x-detail-grid.row>
                @if ($requisition->budget_note)
                    <x-detail-grid.row :label="__('Budget/Kapazität')">{{ $requisition->budget_note }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
            @if ($requisition->profile)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $requisition->profile }}</p>
            @endif
        </x-card>

        <x-card :title="__('Veröffentlichungen')">
            @can('update', $requisition)
                <form method="POST" action="{{ route('recruiting.requisitions.postings.store', $requisition) }}" class="mb-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="channel" class="select select-sm select-bordered">
                        @foreach (\App\Models\Applications\JobPosting::CHANNELS as $channel)
                            <option value="{{ $channel }}">{{ __("values.$channel") }}</option>
                        @endforeach
                    </select>
                    <input name="reference" maxlength="200" class="input input-sm input-bordered" placeholder="{{ __('Referenz/Anzeigen-Nr.') }}">
                    <input name="expires_at" type="date" class="input input-sm input-bordered" aria-label="{{ __('Läuft ab') }}">
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Veröffentlichen') }}</x-icon-btn>
                </form>
            @endcan
            @if ($requisition->postings->isEmpty())
                <x-empty-state icon="campaign" :title="__('Noch keine Veröffentlichung.')" compact />
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($requisition->postings as $posting)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge size="xs" outline>{{ __("values.{$posting->status}") }}</x-status-badge>
                            {{ __("values.{$posting->channel}") }}
                            @if ($posting->reference)<span class="text-base-content/60">{{ $posting->reference }}</span>@endif
                            @if ($posting->expires_at)<span class="text-xs text-base-content/60">{{ __('bis :date', ['date' => $posting->expires_at->fdate()]) }}</span>@endif
                            @if ($posting->status === 'published')
                                @can('update', $requisition)
                                    <x-action-form :action="route('recruiting.requisitions.postings.close', [$requisition, $posting])" class="ml-auto">
                                        <x-icon-btn icon="close" size="xs" tone="ghost" type="submit" :title="__('Schließen')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Bewerbungen zu dieser Stelle')">
        @if ($requisition->applications->isEmpty())
            <x-empty-state icon="person_search" :title="__('Noch keine Bewerbungen.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kandidat') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Eingegangen') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($requisition->applications as $application)
                    <tr>
                        <td>{{ $application->isAnonymized() ? __('(anonymisiert)') : ($application->candidate_name ?? '—') }}</td>
                        <td><x-status-badge size="md" outline>{{ __("values.{$application->status}") }}</x-status-badge></td>
                        <td>{{ optional($application->received_at)->fdate() ?? '—' }}</td>
                        <td class="text-right"><x-icon-btn icon="visibility" :href="route('recruiting.applications.show', $application)" :label="__('Anzeigen')" /></td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
