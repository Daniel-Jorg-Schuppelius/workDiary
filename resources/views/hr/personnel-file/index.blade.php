{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Digitale Personalakte (Feature 141): Akte eines Mitglieds (hrFile-Kreis)
  bzw. Eigenauskunft (selfView, read-only).
  Variablen: $member (User), $documents (Collection<Document>), $selfView (bool), $canCreate (bool)
--}}
@extends('layouts.app')
@section('title', $selfView ? __('hr.personnel_file.title_mine') : __('hr.personnel_file.title'))
@section('nav-title', $selfView ? __('hr.personnel_file.title_mine') : __('hr.personnel_file.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip"
              :subtitle="$selfView ? __('hr.personnel_file.subtitle_mine') : __('hr.personnel_file.subtitle', ['name' => $member->name])">
    <x-slot:actions>
        @unless ($selfView)
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('org.members.index')"
                        show-label>{{ __('hr.personnel_file.back') }}</x-icon-btn>
        @endunless
        @if ($canCreate)
            <x-icon-btn icon="upload_file" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('org.members.personnel-file.create', $member)"
                        show-label>{{ __('hr.personnel_file.action.upload') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-table scroll="flex" :pinRows="true" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort default="asc">{{ __('hr.personnel_file.field.title') }}</x-table.th>
                <x-table.th sort>{{ __('hr.personnel_file.field.category') }}</x-table.th>
                <x-table.th sort type="date">{{ __('hr.personnel_file.field.valid_until') }}</x-table.th>
                <x-table.th sort type="date">{{ __('hr.personnel_file.field.retention_until') }}</x-table.th>
                <th>{{ __('hr.personnel_file.field.version') }}</th>
                <x-table.th sort type="date">{{ __('hr.personnel_file.field.updated_at') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $document)
            @php
                $category = $document->hr_category;
                $effective = $document->effectiveStatus();
            @endphp
            <tr class="hover" id="document-{{ $document->id }}">
                <td>
                    <span class="flex items-center gap-2 font-medium">
                        <x-icon :name="$category?->icon() ?? 'draft'" class="text-muted" />
                        <a class="link link-hover" href="{{ route('documents.show', $document) }}">{{ $document->title }}</a>
                        @if ($effective !== \App\Enums\Document\DocumentStatus::Active)
                            <x-status-badge :tone="$effective->tone()" size="sm">{{ $effective->label() }}</x-status-badge>
                        @endif
                    </span>
                    @if ($document->description)
                        <span class="block max-w-md truncate text-xs text-muted">{{ $document->description }}</span>
                    @endif
                </td>
                <td><x-status-badge tone="ghost" outline>{{ $category?->label() ?? '—' }}</x-status-badge></td>
                <td data-sort-value="{{ $document->valid_until?->toDateString() }}">{{ $document->valid_until?->fdate() ?? '—' }}</td>
                <td data-sort-value="{{ $document->retention_until?->toDateString() }}" class="text-base-content/70">
                    @if ($document->retention_until !== null)
                        {{ $document->retention_until->fdate() }}
                    @else
                        <span class="text-muted">{{ __('hr.personnel_file.retention_pending') }}</span>
                    @endif
                </td>
                <td class="font-mono text-sm">v{{ $document->currentVersion?->version_no ?? '—' }}</td>
                <td data-sort-value="{{ $document->updated_at?->toDateString() }}" class="text-sm text-base-content/70">{{ $document->updated_at?->fdate() }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        @if ($document->currentVersion !== null)
                            <x-icon-btn icon="download" tone="outline" size="xs"
                                        :href="route('documents.download', $document)"
                                        :label="__('hr.personnel_file.action.download')" />
                        @endif
                        <x-icon-btn icon="history" tone="outline" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('documents.versions', $document)"
                                    :label="__('hr.personnel_file.action.versions')" />
                        @can('update', $document)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('personnel-file.edit', $document)"
                                        :label="__('hr.personnel_file.action.edit')" />
                        @endcan
                        @can('delete', $document)
                            <x-action-form :action="route('documents.destroy', $document)" method="DELETE"
                                           :confirm="__('hr.personnel_file.confirm_delete')"
                                           :confirm-label="__('hr.personnel_file.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('hr.personnel_file.action.delete')" />
                            </x-action-form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="folder_shared"
                           :colspan="7" :title="__('hr.personnel_file.empty')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
