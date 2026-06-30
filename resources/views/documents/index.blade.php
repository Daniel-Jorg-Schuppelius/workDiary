{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('document.title.index'))
@section('nav-title', __('document.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('document.subtitle')">
        <x-slot:actions>
            @if ($canCreate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('documents.create')"
                            show-label>{{ __('document.action.create') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        @include('documents._tabs')

        <x-filter-bar :action="route('documents.index')"
                      :reset="$hasActiveFilters ? route('documents.index') : null">
            <x-filter-field :label="__('document.filter.search')" for="document-q" class="flex-1 min-w-60">
                <input id="document-q" type="search" name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="{{ __('document.filter.search_placeholder') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>

            <x-filter-field :label="__('document.field.type')" for="document-type" class="min-w-40">
                <select id="document-type" name="type" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('document.filter.all') }}</option>
                    @foreach (\App\Enums\Document\DocumentType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('document.field.status')" for="document-status" class="min-w-40">
                <select id="document-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('document.filter.all') }}</option>
                    @foreach (\App\Enums\Document\DocumentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('document.field.reference')" for="document-ref" class="min-w-40">
                <select id="document-ref" name="ref" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('document.filter.all') }}</option>
                    <option value="customer" @selected($filters['ref'] === 'customer')>{{ __('document.ref.customer') }}</option>
                    <option value="project" @selected($filters['ref'] === 'project')>{{ __('document.ref.project') }}</option>
                    <option value="diary" @selected($filters['ref'] === 'diary')>{{ __('document.ref.diary') }}</option>
                    <option value="asset" @selected($filters['ref'] === 'asset')>{{ __('document.ref.asset') }}</option>
                    <option value="none" @selected($filters['ref'] === 'none')>{{ __('document.ref.none') }}</option>
                </select>
            </x-filter-field>

            <x-filter-field :label="__('document.filter.expiring')" for="document-expiring" class="min-w-40">
                <select id="document-expiring" name="expiring" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('document.filter.all') }}</option>
                    @foreach ([30, 60, 90] as $days)
                        <option value="{{ $days }}" @selected($filters['expiring'] === (string) $days)>{{ __('document.filter.expiring_days', ['days' => $days]) }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('document.field.title') }}</th>
                    <th>{{ __('document.field.type') }}</th>
                    <th>{{ __('document.field.reference') }}</th>
                    <th>{{ __('document.field.valid_until') }}</th>
                    <th>{{ __('document.field.status') }}</th>
                    <th>{{ __('document.field.version') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($documents as $document)
                @php
                    $effective = $document->effectiveStatus();
                    $isExpired = $document->isExpired();
                    $expiresSoon = ! $isExpired
                        && $document->valid_until !== null
                        && $document->status !== \App\Enums\Document\DocumentStatus::Archived
                        && $document->valid_until->lte(now()->addDays(30));
                    $refLabel = match ($document->documentable_type) {
                        \App\Models\Customer::class => __('document.ref.customer'),
                        \App\Models\Project::class => __('document.ref.project'),
                        \App\Models\DiaryEntry::class => __('document.ref.diary'),
                        \App\Models\Asset::class => __('document.ref.asset'),
                        default => null,
                    };
                    $refName = $document->documentable?->name
                        ?? $document->documentable?->title
                        ?? null;
                @endphp
                <tr class="hover" id="document-{{ $document->id }}">
                    <td>
                        <span class="flex items-center gap-2 font-medium">
                            <x-icon :name="$document->document_type->icon()" class="text-base-content/60" />
                            {{ $document->title }}
                        </span>
                        @if ($document->description)
                            <span class="block max-w-md truncate text-xs text-base-content/60">{{ $document->description }}</span>
                        @endif
                    </td>
                    <td><x-status-badge tone="ghost" outline>{{ $document->document_type->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">
                        @if ($refLabel !== null)
                            {{ $refLabel }}@if ($refName): {{ $refName }}@endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($document->valid_until === null)
                            <span class="text-base-content/50">—</span>
                        @elseif ($isExpired)
                            <x-status-badge tone="error">{{ $document->valid_until->fdate() }} · {{ __('document.badge.expired') }}</x-status-badge>
                        @elseif ($expiresSoon)
                            <x-status-badge tone="warning">{{ $document->valid_until->fdate() }} · {{ __('document.badge.expires_soon') }}</x-status-badge>
                        @else
                            {{ $document->valid_until->fdate() }}
                        @endif
                    </td>
                    <td><x-status-badge :tone="$effective->tone()">{{ $effective->label() }}</x-status-badge></td>
                    <td>
                        <a href="{{ route('documents.versions', $document) }}" data-entry-modal-trigger class="link link-hover font-mono">
                            v{{ $document->currentVersion?->version_no ?? '—' }}
                        </a>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($document->currentVersion !== null)
                                <x-icon-btn icon="download" tone="outline" size="xs"
                                            :href="route('documents.download', $document)"
                                            :label="__('document.action.download')" />
                            @endif
                            <x-icon-btn icon="history" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('documents.versions', $document)"
                                        :label="__('document.title.versions')" />
                            @can('update', $document)
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('documents.edit', $document)"
                                            :label="__('document.action.edit')" />
                            @endcan
                            @if ($document->status !== \App\Enums\Document\DocumentStatus::Archived)
                                @can('archive', $document)
                                    <x-action-form :action="route('documents.archive', $document)"
                                          data-confirm-title="{{ __('document.action.archive') }}"
                                          :confirm="__('document.confirm_archive')"
                                          confirm-icon="archive"
                                          confirm-tone="warning"
                                          :confirm-label="__('document.action.archive')">
                                        <x-icon-btn icon="archive" tone="warning" size="xs" type="submit"
                                                    :label="__('document.action.archive')" />
                                    </x-action-form>
                                @endcan
                            @endif
                            @can('delete', $document)
                                <x-action-form :action="route('documents.destroy', $document)" method="DELETE"
                                      data-confirm-title="{{ __('document.action.delete') }}"
                                      :confirm="__('document.confirm_delete')"
                                      confirm-icon="delete"
                                      confirm-tone="error"
                                      :confirm-label="__('document.action.delete')">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                                :label="__('document.action.delete')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               :title="__('document.empty_title')"
                               :message="$hasActiveFilters ? __('document.empty_filtered') : __('document.empty')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$documents" standing />
    </x-index-page>
@endsection
