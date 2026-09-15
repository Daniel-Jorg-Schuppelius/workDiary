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

        {{-- Einstieg: fünf Schritte bis zum wirksamen Design (bis alle erledigt sind) --}}
        @unless ($checklist['complete'])
            @php
                $editorUrl = $checklist['editor_profile'] ? route('admin.document-design.editor', $checklist['editor_profile']->sqid) : null;
                $checklistSteps = [
                    ['key' => 'letterhead', 'icon' => 'upload_file', 'modal' => true, 'href' => $canManage ? route('admin.document-design.assets.create') : null, 'label' => __('document_design.asset.upload')],
                    ['key' => 'profile', 'icon' => 'add', 'modal' => true, 'href' => $canManage ? route('admin.document-design.profiles.create') : null, 'label' => __('document_design.profile.create')],
                    ['key' => 'design', 'icon' => 'edit', 'modal' => false, 'href' => $editorUrl, 'label' => __('document_design.checklist.open_editor')],
                    ['key' => 'activate', 'icon' => 'verified', 'modal' => false, 'href' => $editorUrl, 'label' => __('document_design.checklist.open_editor')],
                    ['key' => 'assign', 'icon' => 'assignment_turned_in', 'modal' => false, 'href' => $editorUrl ? $editorUrl . '#tab-release' : null, 'label' => __('document_design.checklist.open_editor')],
                ];
            @endphp
            <x-card :title="__('document_design.checklist.heading')" icon="checklist">
                <p class="mb-3 text-sm text-muted">{{ __('document_design.checklist.hint') }}</p>
                <ol class="grid gap-2 md:grid-cols-5" data-design-checklist>
                    @foreach ($checklistSteps as $i => $step)
                        @php
                            $done = $checklist['steps'][$step['key']];
                            $hint = $step['key'] === 'letterhead' && $checklist['review_only']
                                ? __('document_design.checklist.step.letterhead_review')
                                : __('document_design.checklist.step.' . $step['key'] . '_hint');
                        @endphp
                        <li class="flex flex-col gap-1 rounded-box border p-3 {{ $done ? 'border-success/40 bg-success/5' : 'border-base-300' }}">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $done ? 'bg-success text-success-content' : 'bg-base-200' }}"
                                      aria-label="{{ $done ? __('document_design.checklist.done') : __('document_design.checklist.open') }}">
                                    @if ($done)
                                        <x-icon name="check" />
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </span>
                                <span class="text-sm font-medium">{{ __('document_design.checklist.step.' . $step['key']) }}</span>
                            </div>
                            <p class="text-xs text-muted">{{ $hint }}</p>
                            @if (! $done && $step['href'])
                                <div class="mt-auto pt-1">
                                    <x-icon-btn :icon="$step['icon']" tone="outline" size="xs" :href="$step['href']"
                                                :data-entry-modal-trigger="$step['modal'] ? true : null" show-label>{{ $step['label'] }}</x-icon-btn>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endunless

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
                                <th class="w-16">{{ __('document_design.asset.thumbnail') }}</th>
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
                                    <td>
                                        <a href="{{ route('admin.document-design.assets.show', $asset->sqid) }}" data-entry-modal-trigger
                                           class="inline-block" title="{{ __('document_design.asset.show') }}">
                                            @if ($asset->normalized_path)
                                                <img src="{{ route('admin.document-design.assets.preview', $asset->sqid) }}" alt=""
                                                     class="h-8 w-auto rounded border border-base-300 bg-white" loading="lazy">
                                            @else
                                                <span class="flex h-8 w-6 items-center justify-center rounded border border-dashed border-base-300 text-base-content/50">
                                                    <x-icon :name="$asset->source_type === 'pdf' ? 'picture_as_pdf' : 'image'" />
                                                </span>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="font-medium">
                                        <a href="{{ route('admin.document-design.assets.show', $asset->sqid) }}" data-entry-modal-trigger class="link link-hover">{{ $asset->name }}</a>
                                    </td>
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
                                            <x-icon-btn icon="visibility" data-entry-modal-trigger
                                                        :href="route('admin.document-design.assets.show', $asset->sqid)"
                                                        :label="__('document_design.asset.show')" />
                                            <x-icon-btn icon="download" target="_blank"
                                                        :href="route('admin.document-design.assets.original', $asset->sqid)"
                                                        :label="__('document_design.asset.download_original')" />
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
