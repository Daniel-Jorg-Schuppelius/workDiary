{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : origin-badge.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-origin-badge :subject="$model" /> — Herkunft eines aus einem Plugin
  übernommenen Datensatzes (Feature 181): Pluginname, Link ins Fremdsystem
  nur mit http(s)-Schema (RecordOrigins prüft).
--}}
@props(['subject'])

@php($origins = app(\App\Services\Integration\RecordOrigins::class)->for($subject))

@foreach ($origins as $origin)
    <x-status-badge tone="ghost" {{ $attributes->class(['gap-1']) }}>
        <x-icon name="input" class="text-sm" />
        @if ($origin['url'] !== null)
            <a href="{{ $origin['url'] }}" target="_blank" rel="noopener noreferrer" class="link link-hover"
               title="{{ __('integration.origin.open', ['source' => $origin['label']]) }}">{{ __('integration.origin.from', ['source' => $origin['label']]) }}</a>
        @else
            {{ __('integration.origin.from', ['source' => $origin['label']]) }}
        @endif
    </x-status-badge>
@endforeach
