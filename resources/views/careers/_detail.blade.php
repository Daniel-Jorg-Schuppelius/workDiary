{{-- MVP-437: geteilte Stellendetail- + Bewerbungsansicht (Detail & Embed).
     Nur öffentliche Inhaltsfelder ($content); nie interne Requisition-Daten. --}}
<div class="card">
    <h2>{{ $content['title'] }}</h2>
    @if($content['location'])
        <p class="meta">{{ $content['location'] }}</p>
    @endif
    @if($content['summary'])
        <p>{{ $content['summary'] }}</p>
    @endif

    <div class="section">
        @if($content['description'])
            <h3>{{ __('Beschreibung') }}</h3><p>{{ $content['description'] }}</p>
        @endif
        @if($content['tasks'])
            <h3>{{ __('Aufgaben') }}</h3><p>{{ $content['tasks'] }}</p>
        @endif
        @if($content['requirements'])
            <h3>{{ __('Anforderungen') }}</h3><p>{{ $content['requirements'] }}</p>
        @endif
        @if($content['benefits'])
            <h3>{{ __('Wir bieten') }}</h3><p>{{ $content['benefits'] }}</p>
        @endif
        @if($content['deadline'])
            <p class="muted">{{ __('Bewerbungsschluss') }}: {{ \Illuminate\Support\Carbon::parse($content['deadline'])->format('d.m.Y') }}</p>
        @endif
    </div>
</div>

<div class="card">
    <h2>{{ __('Jetzt bewerben') }}</h2>
    @unless($applyable)
        <p class="muted">{{ __('Diese Stelle nimmt derzeit keine Bewerbungen entgegen.') }}</p>
    @else
        <form method="post"
              action="{{ route('careers.apply', ['org' => $organization->slug, 'posting' => $posting->public_slug]) }}"
              enctype="multipart/form-data">
            <input type="hidden" name="form_state" value="{{ $formToken }}">
            {{-- Honeypot: bleibt leer; für Menschen unsichtbar. --}}
            <div class="hp" aria-hidden="true">
                <label>{{ __('Website (bitte leer lassen)') }}<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
            </div>

            <label for="cn">{{ __('Name') }} *</label>
            <input id="cn" type="text" name="candidate_name" required maxlength="200" value="{{ old('candidate_name') }}">

            <label for="ce">{{ __('E-Mail') }} *</label>
            <input id="ce" type="email" name="email" required maxlength="190" value="{{ old('email') }}">

            <label for="cp">{{ __('Telefon') }}</label>
            <input id="cp" type="tel" name="phone" maxlength="60" value="{{ old('phone') }}">

            <label for="cm">{{ __('Nachricht') }}</label>
            <textarea id="cm" name="message" maxlength="5000">{{ old('message') }}</textarea>

            <label for="cd">{{ __('Bewerbungsunterlagen') }} ({{ __('max. :n Dateien, PDF/DOCX/JPG/PNG', ['n' => 5]) }})</label>
            <input id="cd" type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">

            <label class="check">
                <input type="checkbox" name="privacy_ack" value="1" required>
                <span>
                    {{ __('Ich habe den Datenschutzhinweis zur Bewerbung zur Kenntnis genommen.') }}
                    @if($privacyNoticeUrl)
                        <a href="{{ $privacyNoticeUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Datenschutzhinweis') }}</a>
                    @endif
                </span>
            </label>

            @if($privacyNoticeText && ! $privacyNoticeUrl)
                <p class="muted">{{ \Illuminate\Support\Str::limit($privacyNoticeText, 600) }}</p>
            @endif

            <p><button type="submit" class="btn">{{ __('Bewerbung absenden') }}</button></p>
        </form>
    @endunless
</div>
