{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Digitale Personalakte (Feature 141): Akte eines Mitglieds (hrFile-Kreis)
  bzw. Eigenauskunft (selfView, read-only).
  Variablen: $member (User), $documents (Paginator<Document>), $sort, $dir, $acknowledgements (array<int, PersonnelFileAcknowledgement>),
  $submissions (Collection<PersonnelFileSubmission>), $selfView (bool), $canCreate (bool)
--}}
@extends('layouts.app')
@section('title', $selfView ? __('hr.personnel_file.title_mine') : __('hr.personnel_file.title'))
@section('nav-title', $selfView ? __('hr.personnel_file.title_mine') : __('hr.personnel_file.title'))
@include('partials.page-fill')
@section('content')
<x-index-page overflow="clip"
              :subtitle="$selfView ? __('hr.personnel_file.subtitle_mine') : __('hr.personnel_file.subtitle', ['name' => $member->name])"
              :back-route="$selfView ? null : 'org.members.index'" :back-label="__('hr.personnel_file.back')">
    <x-slot:actions>
        @if ($selfView)
            <x-icon-btn icon="outbox" tone="outline" size="sm"
                        data-entry-modal-trigger
                        :href="route('account.personnel-file.submit-form')"
                        show-label>{{ __('hr.personnel_file.action.submit') }}</x-icon-btn>
        @endif
        @if ($canCreate)
            <x-icon-btn icon="upload_file" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('org.members.personnel-file.create', $member)"
                        show-label>{{ __('hr.personnel_file.action.upload') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($submissions->isNotEmpty())
        <div class="mb-3 space-y-2">
            @foreach ($submissions as $submission)
                <x-card padding="px-3 py-2" class="flex flex-wrap items-center justify-between gap-2 text-sm" id="submission-{{ $submission->id }}">
                    <div class="min-w-0">
                        <span class="font-medium">{{ $submission->title }}</span>
                        <span class="text-muted">· {{ $submission->hr_category->label() }} · {{ $submission->created_at?->fdate() }}</span>
                        <x-status-badge :tone="$submission->status->tone()" size="sm">{{ $submission->status->label() }}</x-status-badge>
                        @if ($submission->note)
                            <span class="block text-xs text-muted">{{ $submission->note }}</span>
                        @endif
                        @if ($submission->review_note)
                            <span class="block text-xs text-error">{{ __('hr.personnel_file.submission.reason', ['reason' => $submission->review_note]) }}</span>
                        @endif
                    </div>
                    @unless ($selfView)
                        <div class="flex gap-1">
                            <x-icon-btn icon="download" tone="outline" size="xs" :href="route('personnel-file.submissions.download', $submission)" :label="__('hr.personnel_file.action.download')" />
                            <x-icon-btn icon="check" tone="primary" size="xs" data-entry-modal-trigger :href="route('personnel-file.submissions.accept-form', $submission)" show-label>{{ __('hr.personnel_file.action.accept') }}</x-icon-btn>
                            <x-icon-btn icon="block" tone="error" size="xs" data-entry-modal-trigger :href="route('personnel-file.submissions.reject-form', $submission)" show-label>{{ __('hr.personnel_file.action.reject') }}</x-icon-btn>
                        </div>
                    @endunless
                </x-card>
            @endforeach
        </div>
    @endif

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="$selfView ? route('account.personnel-file') : route('org.members.personnel-file.index', $member)"
             :current-sort="$sort"
             :current-dir="$dir">
        <x-slot:head>
            <tr>
                <x-table.th sort="title" default>{{ __('hr.personnel_file.field.title') }}</x-table.th>
                <x-table.th sort="category">{{ __('hr.personnel_file.field.category') }}</x-table.th>
                <x-table.th sort="valid_until">{{ __('hr.personnel_file.field.valid_until') }}</x-table.th>
                <x-table.th sort="retention_until">{{ __('hr.personnel_file.field.retention_until') }}</x-table.th>
                <th>{{ __('hr.personnel_file.field.version') }}</th>
                <x-table.th sort="updated_at">{{ __('hr.personnel_file.field.updated_at') }}</x-table.th>
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
                        @if ($document->is_ack_required)
                            @if (isset($acknowledgements[$document->id]))
                                <x-status-badge tone="success" size="sm">{{ __('hr.personnel_file.ack.done', ['date' => $acknowledgements[$document->id]->acknowledged_at->fdate()]) }}</x-status-badge>
                            @else
                                <x-status-badge tone="warning" size="sm">{{ __('hr.personnel_file.ack.open') }}</x-status-badge>
                            @endif
                        @endif
                    </span>
                    @if ($document->description)
                        <span class="block max-w-md truncate text-xs text-muted">{{ $document->description }}</span>
                    @endif
                </td>
                <td><x-status-badge tone="ghost" outline>{{ $category?->label() ?? '—' }}</x-status-badge></td>
                <td>{{ $document->valid_until?->fdate() ?? '—' }}</td>
                <td class="text-base-content/70">
                    @if ($document->retention_until !== null)
                        {{ $document->retention_until->fdate() }}
                    @else
                        <span class="text-muted">{{ __('hr.personnel_file.retention_pending') }}</span>
                    @endif
                </td>
                <td class="font-mono text-sm">v{{ $document->currentVersion?->version_no ?? '—' }}</td>
                <td class="text-sm text-base-content/70">{{ $document->updated_at?->fdate() }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        @if ($selfView && $document->is_ack_required && ! isset($acknowledgements[$document->id]) && $document->currentVersion !== null)
                            <x-action-form :action="route('account.personnel-file.acknowledge', $document)"
                                           :confirm="__('hr.personnel_file.ack.confirm')" confirm-icon="done_all" confirm-tone="primary"
                                           :confirm-label="__('hr.personnel_file.action.acknowledge')">
                                <x-icon-btn icon="done_all" tone="primary" size="xs" type="submit" show-label>{{ __('hr.personnel_file.action.acknowledge') }}</x-icon-btn>
                            </x-action-form>
                        @endif
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

    <x-pagination :paginator="$documents" standing />
</x-index-page>
@endsection
