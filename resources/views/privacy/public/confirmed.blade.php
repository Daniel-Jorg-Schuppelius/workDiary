{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : confirmed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('privacy.public.layout')

@section('title', __('dsar.confirmed.title'))

@section('content')
    <div class="alert ok">{{ __('dsar.confirmed.headline') }}</div>

    <section class="card">
        <p>{{ __('dsar.confirmed.text', ['nr' => $requestNumber]) }}</p>
        <p class="muted">{{ __('dsar.confirmed.no_deadline_effect') }}</p>
        <p class="muted">{{ __('dsar.identity_hint') }}</p>
    </section>
@endsection
