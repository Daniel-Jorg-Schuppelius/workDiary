@extends('whistleblowing.public.layout')

@section('title', __('Meldung abgeben'))

@section('content')
    @if ($portal->intro_text)
        <section class="wb-card">
            <p>{{ $portal->intro_text }}</p>
        </section>
    @endif

    <section class="wb-card">
        <h2>{{ __('Wofür ist dieser Kanal?') }}</h2>
        <p>{{ __('Hinweise auf Rechtsverstöße, Korruption, Betrug und andere erhebliche Compliance-Verstöße. Nicht jeder Konflikt am Arbeitsplatz fällt darunter.') }}</p>
        @if (is_array($portal->external_channels) && count($portal->external_channels) > 0)
            <h3>{{ __('Externe Meldewege') }}</h3>
            <ul>
                @foreach ($portal->external_channels as $channel)
                    <li>{{ is_array($channel) ? ($channel['label'] ?? '') . ' ' . ($channel['url'] ?? '') : $channel }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($errors->any())
        <section class="wb-card wb-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form class="wb-card" method="post" action="{{ route('whistleblowing.report.store', ['portal' => $portal->public_slug]) }}" enctype="multipart/form-data">
        @csrf

        <fieldset>
            <legend>{{ __('Art der Meldung') }}</legend>
            @if ($portal->allow_anonymous)
                <label><input type="radio" name="reporter_mode" value="anonymous" checked> {{ __('Anonym') }}</label>
            @endif
            @if ($portal->allow_confidential)
                <label><input type="radio" name="reporter_mode" value="confidential"> {{ __('Vertraulich (freiwillige Kontaktdaten)') }}</label>
            @endif
        </fieldset>

        <label for="category">{{ __('Kategorie') }}</label>
        <select id="category" name="category" required>
            @foreach (\App\Enums\Whistleblowing\CaseCategory::cases() as $cat)
                <option value="{{ $cat->value }}">{{ __('whistleblowing.category.' . $cat->value) }}</option>
            @endforeach
        </select>

        <label for="subject">{{ __('Betreff') }}</label>
        <input id="subject" name="subject" type="text" maxlength="200" required>

        <label for="description">{{ __('Beschreibung des Sachverhalts') }}</label>
        <textarea id="description" name="description" rows="8" maxlength="20000" required></textarea>

        <label for="occurred_from">{{ __('Zeitraum von (optional)') }}</label>
        <input id="occurred_from" name="occurred_from" type="date">
        <label for="occurred_to">{{ __('Zeitraum bis (optional)') }}</label>
        <input id="occurred_to" name="occurred_to" type="date">

        <label for="contact">{{ __('Kontaktdaten (nur bei vertraulicher Meldung, freiwillig)') }}</label>
        <input id="contact" name="contact" type="text" maxlength="500">

        <label for="attachments">{{ __('Anhänge (optional)') }}</label>
        <input id="attachments" name="attachments[]" type="file" multiple>
        <p class="wb-hint">{{ __('Achtung: Dokumente können Namen, Benutzerkonten und Metadaten enthalten, die Sie identifizieren.') }}</p>

        <label class="wb-consent"><input type="checkbox" name="consent" value="1" required> {{ __('Ich mache die Angaben nach bestem Wissen und Gewissen.') }}</label>

        <button type="submit">{{ __('Meldung absenden') }}</button>
    </form>
@endsection
