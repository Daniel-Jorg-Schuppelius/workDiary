{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Hinweisgeber-Meldungen'))
@section('nav-title', __('Hinweisgeber-Meldungen'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Eingegangene Hinweisgeber-Meldungen einsehen und bearbeiten.')">
        <x-filter-bar :action="route('whistleblowing.internal.index')" :reset="route('whistleblowing.internal.index')">
            <select name="status" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach (\App\Enums\Whistleblowing\CaseStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            {{-- Kategorie und Priorität filtern nur zugewiesene Fälle (MVP-802): sonst verriete die Trefferliste beides. --}}
            <select name="category" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Kategorie') }}" title="{{ __('Wirkt nur auf Fälle, die Ihnen zugewiesen sind.') }}">
                <option value="">{{ __('Alle Kategorien') }}</option>
                @foreach (\App\Enums\Whistleblowing\CaseCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ __('whistleblowing.category.' . $category->value) }}</option>
                @endforeach
            </select>
            <select name="priority" class="select select-sm select-bordered w-36 shrink-0" aria-label="{{ __('Priorität') }}" title="{{ __('Wirkt nur auf Fälle, die Ihnen zugewiesen sind.') }}">
                <option value="">{{ __('Alle Prioritäten') }}</option>
                @foreach (\App\Enums\Whistleblowing\CasePriority::cases() as $priority)
                    <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ __('whistleblowing.priority.' . $priority->value) }}</option>
                @endforeach
            </select>
            <select name="assignee" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Zugewiesene Person') }}">
                <option value="">{{ __('Alle Bearbeiter') }}</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->sqid }}" @selected(($filters['assignee'] ?? '') === $assignee->sqid)>{{ $assignee->name }}</option>
                @endforeach
            </select>
            <select name="deadline" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Friststatus') }}">
                <option value="">{{ __('Alle Fristen') }}</option>
                <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>{{ __('Frist überfällig') }}</option>
                <option value="soon" @selected(($filters['deadline'] ?? '') === 'soon')>{{ __('Frist in 14 Tagen') }}</option>
                <option value="feedback_open" @selected(($filters['deadline'] ?? '') === 'feedback_open')>{{ __('Rückmeldung offen') }}</option>
            </select>
            <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                          :from="$filters['from'] ?? null" :to="$filters['to'] ?? null"
                          :from-label="__('Eingang von')" :to-label="__('Eingang bis')" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Fallnummer') }}</x-table.th>
                    <x-table.th>{{ __('Kategorie') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Priorität') }}</x-table.th>
                    <x-table.th>{{ __('Eingang bis') }}</x-table.th>
                    <x-table.th>{{ __('Rückmeldung bis') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($cases as $case)
                @php $showMeta = in_array((int) $case->id, $metadataVisible ?? [], true); @endphp
                <tr class="hover">
                    <td>
                        <a class="link" href="{{ route('whistleblowing.internal.show', $case) }}">
                            {{ $case->case_number }}
                        </a>
                    </td>
                    {{-- Kategorie/Priorität erst nach Zuweisung (MVP-802, Entscheid P13-26). --}}
                    <td>
                        @if ($showMeta)
                            {{ __('whistleblowing.category.' . $case->category->value) }}
                        @else
                            <span class="text-muted" title="{{ __('Sichtbar nach Zuweisung') }}">—</span>
                        @endif
                    </td>
                    <td>{{ __('whistleblowing.status.' . $case->status->value) }}</td>
                    <td>
                        @if ($showMeta)
                            {{ __('whistleblowing.priority.' . $case->priority->value) }}
                        @else
                            <span class="text-muted" title="{{ __('Sichtbar nach Zuweisung') }}">—</span>
                        @endif
                    </td>
                    <td>{{ optional($case->acknowledgement_due_at)->format('d.m.Y') }}</td>
                    <td>{{ optional($case->feedback_due_at)->format('d.m.Y') }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="6" :title="__('Keine Meldungen.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$cases" standing />
    </x-index-page>
@endsection
