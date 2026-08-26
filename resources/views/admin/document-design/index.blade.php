{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('document_design.title'))
@section('nav-title', __('document_design.title'))

@section('content')
<x-index-page :subtitle="__('document_design.intro')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="ghost" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.document-design.assets.create')"
                        show-label>{{ __('document_design.asset.upload') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.document-design.profiles.create')"
                        show-label>{{ __('document_design.profile.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

        <x-validation-errors first />

        {{-- Renderprofile --}}
        <x-card :title="__('document_design.profiles_heading')">
            @if ($profiles->isEmpty())
                <x-empty-state icon="design_services" :title="__('document_design.no_profiles')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('document_design.profile.name') }}</th>
                                <th>{{ __('document_design.profile.status') }}</th>
                                <th>{{ __('document_design.profile.kinds') }}</th>
                                <th>{{ __('document_design.profile.version') }}</th>
                                <th>{{ __('document_design.profile.default') }}</th>
                                <th class="text-right">{{ __('document_design.actions') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($profiles as $profile)
                                <tr>
                                    <td class="font-medium">{{ $profile->name }}</td>
                                    <td><span class="badge badge-sm badge-{{ $profile->status->tone() === 'success' ? 'success' : 'ghost' }}">{{ $profile->status->label() }}</span></td>
                                    <td class="text-sm text-base-content/70">
                                        {{ collect($profile->document_kinds ?? [])->map(fn($k) => \App\Enums\DocumentDesign\RenderDocumentKind::tryFrom($k)?->label())->filter()->join(', ') ?: '—' }}
                                    </td>
                                    <td>{{ $profile->activeVersion?->version !== null ? 'v' . $profile->activeVersion->version : '—' }}</td>
                                    <td>{{ $profile->is_default ? __('Ja') : '—' }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <x-icon-btn icon="edit"
                                                        :href="route('admin.document-design.editor', $profile->sqid)"
                                                        :label="__('document_design.profile.open_editor')" />
                                            @if ($canManage)
                                                <x-action-form :action="route('admin.document-design.archive', $profile->sqid)" method="POST"
                                                      :confirm="__('document_design.profile.archive_confirm')"
                                                      :confirm-label="__('document_design.profile.archive')">
                                                    <x-icon-btn icon="archive" tone="error" type="submit" :label="__('document_design.profile.archive')" />
                                                </x-action-form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- Firmenbögen --}}
        <x-card :title="__('document_design.assets_heading')">
            @if ($assets->isEmpty())
                <x-empty-state icon="wallpaper" :title="__('document_design.no_assets')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('document_design.asset.name') }}</th>
                                <th>{{ __('document_design.asset.page_role') }}</th>
                                <th>{{ __('document_design.asset.type') }}</th>
                                <th>{{ __('document_design.asset.status') }}</th>
                                <th>{{ __('document_design.asset.uploaded') }}</th>
                                <th class="text-right">{{ __('document_design.actions') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($assets as $asset)
                                <tr>
                                    <td class="font-medium">{{ $asset->name }}</td>
                                    <td>{{ $asset->page_role->label() }}</td>
                                    <td class="uppercase text-sm">{{ $asset->source_type }}</td>
                                    <td>
                                        <span class="badge badge-sm badge-{{ $asset->status->tone() === 'success' ? 'success' : ($asset->status->tone() === 'warning' ? 'warning' : 'ghost') }}">{{ $asset->status->label() }}</span>
                                        @if ($asset->review_notes)
                                            <div class="text-xs text-muted">{{ implode(' ', $asset->review_notes) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-sm text-base-content/70">{{ $asset->created_at->fdate() }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            @if ($asset->normalized_path)
                                                <x-icon-btn icon="visibility"
                                                            :href="route('admin.document-design.assets.preview', $asset->sqid)"
                                                            target="_blank"
                                                            :label="__('document_design.asset.preview')" />
                                            @endif
                                            @if ($canManage)
                                                <x-action-form :action="route('admin.document-design.assets.archive', $asset->sqid)" method="POST"
                                                      :confirm="__('document_design.asset.archive_confirm')"
                                                      :confirm-label="__('document_design.asset.archive')">
                                                    <x-icon-btn icon="archive" tone="error" type="submit" :label="__('document_design.asset.archive')" />
                                                </x-action-form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>
</x-index-page>
@endsection
