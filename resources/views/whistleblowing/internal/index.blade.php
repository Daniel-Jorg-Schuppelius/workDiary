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
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Eingegangene Hinweisgeber-Meldungen einsehen und bearbeiten.')">
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
                <tr class="hover">
                    <td>
                        <a class="link" href="{{ route('whistleblowing.internal.show', $case) }}">
                            {{ $case->case_number }}
                        </a>
                    </td>
                    <td>{{ __('whistleblowing.category.' . $case->category->value) }}</td>
                    <td>{{ __('whistleblowing.status.' . $case->status->value) }}</td>
                    <td>{{ __('whistleblowing.priority.' . $case->priority->value) }}</td>
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
