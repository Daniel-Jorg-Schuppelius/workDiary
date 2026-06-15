{{--
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : index.blade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Rechnungsvorlagen'))
@section('nav-title', __('Rechnungsvorlagen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('PDF-Layouts pro Mandant verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    :href="route('invoice-templates.create')"
                    show-label>{{ __('Neue Vorlage') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-filter-bar :action="route('invoice-templates.index')" :reset="route('invoice-templates.index')">
        <input type="text" name="q" value="{{ $search ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
    </x-filter-bar>

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table zebra bare scroll="flex" :pinRows="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Slug') }}</x-table.th>
                    <th>{{ __('Akzent') }}</th>
                    <x-table.th sort type="number">{{ __('Standard') }}</x-table.th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($templates as $template)
                <tr>
                    <td>{{ $template->name }}</td>
                    <td><code>{{ $template->slug }}</code></td>
                    <td>
                        @if ($template->accent_color)
                            <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ str_starts_with($template->accent_color, '#') ? $template->accent_color : '#' . $template->accent_color }};vertical-align:middle;"></span>
                            {{ $template->accent_color }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-sort-value="{{ $template->is_default ? 1 : 0 }}">
                        @if ($template->is_default)
                            <x-icon name="check_circle" class="text-success" />
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('invoice-templates.edit', $template) }}" class="btn btn-sm btn-secondary">
                            <x-icon name="edit" />
                        </a>
                        <form method="POST" action="{{ route('invoice-templates.destroy', $template) }}" class="inline" data-confirm-dialog data-confirm-message="{{ __('Vorlage wirklich löschen?') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">
                                <x-icon name="delete" />
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="5" :title="__('Noch keine Vorlagen.')" />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
