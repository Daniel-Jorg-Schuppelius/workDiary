{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gespeicherte Report-Ansichten (MVP-529): benannte, teilbare Einstiege.
--}}

@extends('layouts.app')
@section('title', __('Gespeicherte Auswertungen'))
@section('nav-title', __('Gespeicherte Auswertungen'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Benannte Report-Ansichten — persönlich oder mit der Organisation geteilt.')" />
    </x-slot:toolbar>

    <x-card>
        <h3 class="font-semibold mb-2">{{ __('Neue Ansicht speichern') }}</h3>
        <p class="text-sm text-muted mb-2">
            {{ __('Auswertung öffnen, Filter einstellen, dann die Adresszeilen-URL hier einfügen.') }}
        </p>
        <form method="POST" action="{{ route('report-views.store') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <div class="fieldset">
                <label for="name" class="fieldset-label">{{ __('Name') }}</label>
                <input id="name" type="text" name="name" maxlength="255" class="input input-sm input-bordered w-64" required
                       value="{{ old('name') }}">
            </div>
            <div class="fieldset grow">
                <label for="url" class="fieldset-label">{{ __('Report-URL') }}</label>
                <input id="url" type="url" name="url" maxlength="2000" class="input input-sm input-bordered w-full" required
                       placeholder="{{ url('/reports/…') }}" value="{{ old('url') }}">
            </div>
            <label class="label cursor-pointer gap-2">
                <input type="checkbox" name="is_shared" value="1" class="checkbox checkbox-sm" @checked(old('is_shared'))>
                <span class="label-text">{{ __('Mit Organisation teilen') }}</span>
            </label>
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
        </form>
    </x-card>

    <x-card>
        @if ($views->isEmpty())
            <x-empty-state icon="bookmark"
                           :title="__('Noch keine gespeicherten Ansichten')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Name') }}</x-table.th>
                        <x-table.th>{{ __('Erstellt von') }}</x-table.th>
                        <x-table.th>{{ __('Sichtbarkeit') }}</x-table.th>
                        <x-table.th></x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($views as $view)
                    <tr>
                        <td>
                            <a class="link link-hover font-medium" href="{{ $view->targetUrl() }}">{{ $view->name }}</a>
                        </td>
                        <td class="text-sm text-muted">{{ $view->creator?->name }}</td>
                        <td>
                            <x-status-badge :tone="$view->is_shared ? 'info' : 'ghost'" size="sm">
                                {{ $view->is_shared ? __('geteilt') : __('persönlich') }}
                            </x-status-badge>
                        </td>
                        <td class="text-right">
                            @if ((int) $view->created_by === $viewerId || $isAdmin)
                                <div class="flex items-center gap-1 justify-end">
                                    <form method="POST" action="{{ route('report-views.toggle-share', $view) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-ghost">
                                            {{ $view->is_shared ? __('Nicht mehr teilen') : __('Teilen') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('report-views.destroy', $view) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit"
                                                    :aria-label="__('Löschen')" />
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
