{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Alle ESG-Bewertungen (Feature 071) — das Dashboard zeigt nur die jüngsten. Erwartet: $assessments, $sort, $dir --}}
@extends('layouts.app')

@section('title', __('Bewertungen (versioniert)'))
@section('nav-title', __('Bewertungen (versioniert)'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" back-route="sustainability.index" :back-label="__('Nachhaltigkeit')"
              :subtitle="__('Alle ESG-Bewertungen der Organisation mit Version, Status und Ergebnis.')">
    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
             :route="route('sustainability.assessments.index')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="request()->except(['sort', 'dir', 'page'])">
        <x-slot:head>
            <tr>
                <x-table.th sort="subject">{{ __('Gegenstand') }}</x-table.th>
                <x-table.th sort="version">{{ __('Version') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <x-table.th sort="score" align="right">{{ __('Bewertung') }}</x-table.th>
                <th>{{ __('Datenqualität') }}</th>
                <x-table.th sort="assessed_at">{{ __('Bewertet am') }}</x-table.th>
                <x-table.th sort="created" default="desc">{{ __('Erstellt am') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($assessments as $assessment)
            <tr class="hover">
                <td><a class="link link-hover font-medium" href="{{ route('sustainability.assessments.show', $assessment) }}">{{ $assessment->subject_label }}</a></td>
                <td class="tabular-nums">V{{ $assessment->version }}</td>
                <td><x-status-badge size="md" outline>{{ $assessment->status->label() }}</x-status-badge></td>
                <td class="text-right">
                    @if ($assessment->rating)
                        <x-status-badge size="md" :tone="$assessment->rating === 'green' ? 'success' : ($assessment->rating === 'yellow' ? 'warning' : 'error')">{{ $assessment->total_score }}</x-status-badge>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $assessment->data_quality ? __('values.' . $assessment->data_quality) : '—' }}</td>
                <td>{{ $assessment->assessed_at?->fdate() ?? '—' }}</td>
                <td>{{ $assessment->created_at?->fdate() ?? '—' }}</td>
                <td class="text-right"><x-icon-btn icon="visibility" :href="route('sustainability.assessments.show', $assessment)" :label="__('Anzeigen')" /></td>
            </tr>
        @empty
            <x-table.empty icon="grade" :colspan="8" :title="__('Noch keine Bewertungen.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$assessments" standing />
</x-index-page>
@endsection
