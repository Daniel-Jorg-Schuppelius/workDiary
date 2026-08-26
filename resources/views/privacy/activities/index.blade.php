{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verarbeitungstätigkeiten'))
@section('nav-title', __('Verzeichnis von Verarbeitungstätigkeiten'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Verarbeitungstätigkeiten dokumentieren, prüfen und freigeben.')">
        <x-slot:actions>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export')"
                        show-label>{{ __('JSON') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export', ['format' => 'csv'])"
                        show-label>{{ __('CSV') }}</x-icon-btn>
            <x-icon-btn icon="print" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export', ['format' => 'print'])"
                        target="_blank"
                        show-label>{{ __('Druck') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('dataprotection.activities.create')"
                        show-label>{{ __('Neue Tätigkeit') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- VVT-Vorlagenkatalog (Feature 043 MVP 1; Vollaudit 2026-07, M17). --}}
        @if ($templates !== [])
            <x-card :title="__('Aus Vorlage anlegen')">
                <form method="POST" action="{{ route('dataprotection.activities.template') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <select name="template" class="select select-sm select-bordered min-w-72" aria-label="{{ __('Vorlage') }}">
                        @foreach ($templates as $key => $template)
                            <option value="{{ $key }}">{{ $template['name'] }} ({{ $template['area'] }})</option>
                        @endforeach
                    </select>
                    <x-icon-btn icon="library_add" tone="outline" size="sm" type="submit" show-label>{{ __('Als Entwurf anlegen') }}</x-icon-btn>
                    <span class="text-xs text-muted">{{ __('Inhalte sind Startpunkte — organisationsspezifisch prüfen.') }}</span>
                </form>
            </x-card>
        @endif

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Tätigkeit') }}</x-table.th>
                    <x-table.th>{{ __('Rolle') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Review fällig') }}</x-table.th>
                    <x-table.th>{{ __('DSFA') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($activities as $a)
                <tr class="hover">
                    <td><a class="link" href="{{ route('dataprotection.activities.show', $a) }}">{{ $a->name }}</a></td>
                    <td>{{ $a->controller_role->label() }}</td>
                    <td><x-status-badge tone="ghost" size="sm">{{ $a->status->label() }}</x-status-badge></td>
                    <td class="{{ $a->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $a->review_due_at?->format('d.m.Y') ?? '—' }}</td>
                    <td>{{ $a->dsfa_required ? __('ja') : '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Noch keine Verarbeitungstätigkeiten.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$activities" standing />
    </x-index-page>
@endsection
