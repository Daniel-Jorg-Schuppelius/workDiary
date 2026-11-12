{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : events.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Ereignisprotokoll') . ' ' . $case->case_number)
@section('nav-title', $case->case_number)
@include('partials.page-fill')

@section('content')
    {{-- Inhaltsfrei: Das Protokoll nennt Ereignis, Zeitpunkt und Akteur, nie Meldeinhalte. --}}
    <x-index-page overflow="clip" :subtitle="__('Wer hat diesen Fall wann geöffnet, geändert oder exportiert? Jeder Aufruf dieser Seite wird selbst protokolliert.')"
                  back-route="whistleblowing.internal.index" :back-label="__('Zurück zur Liste')">
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th>{{ __('Ereignis') }}</x-table.th>
                    <x-table.th>{{ __('Akteur') }}</x-table.th>
                    <x-table.th>{{ __('Details') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($events as $event)
                @php
                    $data = $event->payloadData();
                    $details = [];
                    if (isset($data['from'], $data['to'])) {
                        $details[] = __('whistleblowing.status.' . $data['from']) . ' → ' . __('whistleblowing.status.' . $data['to']);
                    }
                    foreach (['assignee_user_id', 'grantee_user_id', 'subject_user_id', 'user_id'] as $key) {
                        if (isset($data[$key])) {
                            $details[] = $users[$data[$key]] ?? __('Benutzer nicht mehr vorhanden');
                        }
                    }
                    if (isset($data['role'])) {
                        $details[] = __('whistleblowing.role.' . $data['role']);
                    }
                @endphp
                <tr class="hover">
                    <td class="whitespace-nowrap tabular-nums">{{ $event->occurredAt()?->fdatetime() }}</td>
                    <td>{{ $event->label() }}</td>
                    <td>{{ $event->actor?->name ?? ($event->actor_type === 'system' ? __('System') : '—') }}</td>
                    <td>{{ implode(' · ', $details) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Noch keine Ereignisse.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$events" standing />
    </x-index-page>
@endsection
