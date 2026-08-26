{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : landing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('privacy.public.layout')

@section('title', __('dsar.landing.title'))

@section('content')
    <section class="card">
        <h2>{{ __('dsar.landing.title') }}</h2>
        <p>{{ __('dsar.landing.intro') }}</p>
        <p class="muted">{{ __('dsar.landing.no_link') }}</p>
    </section>

    <section class="card">
        <h2>{{ __('dsar.landing.rights') }}</h2>
        <ul>
            @foreach (\App\Enums\Privacy\DataSubjectRequestType::cases() as $type)
                <li>{{ $type->label() }}</li>
            @endforeach
        </ul>
        <p class="muted">{{ __('dsar.legal_note') }}</p>
    </section>
@endsection
