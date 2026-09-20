{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zentrale Notizliste (Feature 154, MVP-777).
--}}

@extends('layouts.app')
@section('title', __('communication.title.notes'))
@section('nav-title', __('communication.title.notes'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\CommunicationNote> $notes */
    /** @var array{q: string, storage: string, customer: string, type: string, open_followups: bool, tag: string} $filters */
    /** @var array<int, string|null> $contextUrls */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('communication.subtitle.notes')">
        <x-slot:actions>
            @can('create', \App\Models\CommunicationNote::class)
                <x-icon-btn icon="add_comment" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('communication-notes.create', array_filter(['customer' => $filters['customer']]))" show-label>
                    {{ __('communication.action.create') }}
                </x-icon-btn>
            @endcan
        </x-slot:actions>

        @if ($openNote)
            <a href="{{ route('communication-notes.show', $openNote) }}" data-entry-modal-autoopen="note" hidden></a>
        @endif

        <x-knowledge-tabs />

        <x-filter-bar :action="route('communication-notes.index')" :reset="route('communication-notes.index')">
            <x-filter-field :label="__('communication.filter.search')" for="notes-q" class="flex-1 min-w-60">
                <input id="notes-q" type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="{{ __('communication.filter.search_placeholder') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>
            <x-filter-field :label="__('communication.field.storage')" for="notes-storage" class="min-w-40">
                <select id="notes-storage" name="storage" class="select select-sm select-bordered w-full" data-autosubmit>
                    <option value="">{{ __('communication.storage.all') }}</option>
                    <option value="internal" @selected($filters['storage'] === 'internal')>{{ __('communication.storage.internal') }}</option>
                    <option value="customer" @selected($filters['storage'] === 'customer')>{{ __('communication.storage.customer') }}</option>
                </select>
            </x-filter-field>
            <x-filter-field :label="__('communication.field.customer')" for="notes-customer" class="min-w-44">
                <select id="notes-customer" name="customer" class="select select-sm select-bordered w-full" data-autosubmit>
                    <option value="">{{ __('communication.filter.all_customers') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->sqid }}" @selected($filters['customer'] === $customer->sqid)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('communication.field.type')" for="notes-type" class="min-w-40">
                <select id="notes-type" name="type" class="select select-sm select-bordered w-full" data-autosubmit>
                    <option value="">{{ __('communication.filter.all_types') }}</option>
                    @foreach (\App\Enums\Communication\CommunicationNoteType::quickCaptureOrder() as $type)
                        <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            @if ($tags->isNotEmpty())
                <x-filter-field :label="__('communication.field.tags')" for="notes-tag" class="min-w-40">
                    <select id="notes-tag" name="tag" class="select select-sm select-bordered w-full" data-autosubmit>
                        <option value="">{{ __('communication.filter.all_tags') }}</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->sqid }}" @selected($filters['tag'] === $tag->sqid)>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif

            <x-filter-toggle name="open_followups" id="notes-open-followups"
                             :label="__('communication.filter.open_followups')"
                             :checked="$filters['open_followups']" data-autosubmit />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
                 :route="route('communication-notes.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="request()->except(['sort', 'dir', 'page'])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="occurred_at">{{ __('communication.field.occurred_at') }}</x-table.th>
                    <x-table.th sort="subject">{{ __('communication.field.subject') }}</x-table.th>
                    <x-table.th sort="type">{{ __('communication.field.type') }}</x-table.th>
                    <th>{{ __('communication.field.storage') }}</th>
                    <th>{{ __('communication.field.creator') }}</th>
                    <th>{{ __('communication.field.next_action') }}</th>
                    <th class="w-32 text-right">{{ __('communication.field.actions') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($notes as $note)
                    @php
                        $contextUrl = $contextUrls[$note->id] ?? null;
                        $kindTone = $note->isOrganizationNote() ? 'neutral' : ($note->notable_type === \App\Models\Customer::class ? 'info' : 'ghost');
                        $dueOverdue = $note->hasOpenFollowUp() && $note->next_action_due_at !== null && $note->next_action_due_at->isPast();
                    @endphp
                    <tr class="hover" id="communication-note-{{ $note->id }}">
                        <td class="whitespace-nowrap">{{ $note->occurred_at->fdatetime() }}</td>
                        <td class="max-w-md">
                            <a href="{{ route('communication-notes.show', $note) }}" data-entry-modal-trigger class="link link-hover font-semibold">{{ $note->subject }}</a>
                            @foreach ($note->tags as $tag)
                                <x-tag-badge :tag="$tag" />
                            @endforeach
                            <div class="truncate text-xs text-muted">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($note->body, 140) }}</div>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="inline-flex items-center gap-1">
                                <x-icon :name="$note->type->icon()" class="text-muted" /> {{ $note->type->label() }}
                            </span>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-status-badge :tone="$kindTone" size="sm">{{ $note->notableKindLabel() }}</x-status-badge>
                                @if ($note->confidential)
                                    <x-status-badge tone="error" size="sm">{{ __('communication.badge.confidential') }}</x-status-badge>
                                @elseif ($note->visibility === \App\Enums\Communication\CommunicationVisibility::Customer)
                                    <x-status-badge :tone="$note->visibility->tone()" size="sm">{{ $note->visibility->label() }}</x-status-badge>
                                @endif
                                @unless ($note->isOrganizationNote())
                                    @if ($contextUrl)
                                        <a href="{{ $contextUrl }}" class="link link-hover text-xs">{{ $note->notableLabel() }}</a>
                                    @else
                                        <span class="text-xs">{{ $note->notableLabel() }}</span>
                                    @endif
                                @endunless
                            </div>
                        </td>
                        <td class="whitespace-nowrap">{{ $note->creator?->name ?? '—' }}</td>
                        <td class="text-xs">
                            @if ($note->next_action)
                                <div class="max-w-48 truncate">{{ $note->next_action }}</div>
                                @if ($note->next_action_completed_at)
                                    <x-status-badge tone="success" size="sm">{{ __('communication.badge.followup_done') }}</x-status-badge>
                                @elseif ($note->next_action_due_at)
                                    <x-status-badge :tone="$dueOverdue ? 'error' : 'warning'" size="sm">{{ $note->next_action_due_at->fdate() }}</x-status-badge>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="visibility" data-entry-modal-trigger :href="route('communication-notes.show', $note)" :label="__('communication.action.show')" />
                            @can('update', $note)
                                <x-icon-btn icon="edit" data-entry-modal-trigger :href="route('communication-notes.edit', $note)" :label="__('communication.action.edit')" />
                            @endcan
                            @if ($note->hasOpenFollowUp())
                                @can('completeFollowup', $note)
                                    <x-action-form :action="route('communication-notes.followup-complete', $note)">
                                        <x-icon-btn type="submit" icon="task_alt" tone="success" :label="__('communication.action.complete_followup')" />
                                    </x-action-form>
                                @endcan
                            @endif
                            @can('delete', $note)
                                <x-action-form :action="route('communication-notes.destroy', $note)" method="DELETE"
                                      data-confirm-title="{{ __('communication.action.delete') }}"
                                      :confirm="__('communication.confirm_delete')"
                                      confirm-icon="delete"
                                      confirm-tone="error"
                                      :confirm-label="__('communication.action.delete')">
                                    <x-icon-btn type="submit" icon="delete" tone="error" :label="__('communication.action.delete')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="7" icon="sticky_note_2" :title="__('communication.empty_filtered')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$notes" standing />
    </x-index-page>
@endsection
