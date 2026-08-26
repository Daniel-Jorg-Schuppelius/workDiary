{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : portal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('privacy.public.layout')

@section('title', __('dsar.form.title'))

@section('content')
    @if ($portal->intro_text)
        <section class="card">
            <p>{{ $portal->intro_text }}</p>
        </section>
    @endif

    <section class="card">
        <h2>{{ __('dsar.form.what') }}</h2>
        <p>{{ __('dsar.form.what_text') }}</p>
        <p class="muted">{{ __('dsar.privacy_notice') }}</p>
    </section>

    @if ($errors->any())
        <div class="alert err">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="card" method="post" action="{{ route('dsar.store', ['portal' => $portal->public_slug]) }}" enctype="multipart/form-data">
        @csrf

        <label for="type">{{ __('dsar.field.type') }}</label>
        <select id="type" name="type" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>

        <label for="full_name">{{ __('dsar.field.full_name') }}</label>
        <input id="full_name" name="full_name" type="text" maxlength="200" value="{{ old('full_name') }}" required>

        <label for="email">{{ __('dsar.field.email') }}</label>
        <input id="email" name="email" type="email" maxlength="190" value="{{ old('email') }}" required>

        <label for="reference">{{ __('dsar.field.reference') }}</label>
        <input id="reference" name="reference" type="text" maxlength="200" value="{{ old('reference') }}">

        <label for="message">{{ __('dsar.field.message') }}</label>
        <textarea id="message" name="message" maxlength="20000" required>{{ old('message') }}</textarea>

        @if ($portal->allow_attachments)
            <label for="attachments">{{ __('dsar.field.attachments') }}</label>
            <input id="attachments" name="attachments[]" type="file" multiple>
            <p class="muted">{{ __('dsar.field.attachments_hint', ['max' => 5, 'size' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
        @endif

        {{-- Honeypot: fuer Menschen unsichtbar, Bots fuellen es aus. --}}
        <div class="hp" aria-hidden="true">
            <label for="company_website">{{ __('dsar.field.honeypot') }}</label>
            <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <label class="check" for="privacy_ack">
            <input id="privacy_ack" type="checkbox" name="privacy_ack" value="1" required>
            <span>{{ __('dsar.field.privacy_ack') }}</span>
        </label>

        <button class="btn" type="submit">{{ __('dsar.form.submit') }}</button>
    </form>

    <section class="card">
        <p class="muted">{{ __('dsar.identity_hint') }}</p>
        <p class="muted">{{ __('dsar.legal_note') }}</p>
    </section>
@endsection
