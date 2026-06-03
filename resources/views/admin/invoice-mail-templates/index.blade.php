{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Rechnungs-Mail-Templates'))
@section('nav-title', __('Mail-Templates'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('E-Mail-Vorlagen für den Rechnungsversand verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    :href="route('admin.invoice-mail-templates.create')"
                    show-label>{{ __('Neues Template') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-filter-bar :action="route('admin.invoice-mail-templates.index')" :reset="route('admin.invoice-mail-templates.index')">
        <input type="text" name="q" value="{{ $search ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Betreff') }}</x-table.th>
                <x-table.th sort type="string">{{ __('Scope') }}</x-table.th>
                <x-table.th sort type="number">{{ __('Standard') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $tpl)
            <tr>
                <td>{{ $tpl->name }}</td>
                <td class="text-sm">{{ $tpl->subject }}</td>
                <td>{{ $tpl->organization_id === null ? __('Global') : __('Organisation') }}</td>
                <td data-sort-value="{{ $tpl->is_default ? 1 : 0 }}">
                    @if ($tpl->is_default)
                        <x-status-badge tone="success" size="md">{{ __('Ja') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right">
                    <x-icon-btn icon="edit" size="xs"
                                :href="route('admin.invoice-mail-templates.edit', $tpl)"/>
                    <form method="POST" action="{{ route('admin.invoice-mail-templates.destroy', $tpl) }}" class="inline"
                          data-confirm-dialog
                          data-confirm-message="{{ __('Template wirklich löschen?') }}"
                          data-confirm-tone="error"
                          data-confirm-label="{{ __('Löschen') }}">
                        @csrf @method('DELETE')
                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit"/>
                    </form>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">mail</span>'
                           :colspan="5" :title="__('Noch keine Templates angelegt.')" compact/>
        @endforelse
    </x-table>

    <div class="mt-6 text-sm text-base-content/70">
        <strong>{{ __('Verfügbare Variablen') }}:</strong>
        <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
            @foreach ($variables as $key => $label)
                <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> – {{ $label }}</li>
            @endforeach
        </ul>
    </div>
</x-index-page>
@endsection
