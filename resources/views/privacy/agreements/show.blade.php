{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $agreement->title)
@section('nav-title', $agreement->title)
@section('content')
    <x-index-page :subtitle="__('Details, Laufzeit und Anlagen des Auftragsverarbeitungsvertrags.')">
        <x-slot:actions>
            <x-icon-btn icon="handshake" tone="ghost" size="sm"
                        :href="route('dataprotection.processors.show', $agreement->processor)"
                        show-label>{{ $agreement->processor?->name }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="grid md:grid-cols-3 gap-4">
            <x-card class="md:col-span-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Vertragsdaten') }}
                    <x-status-badge tone="ghost" size="sm" class="ml-2">{{ $agreement->status->label() }}</x-status-badge>
                </h2>
                <div class="text-sm space-y-1 mt-2">
                    <p><span class="font-semibold">{{ __('Version') }}:</span> {{ $agreement->version }}</p>
                    <p><span class="font-semibold">{{ __('Gültigkeit') }}:</span> {{ $agreement->valid_from?->format('d.m.Y') ?? '—' }} – {{ $agreement->valid_until?->format('d.m.Y') ?? '—' }}</p>
                    <p><span class="font-semibold">{{ __('Datenkategorien') }}:</span> {{ $agreement->data_categories ?? '—' }}</p>
                    @if ($agreement->document_path)
                        <p><a class="link" href="{{ route('dataprotection.agreements.document', $agreement) }}">{{ __('Vertragsdokument') }}: {{ $agreement->document_name }}</a></p>
                    @endif
                    @if ($agreement->terminated_at)
                        <p class="text-warning">{{ __('Gekündigt am') }} {{ $agreement->terminated_at->format('d.m.Y') }} —
                            {{ __('Datenrückgabe') }}: {{ $agreement->data_return ?? 'offen' }}
                            @if ($agreement->data_return_confirmed_at) ({{ $agreement->data_return_confirmed_at->format('d.m.Y') }}) @endif
                        </p>
                    @endif
                </div>
            </x-card>

            @can('update', $agreement)
                <x-card>
                    <div class="space-y-2">
                        @if ($agreement->status->value === 'draft')
                            <form method="post" action="{{ route('dataprotection.agreements.activate', $agreement) }}">@csrf <x-icon-btn icon="check" tone="outline" size="sm" type="submit" show-label class="w-full">{{ __('Aktivieren') }}</x-icon-btn></form>
                        @endif
                        @if ($agreement->status->value !== 'terminated')
                            <form method="post" action="{{ route('dataprotection.agreements.terminate', $agreement) }}">@csrf <x-icon-btn icon="block" tone="error" size="sm" type="submit" show-label class="btn-outline w-full">{{ __('Kündigen') }}</x-icon-btn></form>
                        @else
                            <form method="post" action="{{ route('dataprotection.agreements.return', $agreement) }}" class="space-y-1">
                                @csrf
                                <select name="mode" class="select select-sm select-bordered w-full">
                                    <option value="returned">{{ __('Daten zurückgegeben') }}</option>
                                    <option value="deleted">{{ __('Daten gelöscht') }}</option>
                                </select>
                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label class="w-full">{{ __('Nachweis bestätigen') }}</x-icon-btn>
                            </form>
                        @endif
                    </div>
                </x-card>
            @endcan
        </div>

        {{-- Verknüpfte Verarbeitungstätigkeiten --}}
        @can('update', $agreement)
            <x-card>
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Verknüpfte Verarbeitungstätigkeiten') }}</h2>
                <form method="post" action="{{ route('dataprotection.agreements.activities', $agreement) }}" class="space-y-2 mt-2">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-1">
                        @foreach ($allActivities as $act)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="activity_ids[]" value="{{ $act->sqid }}" class="checkbox checkbox-sm" @checked(in_array($act->id, $linkedIds, true))>
                                {{ $act->name }}
                            </label>
                        @endforeach
                    </div>
                    <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Verknüpfungen speichern') }}</x-icon-btn>
                </form>
            </x-card>
        @endcan

        {{-- Unterauftragsverarbeiter --}}
        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unterauftragsverarbeiter') }}</h2>
            <ul class="space-y-1 mt-2">
                @forelse ($agreement->subprocessors as $sub)
                    <li class="flex items-center justify-between text-sm rounded-box border border-base-300 px-3 py-2">
                        <span>
                            {{ $sub->name }} @if ($sub->location)<span class="text-base-content/60">— {{ $sub->location }}</span>@endif {{ $sub->third_country ? '('.__('Drittland').')' : '' }}
                            @if ($sub->safeguards)<span class="block text-xs text-base-content/60">{{ __('Garantien') }}: {{ $sub->safeguards }}</span>@endif
                        </span>
                        <span class="inline-flex items-center gap-1">
                            @if ($sub->approved)
                                <x-status-badge tone="success" size="sm">{{ __('freigegeben') }}</x-status-badge>
                            @else
                                @can('update', $agreement)
                                    <form method="post" action="{{ route('dataprotection.agreements.subprocessor.approve', [$agreement, $sub]) }}">@csrf <x-icon-btn icon="check" tone="ghost" size="sm" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn></form>
                                @else
                                    <x-status-badge tone="warning" size="sm">{{ __('offen') }}</x-status-badge>
                                @endcan
                            @endif
                            @can('update', $agreement)
                                <form method="post" action="{{ route('dataprotection.agreements.subprocessor.destroy', [$agreement, $sub]) }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="ghost" size="sm" type="submit" :label="__('Entfernen')" />
                                </form>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-base-content/60">{{ __('Keine Unterauftragsverarbeiter.') }}</li>
                @endforelse
            </ul>
            @can('update', $agreement)
                <div class="pt-2">
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-open-dialog="dlg-subprocessor" show-label>{{ __('Subprozessor hinzufügen') }}</x-icon-btn>
                </div>
                <x-modal :embedded="false" id="dlg-subprocessor" :title="__('Subprozessor hinzufügen')"
                         icon="account_tree" tone="primary"
                         :action="route('dataprotection.agreements.subprocessor.store', $agreement)" method="POST"
                         :submit-label="__('Hinzufügen')">
                    <x-form-group :legend="__('Unterauftragsverarbeiter')" icon="account_tree" tone="primary" cols="2">
                        <x-input-field name="name" :label="__('Name')" required />
                        <x-input-field name="location" :label="__('Sitzland / Ort')" />
                        <x-input-field name="purpose" :label="__('Zweck')" />
                        <x-input-field name="safeguards" :label="__('Garantien (z. B. SCC, Angemessenheitsbeschluss)')" />
                        <label class="label cursor-pointer justify-start gap-3 md:col-span-2">
                            <input type="hidden" name="third_country" value="0">
                            <input type="checkbox" name="third_country" value="1" class="toggle toggle-warning">
                            <span class="label-text">{{ __('Sitz in einem Drittland') }}</span>
                        </label>
                    </x-form-group>
                </x-modal>
            @endcan
        </x-card>

        {{-- Zugeordnete TOM (Vertragsanlage) --}}
        <x-card>
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Technische & organisatorische Maßnahmen (Anlage)') }}</h2>
            <ul class="text-sm space-y-1 mt-2">
                @forelse ($assignedMeasures as $m)
                    <li>• <a class="link" href="{{ route('dataprotection.tom.show', $m) }}">{{ $m->name }}</a></li>
                @empty
                    <li class="text-base-content/60">{{ __('Keine TOM zugeordnet.') }}</li>
                @endforelse
            </ul>
            @can('update', $agreement)
                <form method="post" action="{{ route('dataprotection.agreements.tom', $agreement) }}" class="flex gap-2 pt-2">
                    @csrf
                    <select name="measure_id" class="select select-sm select-bordered flex-1">
                        @foreach ($allMeasures as $m)<option value="{{ $m->sqid }}">{{ $m->name }}</option>@endforeach
                    </select>
                    <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('TOM zuordnen') }}</x-icon-btn>
                </form>
            @endcan
        </x-card>
    </x-index-page>
@endsection
