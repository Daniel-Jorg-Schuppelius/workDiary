{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Suchseite (Feature 153): Tätigkeitsrecherche mit Übersicht je
     Kunde/Endkunde und Trefferliste, darunter die Stammdaten-Gruppen der
     globalen Suche. `?domain=` fokussiert eine Stammdaten-Gruppe. --}}

@extends('layouts.app')

@section('title', __('search.title'))
@section('nav-title', __('search.title'))

@section('content')
@php
    $parameters = $criteria->toParameters();
@endphp
<x-index-page :subtitle="__('search.subtitle')">
    @if ($aiUsable && $aiAnswer === null && $result !== null && $result->searched && $result->hits->total() > 0)
        <x-slot:actions>
            <x-action-form :action="route('ai.assist.search-answer')">
                @foreach ($parameters as $name => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <x-icon-btn icon="auto_awesome" tone="info" size="sm" type="submit" show-label>{{ __('search.ai.action') }}</x-icon-btn>
            </x-action-form>
        </x-slot:actions>
    @endif

    <x-filter-bar :action="route('search.index')" :reset="route('search.index')">
        @if ($project !== null)
            <input type="hidden" name="project" value="{{ $project->sqid }}">
        @endif
        <input type="search" name="q" value="{{ $criteria->query }}" maxlength="{{ (int) config('search.max_query_length', 200) }}"
               placeholder="{{ __('search.placeholder') }}" @if ($focus) autofocus @endif
               class="input input-sm input-bordered w-72 shrink-0" aria-label="{{ __('search.field.query') }}">
        <select name="type" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('search.field.type') }}">
            <option value="">{{ __('search.field.all_types') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(in_array($type, $criteria->types, true))>{{ $type->label() }}</option>
            @endforeach
        </select>
        <x-date-range class="w-80 shrink-0" :label="false" from-name="from" to-name="to"
                      from-id="search-from" to-id="search-to" :from="$criteria->from ?? ''" :to="$criteria->to ?? ''" />
        @if ($selectablePersons !== null)
            <select name="person" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('search.field.person') }}">
                <option value="">{{ __('search.field.all_persons') }}</option>
                @foreach ($selectablePersons as $person)
                    <option value="{{ $person->sqid }}" @selected($criteria->personId === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="customer" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('search.field.customer') }}">
            <option value="">{{ __('search.field.all_customers') }}</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}" @selected($criteria->customerId === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        @if ($foreignCustomers->isNotEmpty())
            <select name="foreign_customer" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('search.field.foreign_customer') }}">
                <option value="">{{ __('search.field.all_foreign_customers') }}</option>
                @foreach ($foreignCustomers as $foreignCustomer)
                    <option value="{{ $foreignCustomer->sqid }}" @selected($criteria->foreignCustomerId === $foreignCustomer->id)>{{ $foreignCustomer->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="sort" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('search.field.sort') }}">
            <option value="relevance" @selected($criteria->sort === 'relevance')>{{ __('search.field.sort_relevance') }}</option>
            <option value="date" @selected($criteria->sort === 'date')>{{ __('search.field.sort_date') }}</option>
        </select>
        <x-filter-toggle name="similar" :label="__('search.field.similar')" :checked="$criteria->similar" />
    </x-filter-bar>

    @if ($project !== null)
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-outline gap-1">
                <x-icon name="folder_special" class="text-sm" />
                {{ __('search.filter.project', ['name' => $project->name]) }}
                <a href="{{ route('search.index', $criteria->toParameters(['project' => null])) }}"
                   class="inline-flex" aria-label="{{ __('search.filter.remove') }}">
                    <x-icon name="close" class="text-sm" />
                </a>
            </span>
        </div>
    @endif

    @if ($result !== null)
        @if (! $result->searched)
            <x-empty-state framed icon="manage_search" :title="__('search.empty.start')" :message="__('search.empty.start_hint')" />
        @else
            @include('search._notices', ['parsed' => $result->parsed])

            @if ($aiAnswer !== null)
                @include('ai._insight', ['suggestion' => $aiAnswer, 'showOriginal' => true])
            @endif

            @if ($result->hits->total() > 0)
                @include('search._overview')
            @endif

            @include('search._hits')
        @endif
    @else
        <a class="link link-hover text-sm" href="{{ route('search.index', $parameters) }}">{{ __('search.entities.back') }}</a>
    @endif

    @include('search._entities')
</x-index-page>
@endsection
