{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Leads'))
@section('nav-title', __('Leads'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\Sales\{LeadSource, LeadStatus};
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $leads */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Interessenten vor dem Kundenstatus: erfassen, qualifizieren, konvertieren — ohne Excel-Nebenliste.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="person_add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('leads.create')" show-label>{{ __('Lead anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('leads.index')" :reset="route('leads.index')">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-56 shrink-0"
               placeholder="{{ __('Firma, Person, E-Mail') }}" aria-label="{{ __('Suche') }}">
        <select name="status" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (LeadStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                    {{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})
                </option>
            @endforeach
        </select>
        <select name="source" class="select select-sm select-bordered w-48 shrink-0" aria-label="{{ __('Quelle') }}">
            <option value="">{{ __('Alle Quellen') }}</option>
            @foreach (LeadSource::cases() as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($leads->isEmpty())
        <x-empty-state framed icon="person_search"
                       :title="__('Keine Leads im gewählten Ausschnitt.')"
                       :message="__('Interessenten aus Empfehlung, Web oder Messe landen hier — bis sie Kunde werden oder fristgerecht verschwinden.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Lead') }}</th>
                    <th>{{ __('Kontakt') }}</th>
                    <th>{{ __('Quelle') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th>{{ __('Letzter Kontakt') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($leads as $lead)
                <tr class="hover">
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('leads.show', $lead) }}">{{ $lead->displayName() }}</a>
                        @if ($lead->anonymized_at)
                            <span class="badge badge-ghost badge-xs align-middle">{{ __('anonymisiert') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-base-content/70">
                        {{ $lead->contact_name }}
                        @if ($lead->email)<span class="block text-xs">{{ $lead->email }}</span>@endif
                    </td>
                    <td class="text-sm">{{ $lead->source->label() }}</td>
                    <td><x-status-badge :tone="$lead->status->tone()" size="sm">{{ $lead->status->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ $lead->responsible?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap text-sm">{{ $lead->last_contact_at?->format('d.m.Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$leads" standing />
    @endif
</x-index-page>
@endsection
