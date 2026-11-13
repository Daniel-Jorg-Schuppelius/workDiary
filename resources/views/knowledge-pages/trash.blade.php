{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : trash.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Papierkorb der Wissensseiten (Feature 191, MVP-1664): Wiederherstellen,
  endgültig löschen nur mit Recht; nach der Frist leert ihn
  `knowledge-pages:empty-trash`. Variablen: $pages (Paginator), $trashDays.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.trash.title'))
@section('nav-title', __('knowledge_pages.trash.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('knowledge_pages.trash.subtitle', ['days' => $trashDays])">
    <x-knowledge-tabs />
    @if ($pages->isEmpty())
        <x-empty-state framed icon="delete_sweep" :title="__('knowledge_pages.trash.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('knowledge_pages.field.title') }}</th>
                    <th>{{ __('knowledge_pages.field.space') }}</th>
                    <th>{{ __('knowledge_pages.trash.deleted') }}</th>
                    <th>{{ __('knowledge_pages.trash.purge_on') }}</th>
                    <th class="w-px"></th>
                </tr>
            </x-slot:head>
            @foreach ($pages as $trashed)
                <tr class="hover">
                    <td class="font-medium">{{ $trashed->title }}</td>
                    <td>{{ $trashed->space?->title }}</td>
                    <td class="text-sm">{{ $trashed->deleted_at?->fdatetime() }} · {{ $trashed->trasher?->name ?? '—' }}</td>
                    <td class="text-sm tabular-nums">{{ $trashed->deleted_at?->copy()->addDays($trashDays)->fdate() }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('restore', $trashed)
                                <form method="POST" action="{{ route('knowledge.pages.restore', $trashed->sqid) }}">
                                    @csrf
                                    <x-icon-btn icon="restore_from_trash" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.trash.restore')" />
                                </form>
                            @endcan
                            @can('forceDelete', $trashed)
                                <x-action-form :action="route('knowledge.pages.force-delete', $trashed->sqid)" method="DELETE"
                                               :confirm="__('knowledge_pages.confirm.purge')" confirm-icon="delete_forever" confirm-tone="error"
                                               :confirm-label="__('knowledge_pages.trash.purge')">
                                    <x-icon-btn icon="delete_forever" tone="error" size="xs" type="submit" :label="__('knowledge_pages.trash.purge')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$pages" standing />
    @endif
</x-index-page>
@endsection
