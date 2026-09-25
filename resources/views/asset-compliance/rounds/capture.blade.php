{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : capture.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Schnellerfassung einer Prüfung in der Runde (MVP-899); ausführlich mit Messwerten und Zertifikat im Prüfkalender. --}}
@extends('layouts.app')

@section('title', __('inspection_round.capture'))
@section('nav-title', __('inspection_round.capture'))

@section('content')
<x-page-shell gap="3">
    <x-slot:toolbar>
        <x-page-toolbar :title="$item->asset?->name" :subtitle="$item->assignment?->profile?->name">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('asset-compliance.rounds.show', $round)" :label="$round->name" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($item->assignment?->profile?->requires_certificate)
        <div role="status" class="alert alert-warning text-sm">{{ __('inspection_round.certificate_hint') }}</div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('asset-compliance.rounds.capture.store', [$round, $item]) }}" class="space-y-3">
            @csrf
            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ __('inspection_round.result') }}</legend>
                @foreach (\App\Enums\AssetCompliance\AssetInspectionResult::cases() as $result)
                    <label class="flex items-center gap-2 py-1 text-base">
                        <input type="radio" name="result" value="{{ $result->value }}" class="radio" required @checked(old('result', 'passed') === $result->value)>
                        {{ $result->label() }}
                    </label>
                @endforeach
            </fieldset>
            <x-textarea-field name="note" :label="__('inspection_round.note')" :value="old('note')" rows="2" />
            <x-input-field name="signature_name" :label="__('inspection_round.signature_name')" :value="old('signature_name', auth()->user()?->name)" maxlength="255" />
            <x-button type="submit" tone="primary" class="w-full">{{ __('inspection_round.capture_submit') }}</x-button>
        </form>
    </x-card>
</x-page-shell>
@endsection
