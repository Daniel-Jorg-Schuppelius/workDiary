@extends('layouts.app')
@section('title', __('document_design.editor.title', ['name' => $profile->name]))
@section('nav-title', __('document_design.editor.title', ['name' => $profile->name]))

@section('content')
@php
    /** @var \App\Models\DocumentDesign\DocumentRenderProfile $profile */
    /** @var \App\Models\DocumentDesign\DocumentRenderProfileVersion $version */
    $editorConfig = [
        'saveUrl' => route('admin.document-design.draft.update', $profile->sqid),
        'layout' => $version->layout,
        'blocks' => $version->block_rules,
        'tableStyle' => $version->table_style,
        'preflight' => $preflight,
        'editable' => $isDraft && $canManage,
    ];
    $blockCases = \App\Enums\DocumentDesign\InformationBlock::cases();
    $stateCases = \App\Enums\DocumentDesign\InformationBlockState::cases();
@endphp

<x-page-shell>
    <div class="space-y-4" x-data="designEditor" data-config="{{ json_encode($editorConfig) }}"
         @pointermove.window="onPointerMove($event)" @pointerup.window="endDrag()" @keydown.window="nudge($event)">

        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif

        {{-- Kopf: Versionsstand + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $profile->name }}</h1>
                    <p class="text-sm text-base-content/60">
                        {{ __('document_design.editor.version_line', ['v' => $version->version, 'status' => $isDraft ? __('Entwurf') : __('Aktiv')]) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Vollaudit 2026-07 (N3): x-page-shell hat keinen actions-Slot —
                         der Zurück-Link gehört in die Kopf-Karte. --}}
                    <a href="{{ route('admin.document-design.index') }}" class="btn btn-sm btn-ghost">{{ __('Zurück zur Übersicht') }}</a>
                    <span class="text-xs text-base-content/50" x-show="dirty">{{ __('document_design.editor.unsaved') }}</span>
                    <template x-if="message && message.tone === 'error'">
                        <span class="badge badge-error badge-sm" x-text="message.text"></span>
                    </template>
                    @if ($isDraft && $canManage)
                        <button type="button" class="btn btn-sm btn-primary" @click="save()" :disabled="saving">
                            {{ __('document_design.editor.save_draft') }}
                        </button>
                        <x-action-form :action="route('admin.document-design.activate', $profile->sqid)" method="POST"
                              :confirm="__('document_design.editor.activate_confirm')"
                              :confirm-label="__('document_design.editor.activate')">
                            <button type="submit" class="btn btn-sm btn-success">{{ __('document_design.editor.activate') }}</button>
                        </x-action-form>
                    @elseif ($canManage)
                        <x-action-form :action="route('admin.document-design.draft.new', $profile->sqid)" method="POST">
                            <input type="hidden" name="source" value="{{ $version->sqid }}">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('document_design.editor.new_draft') }}</button>
                        </x-action-form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Preflight-Ergebnis --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs" x-show="preflight.errors.length || preflight.warnings.length" x-cloak>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.preflight') }}</h2>
            <ul class="space-y-1 text-sm">
                <template x-for="issue in preflight.errors" :key="issue.code + (issue.block || '') + (issue.page || '')">
                    <li class="flex items-start gap-2"><span class="badge badge-error badge-xs mt-1"></span><span x-text="issue.message"></span></li>
                </template>
                <template x-for="issue in preflight.warnings" :key="'w' + issue.code + (issue.block || '') + (issue.page || '')">
                    <li class="flex items-start gap-2"><span class="badge badge-warning badge-xs mt-1"></span><span x-text="issue.message"></span></li>
                </template>
            </ul>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Visuelle A4-Vorschau --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.preview_heading') }}</h2>
                    <div class="join">
                        <button type="button" class="btn btn-xs join-item" :class="page === 'first' ? 'btn-primary' : 'btn-ghost'" @click="page = 'first'">{{ __('Erste Seite') }}</button>
                        <button type="button" class="btn btn-xs join-item" :class="page === 'following' ? 'btn-primary' : 'btn-ghost'" @click="page = 'following'">{{ __('Folgeseiten') }}</button>
                    </div>
                </div>
                <p class="mb-2 text-xs text-base-content/50">{{ __('document_design.editor.preview_hint') }}</p>

                <div data-page-canvas
                     class="relative mx-auto w-full max-w-105 border border-base-300 bg-white shadow-sm select-none"
                     style="aspect-ratio: 210 / 297;"
                     role="application" aria-label="{{ __('document_design.editor.preview_heading') }}">
                    {{-- Firmenbogen-Hintergrund --}}
                    @if ($version->firstAsset?->normalized_path)
                        <img src="{{ route('admin.document-design.assets.preview', $version->firstAsset->sqid) }}"
                             class="absolute inset-0 h-full w-full object-fill" alt="" x-show="page === 'first'">
                    @endif
                    @if ($version->followingAsset?->normalized_path)
                        <img src="{{ route('admin.document-design.assets.preview', $version->followingAsset->sqid) }}"
                             class="absolute inset-0 h-full w-full object-fill" alt="" x-show="page === 'following'">
                    @endif

                    {{-- Inhaltsbereich --}}
                    <div class="absolute border-2 border-primary/70 bg-primary/5 cursor-move focus:outline-2"
                         :style="styleFor('content')"
                         :class="isSelected('content') ? 'ring-2 ring-primary' : ''"
                         tabindex="0" role="button"
                         aria-label="{{ __('document_design.editor.region.content') }}"
                         @pointerdown="startDrag($event, 'content')"
                         @focus="select('content')">
                        <span class="absolute left-1 top-0 text-[10px] text-primary/80">{{ __('document_design.editor.region.content') }}</span>
                        <span class="absolute bottom-0 right-0 h-3 w-3 cursor-nwse-resize bg-primary/70"
                              @pointerdown.stop="startDrag($event, 'content', null, 'resize')"></span>
                    </div>

                    {{-- Adressfenster + Absenderzeile (nur erste Seite) --}}
                    <template x-if="page === 'first' && layout.address_window">
                        <div class="absolute border-2 border-info/70 bg-info/10 cursor-move"
                             :style="styleFor('address_window')"
                             :class="isSelected('address_window') ? 'ring-2 ring-info' : ''"
                             tabindex="0" role="button"
                             aria-label="{{ __('document_design.editor.region.address') }}"
                             @pointerdown="startDrag($event, 'address_window')"
                             @focus="select('address_window')">
                            <span class="absolute left-1 top-0 text-[10px] text-info/80">{{ __('document_design.editor.region.address') }}</span>
                            <span class="absolute bottom-0 right-0 h-3 w-3 cursor-nwse-resize bg-info/70"
                                  @pointerdown.stop="startDrag($event, 'address_window', null, 'resize')"></span>
                        </div>
                    </template>
                    <template x-if="page === 'first' && layout.sender_line">
                        <div class="absolute border border-dashed border-info/70 cursor-move"
                             :style="styleFor('sender_line')"
                             :class="isSelected('sender_line') ? 'ring-2 ring-info' : ''"
                             tabindex="0" role="button"
                             aria-label="{{ __('document_design.editor.region.sender') }}"
                             @pointerdown="startDrag($event, 'sender_line')"
                             @focus="select('sender_line')">
                            <span class="absolute left-1 top-0 text-[10px] text-info/80">{{ __('document_design.editor.region.sender') }}</span>
                        </div>
                    </template>

                    {{-- Sperrflächen --}}
                    <template x-for="(area, index) in layout.blocked_areas" :key="index">
                        <div x-show="blockedVisible(area)"
                             class="absolute border-2 border-error/70 bg-error/10 cursor-move"
                             :style="styleFor('blocked', index)"
                             :class="isSelected('blocked', index) ? 'ring-2 ring-error' : ''"
                             tabindex="0" role="button"
                             aria-label="{{ __('document_design.editor.region.blocked') }}"
                             @pointerdown="startDrag($event, 'blocked', index)"
                             @focus="select('blocked', index)">
                            <span class="absolute left-1 top-0 text-[10px] text-error/80" x-text="area.label || '{{ __('document_design.editor.region.blocked') }}'"></span>
                            <span class="absolute bottom-0 right-0 h-3 w-3 cursor-nwse-resize bg-error/70"
                                  @pointerdown.stop="startDrag($event, 'blocked', index, 'resize')"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Numerische Millimeterwerte + Optionen --}}
            <div class="space-y-4">
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.margins_heading') }}</h2>
                    @foreach ([['content_first', __('Erste Seite')], ['content_following', __('Folgeseiten')]] as [$key, $label])
                        <fieldset class="mb-3">
                            <legend class="text-sm font-medium">{{ $label }} ({{ __('Ränder in mm') }})</legend>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach (['top' => __('Oben'), 'right' => __('Rechts'), 'bottom' => __('Unten'), 'left' => __('Links')] as $side => $sideLabel)
                                    <label class="form-control">
                                        <span class="label-text text-xs">{{ $sideLabel }}</span>
                                        <input type="number" step="0.5" min="0" max="297"
                                               class="input input-bordered input-xs"
                                               x-model.number="layout.{{ $key }}.{{ $side }}"
                                               @change="markDirty()"
                                               :disabled="!editable">
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-xs btn-outline" @click="toggleAddressWindow()" :disabled="!editable">
                            <span x-text="layout.address_window ? '{{ __('document_design.editor.address_remove') }}' : '{{ __('document_design.editor.address_add') }}'"></span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline" @click="toggleSenderLine()" :disabled="!editable">
                            <span x-text="layout.sender_line ? '{{ __('document_design.editor.sender_remove') }}' : '{{ __('document_design.editor.sender_add') }}'"></span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline" @click="addBlockedArea()" :disabled="!editable">{{ __('document_design.editor.blocked_add') }}</button>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="checkbox checkbox-xs" x-model="layout.footer.page_numbers" @change="markDirty()" :disabled="!editable">
                            {{ __('document_design.editor.page_numbers') }}
                        </label>
                    </div>

                    <template x-if="layout.blocked_areas.length">
                        <div class="mt-3 space-y-2">
                            <template x-for="(area, index) in layout.blocked_areas" :key="'row' + index">
                                <div class="flex flex-wrap items-end gap-2 text-xs">
                                    <input type="text" class="input input-bordered input-xs w-28" x-model="area.label" @change="markDirty()" :disabled="!editable" placeholder="{{ __('Bezeichnung') }}">
                                    <select class="select select-bordered select-xs" x-model="area.page" @change="markDirty()" :disabled="!editable">
                                        <option value="all">{{ __('Alle Seiten') }}</option>
                                        <option value="first">{{ __('Erste Seite') }}</option>
                                        <option value="following">{{ __('Folgeseiten') }}</option>
                                    </select>
                                    <template x-for="field in ['x','y','width','height']" :key="field">
                                        <input type="number" step="0.5" class="input input-bordered input-xs w-16"
                                               x-model.number="area[field]" @change="markDirty()" :disabled="!editable">
                                    </template>
                                    <button type="button" class="btn btn-ghost btn-xs text-error" @click="removeBlockedArea(index)" :disabled="!editable">✕</button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Firmenbogen-Zuordnung --}}
                @if ($canManage && $isDraft)
                    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                        <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.assets_heading') }}</h2>
                        <form method="POST" action="{{ route('admin.document-design.draft.update', $profile->sqid) }}" class="grid gap-2 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <label class="form-control">
                                <span class="label-text text-sm">{{ __('Erste Seite') }}</span>
                                <select name="first_asset" class="select select-bordered select-sm">
                                    <option value="">{{ __('document_design.editor.no_letterhead') }}</option>
                                    @foreach ($assetsFirst as $asset)
                                        <option value="{{ $asset->sqid }}" @selected($version->first_asset_id === $asset->id)>{{ $asset->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="form-control">
                                <span class="label-text text-sm">{{ __('Folgeseiten') }}</span>
                                <select name="following_asset" class="select select-bordered select-sm">
                                    <option value="">{{ __('document_design.editor.no_letterhead') }}</option>
                                    @foreach ($assetsFollowing as $asset)
                                        <option value="{{ $asset->sqid }}" @selected($version->following_asset_id === $asset->id)>{{ $asset->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn btn-sm btn-outline">{{ __('document_design.editor.assets_save') }}</button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Informationsblöcke --}}
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.blocks_heading') }}</h2>
                    <p class="mb-2 text-xs text-base-content/50">{{ __('document_design.editor.blocks_hint') }}</p>
                    <div class="space-y-2">
                        @foreach ($blockCases as $block)
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-200 pb-1 text-sm">
                                <span>{{ $block->label() }}</span>
                                <div class="flex items-center gap-2">
                                    <select class="select select-bordered select-xs"
                                            x-model="blocks['{{ $block->value }}'].state"
                                            @change="markDirty()"
                                            :disabled="!editable">
                                        @foreach ($stateCases as $state)
                                            @if ($state !== \App\Enums\DocumentDesign\InformationBlockState::ProvidedByLetterhead || ! $block->dynamicOnly())
                                                <option value="{{ $state->value }}">{{ $state->label() }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-1 text-xs"
                                           x-show="blocks['{{ $block->value }}'].state === 'provided_by_letterhead'" x-cloak>
                                        <input type="checkbox" class="checkbox checkbox-xs"
                                               x-model="blocks['{{ $block->value }}'].confirmed"
                                               @change="markDirty()"
                                               :disabled="!editable">
                                        {{ __('document_design.editor.block_confirmed') }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tabellenstil --}}
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.table_heading') }}</h2>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="form-control">
                            <span class="label-text text-sm">{{ __('document_design.editor.table_preset') }}</span>
                            <select class="select select-bordered select-sm" x-model="tableStyle.preset" @change="markDirty()" :disabled="!editable">
                                @foreach ($presets as $preset)
                                    <option value="{{ $preset->value }}">{{ $preset->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-control">
                            <span class="label-text text-sm">{{ __('document_design.editor.accent_color') }}</span>
                            <input type="color" class="input input-bordered input-sm w-full"
                                   x-model="tableStyle.overrides.accent_color" @change="markDirty()" :disabled="!editable">
                        </label>
                        <label class="form-control">
                            <span class="label-text text-sm">{{ __('document_design.editor.header_fill') }}</span>
                            <input type="color" class="input input-bordered input-sm w-full"
                                   x-model="tableStyle.overrides.header_fill" @change="markDirty()" :disabled="!editable">
                        </label>
                        <label class="form-control">
                            <span class="label-text text-sm">{{ __('document_design.editor.font_size') }}</span>
                            <input type="number" min="8" max="14" class="input input-bordered input-sm w-full"
                                   x-model.number="tableStyle.overrides.font_size" @change="markDirty()" :disabled="!editable">
                        </label>
                        <label class="flex items-center gap-2 text-sm sm:col-span-2">
                            <input type="checkbox" class="checkbox checkbox-sm" x-model="tableStyle.overrides.zebra" @change="markDirty()" :disabled="!editable">
                            {{ __('document_design.editor.zebra') }}
                        </label>
                    </div>
                </div>

                {{-- Testdokumente --}}
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.test_heading') }}</h2>
                    <p class="mb-2 text-xs text-base-content/50">{{ __('document_design.editor.test_hint') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($kinds as $kind)
                            <a class="btn btn-xs btn-outline"
                               href="{{ route('admin.document-design.test-pdf', ['profile' => $profile->sqid, 'kind' => $kind->value]) }}">
                                {{ $kind->label() }}
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Zuweisung + Versionen --}}
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('document_design.editor.assign_heading') }}</h2>
                    <form method="POST" action="{{ route('admin.document-design.assign', $profile->sqid) }}" class="space-y-2">
                        @csrf
                        @foreach ($kinds as $kind)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="document_kinds[]" value="{{ $kind->value }}" class="checkbox checkbox-sm"
                                       @checked(in_array($kind->value, $profile->document_kinds ?? [], true))>
                                {{ $kind->label() }}
                            </label>
                        @endforeach
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-sm" @checked($profile->is_default)>
                            {{ __('document_design.profile.set_default') }}
                        </label>
                        <button type="submit" class="btn btn-sm btn-outline">{{ __('document_design.editor.assign_save') }}</button>
                    </form>

                    <div class="mt-4">
                        <h3 class="text-sm font-medium">{{ __('document_design.editor.versions_heading') }}</h3>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($versions as $v)
                                <li class="flex items-center justify-between gap-2">
                                    <span>
                                        v{{ $v->version }} — {{ $v->status === 'active' ? __('Aktiv') : ($v->status === 'draft' ? __('Entwurf') : __('Abgelöst')) }}
                                        @if ($v->activated_at) · {{ $v->activated_at->fdate() }} @endif
                                    </span>
                                    @if ($canManage && $v->status === 'superseded')
                                        <x-action-form :action="route('admin.document-design.draft.new', $profile->sqid)" method="POST"
                                              :confirm="__('document_design.editor.rollback_confirm', ['v' => $v->version])"
                                              :confirm-label="__('document_design.editor.rollback')">
                                            <input type="hidden" name="source" value="{{ $v->sqid }}">
                                            <button type="submit" class="btn btn-ghost btn-xs">{{ __('document_design.editor.rollback') }}</button>
                                        </x-action-form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-page-shell>
@endsection
