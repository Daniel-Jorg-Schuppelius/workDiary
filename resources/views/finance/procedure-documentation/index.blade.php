{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Versionsliste der GoBD-Verfahrensdokumentation (Feature 134): Voll-Höhe-
  Tabelle Version/Status/Erstellt/Veröffentlicht/PDF-Hash; ein Entwurf je Org.
--}}
@extends('layouts.app')
@section('title', __('procedure-documentation.title'))
@section('nav-title', __('procedure-documentation.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('procedure-documentation.subtitle')">
    <x-slot:actions>
        @if ($canManage && ! $hasDraft)
            <x-action-form :action="route('finance.procedure-documentation.store')">
                <x-icon-btn type="submit" icon="add" tone="primary" size="sm" show-label>{{ __('procedure-documentation.action.create_draft') }}</x-icon-btn>
            </x-action-form>
        @endif
    </x-slot:actions>

    <x-table scroll="flex" :zebra="true">
        <x-slot:head>
            <tr>
                <th>{{ __('procedure-documentation.field.version') }}</th>
                <th>{{ __('procedure-documentation.field.status') }}</th>
                <th>{{ __('procedure-documentation.field.created') }}</th>
                <th>{{ __('procedure-documentation.field.published') }}</th>
                <th>{{ __('procedure-documentation.field.pdf_sha256') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $document)
            <tr class="hover">
                <td class="font-mono text-sm">{{ $document->displayVersion() }}</td>
                <td><x-status-badge :tone="$document->status->tone()" size="sm">{{ $document->status->label() }}</x-status-badge></td>
                <td class="text-sm">{{ $document->created_at?->fdatetime() }}{{ $document->createdBy ? ' · ' . $document->createdBy->name : '' }}</td>
                <td class="text-sm">{{ $document->published_at?->fdatetime() ?? '–' }}{{ $document->publishedBy ? ' · ' . $document->publishedBy->name : '' }}</td>
                <td class="font-mono text-xs text-muted">{{ $document->pdf_sha256 ? \Illuminate\Support\Str::limit($document->pdf_sha256, 16) : '–' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('finance.procedure-documentation.show', $document)" :label="__('procedure-documentation.action.show')" />
                        @if ($document->isPublished())
                            <x-icon-btn icon="download" tone="outline" size="xs" :href="route('finance.procedure-documentation.download', $document)" :label="__('procedure-documentation.action.download')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" icon="menu_book" :title="__('procedure-documentation.empty')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$documents" standing />
</x-index-page>
@endsection
