{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : datev_check.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Steuerprüfung vor der DATEV-Übergabe aus dem Journal (Feature 207,
  MVP-1661). Erst die Durchsicht, dann der Export: Offene Fehler sperren
  ihn, bis sie berichtigt oder begründet freigegeben sind.
--}}

@extends('layouts.app')

@section('title', __('accounting.datev_check.title'))
@section('nav-title', __('accounting.closing.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.datev_check.subtitle', ['from' => $from->fdate(), 'to' => $to->fdate()])"
                  back-route="finance.accounting.closing.index" :back-label="__('accounting.datev_check.action.back')">
        <x-slot:actions>
            <form method="POST" action="{{ route('finance.accounting.closing.datev') }}" class="inline">
                @csrf
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                <x-icon-btn type="submit" icon="download" tone="primary" size="sm" show-label :disabled="$check->isBlocked()">
                    {{ __('accounting.datev_check.action.export') }}
                </x-icon-btn>
            </form>
        </x-slot:actions>

        @include('finance.datev._tax_check', [
            'check' => $check,
            'canRelease' => $canRelease,
            'releaseAction' => route('finance.accounting.closing.datev-release'),
            'releaseFields' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ])
    </x-index-page>
@endsection
