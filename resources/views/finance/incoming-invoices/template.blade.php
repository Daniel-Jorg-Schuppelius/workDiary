{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : template.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zonen-Editor (Phase 151, MVP-1220): Seitenbild, Wortboxen, Zonen je Feld; Logik in resources/js/zone-editor.js. --}}
@extends('layouts.app')
@section('title', __('Vorlage'))
@section('nav-title', __('Vorlage'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar back-route="finance.incoming-invoices.show" :back-params="[$document]" :back-label="__('Zum Beleg')">
            <x-slot:title>{{ __('Vorlage') }}: {{ $document->title }}</x-slot:title>
            <x-slot:subtitle>{{ __('Zonen ziehen, Wert prüfen, als Vorlage des Lieferanten speichern.') }}</x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    <form method="POST" action="{{ route('finance.incoming-invoices.template.store', $document) }}" data-zone-editor data-config="{{ $config }}" data-entry-form>
        @csrf
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0">
                @if ($pages === [])
                    <x-empty-state icon="image_not_supported" tone="warning" framed
                                   :title="__('Keine Seitenbilder')" :message="__('Das Original konnte nicht gerendert werden.')" />
                @endif
                <div data-zone-pages class="space-y-4 select-none"></div>
            </div>
            <aside class="space-y-4">
                <x-card :title="__('Zonen')">
                    <x-select-field name="field" :label="__('Feld')" data-zone-field>
                        @foreach ($fields as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-select-field>
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" class="checkbox checkbox-sm" data-zone-show-boxes checked>
                        <span class="label-text">{{ __('Wörter anzeigen') }}</span>
                    </label>
                    <p class="text-xs text-muted">{{ __('Feld wählen, dann auf dem Beleg ein Rechteck aufziehen. Der erkannte Wert erscheint sofort.') }}</p>
                    <ul class="mt-2 space-y-1 text-sm" data-zone-list></ul>
                </x-card>
                <x-card :title="__('Speichern')">
                    <x-input-field name="name" :label="__('Name der Vorlage')" :value="old('name', $template?->name ?? $incoming->seller_name ?? $incoming->counterparty()?->name ?? '')" required maxlength="191" />
                    <input type="hidden" name="zones" value="[]" data-zone-input>
                    @if ($openCount > 0)
                        <x-checkbox-field name="apply_to_open" :label="trans_choice('Auch auf :count offenen Eingang anwenden|Auch auf :count offene Eingänge anwenden', $openCount, ['count' => $openCount])" :checked="(bool) old('apply_to_open', true)" />
                    @endif
                    <x-button type="submit" tone="primary" icon="save">{{ __('Vorlage speichern') }}</x-button>
                    <x-validation-errors />
                </x-card>
            </aside>
        </div>
    </form>
</x-page-shell>
@endsection
