{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : step_edit.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schritt einer Bildanleitung bearbeiten (Feature 191, MVP-1668): Pfeil,
  Nummer, Rahmen und Text als Markierung, Schwärzen als Rechteck. Zeichnen
  übernimmt resources/js/guide-editor.js; gespeichert werden Markierungen
  und Schwärzungen als JSON. Schwärzen schreibt das Bild neu und verwirft
  das Original. Variablen: $page, $step, $imageUrl, $number.
--}}
@extends('layouts.app')

@section('title', __('knowledge_pages.guide.step_number', ['number' => $number]) . ' — ' . $page->title)
@section('nav-title', __('knowledge_pages.guide.edit_step'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
    <x-page-toolbar :subtitle="$page->title . ' · ' . __('knowledge_pages.guide.step_number', ['number' => $number])" :back="route('knowledge.pages.show', $page)" />
    </x-slot:toolbar>

    <form method="POST" action="{{ route('knowledge.pages.steps.update', [$page, $step]) }}" enctype="multipart/form-data"
          data-guide-editor
          data-label-remove="{{ __('knowledge_pages.guide.remove_marker') }}"
          data-label-redaction="{{ __('knowledge_pages.guide.redaction') }}"
          data-label-prompt="{{ __('knowledge_pages.guide.label_needed') }}"
          data-marker-names="{{ \CommonToolkit\Helper\Data\JsonHelper::encode(collect(\App\Enums\Knowledge\KnowledgeGuideMarkerKind::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="markers" value="{{ \CommonToolkit\Helper\Data\JsonHelper::encode(array_values($step->markers ?? [])) }}" data-guide-markers-input>
        <input type="hidden" name="redactions" value="[]" data-guide-redactions-input>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_20rem]">
            <x-card :title="__('knowledge_pages.guide.image')" icon="image">
                @if ($imageUrl)
                    <fieldset class="mb-3 flex flex-wrap items-center gap-3 text-sm" data-guide-tools>
                        <legend class="sr-only">{{ __('knowledge_pages.guide.tool') }}</legend>
                        @foreach (\App\Enums\Knowledge\KnowledgeGuideMarkerKind::cases() as $toolCase)
                            <label class="flex items-center gap-1"><input type="radio" name="tool" value="{{ $toolCase->value }}" class="radio radio-sm" @checked($loop->first)> {{ $toolCase->label() }}</label>
                        @endforeach
                        <label class="flex items-center gap-1 text-error"><input type="radio" name="tool" value="redact" class="radio radio-sm radio-error"> {{ __('knowledge_pages.guide.redaction') }}</label>
                        <label for="kp-marker-label" class="sr-only">{{ __('knowledge_pages.guide.marker_label') }}</label>
                        <input id="kp-marker-label" type="text" maxlength="120" class="input input-sm input-bordered w-48" placeholder="{{ __('knowledge_pages.guide.marker_label') }}" data-guide-label>
                    </fieldset>
                    <div class="relative inline-block max-w-full touch-none select-none overflow-hidden rounded-box border border-base-300" data-guide-image data-guide-stage
                         data-markers="{{ \CommonToolkit\Helper\Data\JsonHelper::encode(array_values($step->markers ?? [])) }}">
                        <img src="{{ $imageUrl }}" alt="{{ __('knowledge_pages.guide.step_number', ['number' => $number]) }}" class="block h-auto max-w-full" data-guide-picture draggable="false">
                        <canvas class="absolute inset-0 h-full w-full cursor-crosshair" aria-hidden="true" data-guide-canvas></canvas>
                    </div>
                    <p class="mt-2 text-xs text-muted">{{ __('knowledge_pages.guide.draw_hint') }}</p>
                    <div class="alert alert-warning mt-2 text-sm" role="alert">
                        <x-icon name="ink_eraser" />
                        <span>{{ __('knowledge_pages.guide.redact_warning') }}</span>
                    </div>
                    @if ($step->redacted_at)
                        <p class="mt-2 text-xs text-muted">{{ __('knowledge_pages.guide.redacted_at', ['date' => $step->redacted_at->fdatetime()]) }}</p>
                    @endif
                @else
                    <x-empty-state compact icon="hide_image" :title="__('knowledge_pages.guide.no_image')" />
                @endif
                <label class="form-control mt-3" for="kp-step-replace">
                    <span class="label-text">{{ __('knowledge_pages.guide.replace_image') }}</span>
                    <input id="kp-step-replace" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered file-input-sm w-full" />
                </label>
            </x-card>

            <div class="space-y-4">
                <x-card :title="__('knowledge_pages.guide.markers')" icon="label">
                    <ul class="space-y-1 text-sm" role="list" data-guide-marker-list></ul>
                    <p class="text-xs text-muted" data-guide-marker-empty>{{ __('knowledge_pages.guide.no_markers') }}</p>
                </x-card>
                <x-card :title="__('knowledge_pages.guide.text')" icon="notes">
                    <x-textarea-field name="body" :label="__('knowledge_pages.guide.text')" rows="6" maxlength="5000" :value="old('body', $step->body)" />
                </x-card>
                <div class="flex justify-end gap-2">
                    <x-icon-btn icon="close" size="sm" tone="outline" :href="route('knowledge.pages.show', $page)" show-label>{{ __('knowledge_pages.action.cancel') }}</x-icon-btn>
                    <x-icon-btn icon="save" size="sm" tone="primary" type="submit" show-label>{{ __('knowledge_pages.action.save') }}</x-icon-btn>
                </div>
            </div>
        </div>
    </form>
</x-page-shell>
@endsection
