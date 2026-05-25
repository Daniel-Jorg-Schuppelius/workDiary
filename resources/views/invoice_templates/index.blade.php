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

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('PDF-Layouts pro Mandant verwalten.')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            :href="route('invoice-templates.create')"
                            show-label>{{ __('Neue Vorlage') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table zebra>
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Slug') }}</th>
                    <th>{{ __('Akzent') }}</th>
                    <th>{{ __('Standard') }}</th>
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
                    <td>
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
                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Noch keine Vorlagen.') }}</td></tr>
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
