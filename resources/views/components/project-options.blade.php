{{--
  Created on   : Fri Aug 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : project-options.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Gemeinsame Options-Liste für Projekt-Dropdowns (in <select> einsetzbar):
  gruppiert nach Kunde (optgroup, „Ohne Kunde" zuletzt) und hängt den
  Endkunden als „ — Name" an (App-Konvention, disambiguiert gleichnamige
  Projekte verschiedener Endkunden). Sub-Projekte tragen immer die
  customer_id des Parents (ProjectObserver), daher keine Vererbungslogik.
  Die Query muss customer_id (und für den Endkunden-Suffix
  foreign_customer_id) mitselektieren; Relationen lädt die Komponente nach.
--}}
@props([
    'projects',            {{-- Collection<Project> mit id, name, customer_id --}}
    'selected' => '',      {{-- Sqid des vorausgewählten Projekts ('' = keins) --}}
    'group' => true,       {{-- aus, wenn der Kontext bereits ein Kunde ist --}}
    'dataParent' => false, {{-- data-parent="<Kunden-Sqid>" je Option (für data-depends-on) --}}
])

@php
    $projects = $projects instanceof \Illuminate\Support\Enumerable ? $projects : collect($projects);
    $first = $projects->first();
    $withCustomer = $first !== null && array_key_exists('customer_id', $first->getAttributes());
    if ($projects instanceof \Illuminate\Database\Eloquent\Collection && $first !== null) {
        if ($withCustomer) {
            $projects->loadMissing('customer:id,name');
        }
        if (array_key_exists('foreign_customer_id', $first->getAttributes())) {
            $projects->loadMissing('foreignCustomer:id,name');
        }
    }

    $optionLabel = fn($p): string => $p->name
        . ($p->relationLoaded('foreignCustomer') && $p->foreignCustomer ? ' — ' . $p->foreignCustomer->name : '');

    $noCustomer = __('Ohne Kunde');
    $groups = $group && $withCustomer
        ? $projects
            ->groupBy(fn($p) => $p->customer?->name ?? $noCustomer)
            ->sortKeysUsing(fn($a, $b): int => (($a === $noCustomer) <=> ($b === $noCustomer)) ?: strcasecmp((string) $a, (string) $b))
        : collect(['' => $projects]);
    if ($groups->keys()->all() === [$noCustomer]) {
        $groups = collect(['' => $projects]); // nur kundenlose Projekte → optgroup wäre Rauschen
    }
@endphp

@foreach ($groups as $groupLabel => $groupProjects)
    @if ($groupLabel !== '')<optgroup label="{{ $groupLabel }}">@endif
    @foreach ($groupProjects as $p)
        <option value="{{ $p->sqid }}"@if ($dataParent) data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}"@endif @selected((string) $selected === $p->sqid)>{{ $optionLabel($p) }}</option>
    @endforeach
    @if ($groupLabel !== '')</optgroup>@endif
@endforeach
