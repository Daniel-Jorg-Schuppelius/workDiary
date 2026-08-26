{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : receipt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('privacy.public.layout')

@section('title', __('dsar.receipt.title'))

@section('content')
    <div class="alert ok">{{ __('dsar.receipt.headline') }}</div>

    <section class="card">
        @if ($requestNumber)
            <p><strong>{{ __('dsar.receipt.number', ['nr' => $requestNumber]) }}</strong></p>
        @endif
        <p>{{ __('dsar.receipt.mail_sent') }}</p>
        <p class="muted">{{ __('dsar.identity_hint') }}</p>
    </section>

    <section class="card">
        <p><a href="{{ route('dsar.portal', ['portal' => $portal->public_slug]) }}">{{ __('dsar.receipt.back') }}</a></p>
    </section>
@endsection
