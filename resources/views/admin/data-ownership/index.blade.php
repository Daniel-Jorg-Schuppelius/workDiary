{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Datenführerschaft-Matrix (Restpunkt 69): Bereich × führendes System. --}}

@extends('layouts.app')

@section('title', __('Datenführerschaft'))
@section('nav-title', __('Datenführerschaft'))

@section('content')
<x-page-shell>
    <x-page-toolbar>
        <x-slot:title>{{ __('Datenführerschaft') }}</x-slot:title>
        <x-slot:subtitle>{{ __('Je Datenbereich führt genau ein System. Bei Plugin-Führung landen Schreibversuche anderer Plugins als Inbox-Konflikt statt als Änderung.') }}</x-slot:subtitle>
    </x-page-toolbar>

    <x-card>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Datenbereich') }}</th>
                    <th>{{ __('Führendes System') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($domains as $domain)
                <tr>
                    <td class="font-medium">{{ $domain->label() }}</td>
                    <td colspan="2">
                        <form method="POST" action="{{ route('admin.data-ownership.update') }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="domain" value="{{ $domain->value }}">
                            <select name="owner" class="select select-sm select-bordered max-w-64">
                                <option value="native" @selected(($matrix[$domain->value] ?? 'native') === 'native')>{{ __('WorkDiary (nativ)') }}</option>
                                @foreach ($plugins as $plugin)
                                    <option value="{{ $plugin['id'] }}" @selected(($matrix[$domain->value] ?? '') === $plugin['id'])>{{ $plugin['name'] }}</option>
                                @endforeach
                            </select>
                            <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" :label="__('Speichern')" />
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</x-page-shell>
@endsection
