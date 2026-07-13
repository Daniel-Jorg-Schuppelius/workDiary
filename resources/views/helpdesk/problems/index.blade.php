{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Problem-Management (Feature 065, MVP-156): Ursachenobjekte hinter
     Incidents — Statusfilter, Suche, Wirksamkeitsfristen im Blick. --}}

@extends('layouts.app')
@section('title', __('Probleme'))
@section('nav-title', __('Probleme'))

@section('content')
    <x-index-page :subtitle="__('Ursachen hinter Incidents — Known Errors, Workarounds und Wirksamkeitsprüfung.')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('servicedesk.problems.create')"
                            show-label>{{ __('Neues Problem') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('servicedesk.problems.index')" :reset="route('servicedesk.problems.index')">
            <input type="text" name="q" value="{{ $filters['q'] }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />

            <select name="status" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statusLabels as $val => $label)
                    <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <x-table :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Incidents') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th>{{ __('Wirksamkeitsprüfung') }}</th>
                    <th>{{ __('Sichtbarkeit') }}</th>
                    <th class="w-24 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($problems as $problem)
                    @php
                        $due = $problem->effectiveness_check_due_at;
                        $checkOverdue = $due !== null && $due->isPast() && $problem->effectiveness_checked_at === null;
                    @endphp
                    <tr class="hover">
                        <td>
                            <a class="link link-hover font-medium" href="{{ route('servicedesk.problems.show', $problem) }}">{{ $problem->title }}</a>
                        </td>
                        <td>
                            <x-status-badge size="sm" outline>{{ $statusLabels[$problem->status] ?? $problem->status }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums">{{ $problem->tickets_count }}</td>
                        <td class="text-sm text-base-content/60">{{ $problem->owner?->name ?? '—' }}</td>
                        <td class="text-sm {{ $checkOverdue ? 'text-error font-medium' : 'text-base-content/60' }}">
                            @if ($problem->effectiveness_checked_at !== null)
                                {{ __('Geprüft am') }} {{ $problem->effectiveness_checked_at->translatedFormat('d.m.Y') }}
                            @elseif ($due !== null)
                                {{ __('Fällig am') }} {{ $due->translatedFormat('d.m.Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">{{ $problem->visibility === 'customer' ? __('Kundenportal') : __('Intern') }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new" :href="route('servicedesk.problems.show', $problem)" :label="__('Details')" />
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="7" icon="troubleshoot" :title="__('Noch keine Probleme erfasst')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$problems" standing />
    </x-index-page>
@endsection
