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
    'projects',              {{-- Collection<Project> mit id, name, customer_id --}}
    'selected' => '',        {{-- Sqid des vorausgewählten Projekts ('' = keins) --}}
    'group' => true,         {{-- aus, wenn der Kontext bereits ein Kunde ist --}}
    'dataParent' => false,   {{-- data-parent="<Kunden-Sqid>" je Option (für data-depends-on) --}}
    'dataCustomer' => false, {{-- data-customer="<Kunden-Sqid>" je Optgroup (Inbox-Kundenfilter); erzwingt Optgroups inkl. „Intern (ohne Kunde)" --}}
    'dataForeign' => false,  {{-- data-foreign="<Endkunden-Sqid>" je Option (Fremdkunden-Filter) --}}
    'recent' => null,        {{-- Collection<Project>: „Zuletzt verwendet"-Optgroup zuerst; diese Projekte tauchen in den Kundengruppen nicht doppelt auf --}}
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

    $foreignSqid = fn($p): string => $p->relationLoaded('foreignCustomer') && $p->foreignCustomer ? (string) $p->foreignCustomer->sqid : '';

    // „Zuletzt verwendet" zuerst; die Optionen dort tragen den Kunden als
    // „ · Name"-Suffix (gruppenübergreifend, sonst ist der Kontext weg).
    $recent = $recent instanceof \Illuminate\Support\Enumerable ? $recent : collect($recent ?? []);
    if ($recent instanceof \Illuminate\Database\Eloquent\Collection && $recent->first() !== null) {
        if (array_key_exists('customer_id', $recent->first()->getAttributes())) {
            $recent->loadMissing('customer:id,name');
        }
        if (array_key_exists('foreign_customer_id', $recent->first()->getAttributes())) {
            $recent->loadMissing('foreignCustomer:id,name');
        }
    }
    $recentIds = $recent->pluck('id')->all();
    if ($recentIds !== []) {
        $projects = $projects->reject(fn($p): bool => in_array($p->id, $recentIds, true))->values();
    }
    $recentLabel = fn($p): string => $optionLabel($p)
        . ($p->relationLoaded('customer') && $p->customer ? ' · ' . $p->customer->name : '');

    $noCustomer = __('Intern (ohne Kunde)');
    // Gruppen als Liste {label, customer(Sqid|null), projects}: label=null
    // rendert ohne optgroup. Schlüssel ist die customer_id (nicht der Name),
    // damit data-customer je Gruppe eindeutig bleibt.
    $renderGroups = [];
    if ($group && $withCustomer) {
        $byCustomer = [];
        foreach ($projects as $p) {
            $key = $p->customer_id !== null ? 'c' . $p->customer_id : '';
            $byCustomer[$key] ??= [
                'label' => $p->customer?->name ?? $noCustomer,
                'customer' => $p->customer_id !== null ? \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) : '',
                'projects' => [],
            ];
            $byCustomer[$key]['projects'][] = $p;
        }
        usort($byCustomer, fn(array $a, array $b): int => (($a['customer'] === '') <=> ($b['customer'] === '')) ?: strcasecmp($a['label'], $b['label']));
        $renderGroups = array_values($byCustomer);
        // Nur kundenlose Projekte → optgroup wäre Rauschen; mit dataCustomer
        // braucht der clientseitige Filter die Gruppe aber zwingend.
        if (! $dataCustomer && count($renderGroups) === 1 && $renderGroups[0]['customer'] === '') {
            $renderGroups = [['label' => null, 'customer' => null, 'projects' => $projects]];
        }
    } else {
        $renderGroups = [['label' => null, 'customer' => null, 'projects' => $projects]];
    }
@endphp

@if ($recent->isNotEmpty())
    <optgroup label="{{ __('Zuletzt verwendet') }}">
        @foreach ($recent as $p)
            <option value="{{ $p->sqid }}" @selected((string) $selected === $p->sqid)>{{ $recentLabel($p) }}</option>
        @endforeach
    </optgroup>
@endif
@foreach ($renderGroups as $rg)
    @if ($rg['label'] !== null)<optgroup label="{{ $rg['label'] }}"@if ($dataCustomer) data-customer="{{ $rg['customer'] }}"@endif>@endif
    @foreach ($rg['projects'] as $p)
        <option value="{{ $p->sqid }}"@if ($dataParent) data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}"@endif @if ($dataForeign)data-foreign="{{ $foreignSqid($p) }}"@endif @selected((string) $selected === $p->sqid)>{{ $optionLabel($p) }}</option>
    @endforeach
    @if ($rg['label'] !== null)</optgroup>@endif
@endforeach
