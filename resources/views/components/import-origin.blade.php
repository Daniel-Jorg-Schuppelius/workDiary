{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import-origin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-import-origin :subject="$model" /> — Herkunftsvermerk übernommener
  Inhalte (MVP-815): aus Obsidian oder OneNote, mit Pfad und Zeitpunkt.
--}}
@props(['subject'])

@php
    $importOrigin = \App\Models\Integration\ExternalReference::query()->withoutGlobalScopes()
        ->where('organization_id', $subject->getAttribute('organization_id'))
        ->forReferenceable($subject)
        ->whereIn('external_type', ['obsidian_note', 'onenote_page'])
        ->first();
@endphp

@if ($importOrigin !== null)
    <p {{ $attributes->class(['flex items-center gap-1 text-xs text-muted']) }}>
        <x-icon name="input" class="text-sm" />
        {{ __('collections.import.origin', ['source' => (string) ($importOrigin->payload['source'] ?? $importOrigin->external_type), 'date' => $importOrigin->synced_at?->fdate() ?? '—']) }}
    </p>
@endif
