{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : activity-search-box.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-activity-search-box> — Einstieg in die Tätigkeitsrecherche (Feature 153)
    mit festem Bezug: Kunde (inkl. seiner Endkunden), Endkunde oder Projekt.
    Ein leeres Suchfeld zeigt die neuesten Tätigkeiten des Bezugs.
--}}
@props([
    'customer' => null,
    'foreignCustomer' => null,
    'project' => null,
    'placeholder' => null,
])

<form method="GET" action="{{ route('search.index') }}" role="search"
      {{ $attributes->class(['flex w-full flex-wrap items-center gap-2']) }}>
    @if ($customer !== null)
        <input type="hidden" name="customer" value="{{ $customer->sqid }}">
    @endif
    @if ($foreignCustomer !== null)
        <input type="hidden" name="foreign_customer" value="{{ $foreignCustomer->sqid }}">
    @endif
    @if ($project !== null)
        <input type="hidden" name="project" value="{{ $project->sqid }}">
    @endif
    <label class="input input-sm input-bordered flex min-w-48 flex-1 items-center gap-2">
        <x-icon name="manage_search" class="text-muted" />
        <input type="search" name="q" maxlength="{{ (int) config('search.max_query_length', 200) }}"
               class="min-w-0 grow" autocomplete="off"
               placeholder="{{ $placeholder ?? __('search.box.placeholder') }}"
               aria-label="{{ __('search.box.label') }}">
    </label>
    <x-icon-btn icon="search" type="submit" size="sm" tone="primary" show-label>{{ __('search.box.submit') }}</x-icon-btn>
</form>
