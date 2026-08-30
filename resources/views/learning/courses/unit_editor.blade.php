{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : unit_editor.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Autorenwerkzeug einer Lerneinheit (Feature 149, MVP-736): Inhaltsblöcke
  anlegen, sortieren, entfernen. Blöcke sind strukturiert, nicht freies
  HTML — Text bleibt Text. Einbettungen brauchen einen freigegebenen Host
  (CSP `frame-src`), sonst würde der Kurs sie still blockieren.
--}}
@extends('layouts.app')
@section('title', $unit->title)
@section('nav-title', $unit->title)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$course->title" :badge="$unit->kind->label()" badgeTone="info">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('learning.courses.show', $course)"
                            show-label>{{ __('learning.action.back_to_course') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="view_agenda" class="text-muted" /> {{ __('learning.field.blocks') }}
                </h3>

                @forelse ($blocks as $index => $block)
                    @php $kind = \App\Enums\Learning\LearningBlockKind::tryFrom($block['type'] ?? ''); @endphp
                    <div class="mb-3 rounded-box border border-base-300 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <x-icon :name="$kind?->icon() ?? 'help'" class="text-muted" />
                                {{ $kind?->label() ?? ($block['type'] ?? '?') }}
                            </div>
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('learning.courses.units.blocks.move', [$course, $unit, $index]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <x-icon-btn icon="arrow_upward" tone="ghost" size="xs" type="submit"
                                                :label="__('learning.action.move_up')" :disabled="$index === 0" />
                                </form>
                                <form method="POST" action="{{ route('learning.courses.units.blocks.move', [$course, $unit, $index]) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <x-icon-btn icon="arrow_downward" tone="ghost" size="xs" type="submit"
                                                :label="__('learning.action.move_down')" :disabled="$index === count($blocks) - 1" />
                                </form>
                                <form method="POST" action="{{ route('learning.courses.units.blocks.destroy', [$course, $unit, $index]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit"
                                                :label="__('learning.action.remove_block')" />
                                </form>
                            </div>
                        </div>

                        {{-- Vorschau mit demselben Bauteil wie der Kurs: sonst
                             sähe der Stoff beim Schreiben anders aus als beim Lernen. --}}
                        @include('learning._blocks', [
                            'blocks' => [$block],
                            'mediaUrl' => fn (int $id): ?string => ($a = $unit->attachments->firstWhere('id', $id))
                                ? route('learning.courses.units.media', [$course->sqid, $unit->sqid, $a->sqid])
                                : null,
                        ])
                    </div>
                @empty
                    <x-empty-state icon="view_agenda" :title="__('learning.empty.blocks')" compact />
                @endforelse
            </x-card>

            <x-card>
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="add_box" class="text-muted" /> {{ __('learning.action.add_block') }}
                </h3>
                <form method="POST" action="{{ route('learning.courses.units.blocks.store', [$course, $unit]) }}"
                      enctype="multipart/form-data">
                    @csrf
                    <x-form-group :legend="__('learning.field.block')" icon="add_box" tone="primary" cols="2">
                        <x-select-field name="type" :label="__('learning.field.block_kind')" required>
                            @foreach (\App\Enums\Learning\LearningBlockKind::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('type', 'text') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </x-select-field>
                        <x-select-field name="tone" :label="__('learning.field.block_tone')">
                            <option value="">–</option>
                            @foreach (['info', 'warning', 'success', 'error'] as $tone)
                                <option value="{{ $tone }}" @selected(old('tone') === $tone)>{{ $tone }}</option>
                            @endforeach
                        </x-select-field>
                        <x-textarea-field name="text" :label="__('learning.field.block_text')" rows="4" span="2" maxlength="5000" :value="old('text')" />
                        <x-textarea-field name="items" :label="__('learning.field.block_items')" rows="3" span="2" maxlength="5000"
                                          :hint="__('learning.help.block_items')" :value="old('items')" />
                        <x-input-field name="url" type="url" :label="__('learning.field.block_url')" maxlength="2000" :value="old('url')" />
                        <x-input-field name="caption" :label="__('learning.field.block_caption')" maxlength="255" :value="old('caption')" />
                        <x-input-field name="alt" :label="__('learning.field.block_alt')" maxlength="255" :value="old('alt')"
                                       :hint="__('learning.help.block_alt')" />
                        <x-input-field name="require_percent" type="number" min="1" max="100"
                                       :label="__('learning.field.block_require_percent')" :value="old('require_percent')" />
                        <div class="sm:col-span-2">
                            {{-- Bild, Datei und Video tragen ihre Quelle als Anhang
                                 der Lerneinheit — ohne Upload bleibt der Block leer. --}}
                            <label class="label" for="block-media"><span class="label-text">{{ __('learning.field.block_media') }}</span></label>
                            <input type="file" id="block-media" name="media"
                                   class="file-input file-input-bordered file-input-sm w-full">
                            <p class="mt-1 text-xs text-muted">{{ __('learning.help.block_media', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
                        </div>
                    </x-form-group>
                    <div class="mt-3 flex justify-end">
                        <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.add_block') }}</x-icon-btn>
                    </div>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('learning.help.block_fields') }}</p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold">{{ __('learning.field.unit') }}</h3>
                <form method="POST" action="{{ route('learning.courses.units.update', [$course, $unit]) }}">
                    @csrf
                    @method('PUT')
                    <x-form-group :legend="__('learning.field.unit')" icon="playlist_add" tone="primary" cols="1">
                        <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" :value="old('title', $unit->title)" />
                        <x-input-field name="duration_minutes" type="number" min="1" max="10000" :label="__('learning.field.duration_minutes')" :value="old('duration_minutes', $unit->duration_minutes)" />
                        <x-input-field name="points" type="number" min="0" max="1000" :label="__('learning.field.points')" :value="old('points', $unit->points)" />
                        <x-input-field name="release_after_days" type="number" min="0" max="3650"
                                       :label="__('learning.field.release_after_days')"
                                       :hint="__('learning.help.release_after_days')"
                                       :value="old('release_after_days', $unit->release_rule['after_days'] ?? null)" />
                        <x-checkbox-field name="is_mandatory" :label="__('learning.field.is_mandatory')" :checked="(bool) old('is_mandatory', $unit->is_mandatory)" />
                    </x-form-group>
                    <div class="mt-3 flex justify-end">
                        <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.save') }}</x-icon-btn>
                    </div>
                </form>
            </x-card>

            @php
                $videos = $unit->attachments->filter(fn ($a) => $a->media_state !== null);
            @endphp
            @if ($videos->isNotEmpty())
                {{-- Videos (Feature 150): Verarbeitungsstand und Untertitel.
                     Untertitel sind beim Verkauf an Verbraucher Pflicht
                     (WCAG 1.2.2), und eine maschinelle Spur zählt erst nach
                     Durchsicht — deshalb Upload von Hand. --}}
                <x-card>
                    <h3 class="mb-2 text-sm font-semibold">{{ __('media.section') }}</h3>

                    @foreach ($videos as $video)
                        <div class="mb-3 rounded-box border border-base-300 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="truncate text-sm">{{ $video->original_name }}</span>
                                <x-status-badge :tone="$video->media_state->tone()" size="sm">
                                    {{ $video->media_state->label() }}
                                </x-status-badge>
                            </div>

                            @if ($video->media_error)
                                <p class="mt-1 text-xs text-error">{{ $video->media_error }}</p>
                            @endif

                            @if ($video->media_duration_seconds)
                                <p class="mt-1 text-xs text-muted">
                                    {{ __('media.field.duration') }}: {{ gmdate('i:s', $video->media_duration_seconds) }}
                                    @if ($video->media_height) · {{ $video->media_width }}×{{ $video->media_height }} @endif
                                </p>
                            @endif

                            <form method="POST" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2"
                                  action="{{ route('learning.courses.units.subtitles.store', [$course->sqid, $unit->sqid, $video->sqid]) }}">
                                @csrf
                                <div>
                                    <label class="label" for="vtt-locale-{{ $video->id }}"><span class="label-text">{{ __('learning.field.locale') }}</span></label>
                                    <select class="select select-bordered select-sm" id="vtt-locale-{{ $video->id }}" name="locale" required>
                                        @foreach ((array) config('app.available_locales', ['de']) as $available)
                                            <option value="{{ $available }}">{{ strtoupper($available) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grow">
                                    <label class="label" for="vtt-file-{{ $video->id }}"><span class="label-text">{{ __('media.field.subtitles') }}</span></label>
                                    <input type="file" id="vtt-file-{{ $video->id }}" name="vtt" accept=".vtt,text/vtt" required
                                           class="file-input file-input-bordered file-input-sm w-full">
                                </div>
                                <x-icon-btn icon="subtitles" tone="primary" size="sm" type="submit"
                                            :label="__('media.field.subtitles')" />
                            </form>
                            <p class="mt-1 text-xs text-muted">{{ __('media.help.subtitle_upload') }}</p>

                            @if ($canTranscribe)
                                {{-- Maschinelle Spur (Feature 150): Whisper läuft
                                     lokal auf demselben Server — es verlässt kein
                                     Byte das Haus. Das Ergebnis ist ein Entwurf. --}}
                                <form method="POST" class="mt-2 flex flex-wrap items-end gap-2"
                                      action="{{ route('learning.courses.units.subtitles.transcribe', [$course->sqid, $unit->sqid, $video->sqid]) }}">
                                    @csrf
                                    <div>
                                        <label class="label" for="auto-locale-{{ $video->id }}"><span class="label-text">{{ __('media.field.transcribe_locale') }}</span></label>
                                        <select class="select select-bordered select-sm" id="auto-locale-{{ $video->id }}" name="locale" required>
                                            @foreach ((array) config('app.available_locales', ['de']) as $available)
                                                <option value="{{ $available }}">{{ strtoupper($available) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit" show-label
                                                :label="__('media.action.transcribe')" />
                                </form>
                                <p class="mt-1 text-xs text-muted">{{ __('media.help.transcribe') }}</p>
                            @endif

                            @php($tracks = ($subtitles[$video->id] ?? collect()))
                            @if ($tracks->isNotEmpty())
                                <ul class="mt-3 space-y-1">
                                    @foreach ($tracks as $track)
                                        <li class="flex flex-wrap items-center gap-2 rounded-box bg-base-200 px-2 py-1">
                                            <span class="font-mono text-xs">{{ strtoupper((string) $track->locale) }}</span>
                                            <x-status-badge :tone="$track->source->tone()" size="sm">{{ $track->source->label() }}</x-status-badge>
                                            @if ($track->awaitsReview())
                                                <span class="text-xs text-warning">{{ __('media.label.awaits_review') }}</span>
                                            @elseif ($track->reviewed_at)
                                                <span class="text-xs text-muted">{{ __('media.label.reviewed_on', ['date' => $track->reviewed_at->isoFormat('L')]) }}</span>
                                            @endif
                                            <span class="grow"></span>
                                            @if ($track->awaitsReview())
                                                <form method="POST" action="{{ route('learning.courses.units.subtitles.review', [$course->sqid, $unit->sqid, $track->sqid]) }}">
                                                    @csrf
                                                    <x-icon-btn icon="done" tone="ghost" size="xs" type="submit"
                                                                :label="__('media.action.mark_reviewed')" />
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('learning.courses.units.subtitles.destroy', [$course->sqid, $unit->sqid, $track->sqid]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-icon-btn icon="delete" tone="ghost" size="xs" type="submit"
                                                            :label="__('media.action.remove_subtitle')" />
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </x-card>
            @endif

            @if ($unit->kind === \App\Enums\Learning\LearningUnitKind::Scorm)
                {{-- SCORM-Paket (MVP-743). Der Import entpackt geprüft: keine
                     ausführbaren Dateien, kein Pfad-Ausbruch, Größen-Deckel. --}}
                <x-card>
                    <h3 class="mb-2 text-sm font-semibold">{{ __('learning.field.scorm_package') }}</h3>

                    @if ($unit->scormPackage)
                        <x-detail-grid>
                            <x-detail-grid.row :label="__('learning.field.title')">{{ $unit->scormPackage->title }}</x-detail-grid.row>
                            <x-detail-grid.row :label="__('learning.field.scorm_version')">{{ $unit->scormPackage->version }}</x-detail-grid.row>
                            <x-detail-grid.row :label="__('learning.field.scorm_launch')"><span class="font-mono text-xs">{{ $unit->scormPackage->launch_href }}</span></x-detail-grid.row>
                            <x-detail-grid.row :label="__('learning.field.scorm_files')">{{ $unit->scormPackage->file_count }}</x-detail-grid.row>
                        </x-detail-grid>
                    @else
                        <p class="text-sm text-base-content/80">{{ __('learning.help.scorm_empty') }}</p>
                    @endif

                    <form method="POST" action="{{ route('learning.courses.units.scorm.import', [$course, $unit]) }}"
                          enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <label class="label" for="scorm-package"><span class="label-text">{{ __('learning.field.scorm_upload') }}</span></label>
                        <input type="file" id="scorm-package" name="package" required accept=".zip,application/zip"
                               class="file-input file-input-bordered file-input-sm w-full">
                        <p class="mt-1 text-xs text-muted">{{ __('learning.help.scorm_upload') }}</p>
                        <div class="mt-2 flex justify-end">
                            <x-icon-btn icon="upload_file" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.import_scorm') }}</x-icon-btn>
                        </div>
                    </form>
                </x-card>
            @endif

            <x-card>
                <h3 class="mb-2 text-sm font-semibold">{{ __('learning.field.embed_hosts') }}</h3>
                @if ($allowedHosts === [])
                    <p class="text-sm text-base-content/80">{{ __('learning.help.embed_hosts_empty') }}</p>
                @else
                    <ul class="list-disc pl-5 text-sm text-base-content/80">
                        @foreach ($allowedHosts as $host)
                            <li class="font-mono">{{ $host }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
