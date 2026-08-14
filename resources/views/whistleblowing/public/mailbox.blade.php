{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : mailbox.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('whistleblowing.public.layout')

@section('title', __('Postfach'))

@section('content')
    @if (session('success'))
        <section class="wb-card wb-success"><p>{{ session('success') }}</p></section>
    @endif

    <section class="wb-card">
        <h2>{{ __('Stand Ihrer Meldung') }}</h2>
        <p><strong>{{ __('Fallnummer') }}:</strong> <code>{{ $case->case_number }}</code></p>
        <p><strong>{{ __('Status') }}:</strong> {{ __('whistleblowing.reporter_status.' . $reporterStatus) }}</p>

        <form method="post" action="{{ route('whistleblowing.mailbox.logout') }}">
            @csrf
            <button type="submit">{{ __('Abmelden') }}</button>
        </form>
    </section>

    <section class="wb-card">
        <h2>{{ __('Nachrichten') }}</h2>
        @forelse ($messages as $m)
            <div class="border-l-2 pl-2">
                <span class="badge">{{ $m->author_type->value === 'reporter' ? __('Sie') : __('Meldestelle') }}</span>
                <p class="whitespace-pre-line">{{ $m->body_ciphertext }}</p>
            </div>
        @empty
            <p>{{ __('Noch keine Nachrichten.') }}</p>
        @endforelse

        <form method="post" action="{{ route('whistleblowing.mailbox.message.store') }}">
            @csrf
            <label for="body">{{ __('Antwort an die Meldestelle') }}</label>
            <textarea id="body" name="body" rows="5" maxlength="20000" required></textarea>
            <button type="submit">{{ __('Senden') }}</button>
        </form>
    </section>

    <section class="wb-card">
        <h2>{{ __('Ihre Anhänge') }}</h2>
        <ul>
            @forelse ($attachments as $a)
                <li>{{ $a->original_name_ciphertext }}</li>
            @empty
                <li>{{ __('Keine Anhänge.') }}</li>
            @endforelse
        </ul>

        <form method="post" action="{{ route('whistleblowing.mailbox.attachment.store') }}" enctype="multipart/form-data">
            @csrf
            <label for="file">{{ __('Datei hinzufügen') }}</label>
            <input id="file" name="file" type="file" required>
            <p class="wb-hint">{{ __('Achtung: Dokumente können Metadaten enthalten, die Sie identifizieren.') }}</p>
            <button type="submit">{{ __('Hochladen') }}</button>
        </form>
    </section>
@endsection
