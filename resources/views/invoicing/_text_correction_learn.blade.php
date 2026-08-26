{{--
  Created on   : Mon Aug 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _text_correction_learn.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Merken?"-Dialog des Schreibfehler-Wörterbuchs: erscheint nach einer
     manuellen Positionstext-Korrektur mit erkannten 1:1-Wortersetzungen.
     Aufnahme NUR über die Bestätigung hier — nie still. Wegklicken =
     nichts wird gespeichert. --}}
@if (session('text_correction_learn') !== null)
    @php $txcLearn = session('text_correction_learn'); @endphp
    <section class="rounded-box border border-warning/40 bg-warning/5 p-3 space-y-2"
             aria-label="{{ __('textcorrections.learn.title') }}">
        <header class="flex items-center gap-2 text-sm font-semibold">
            <x-icon name="spellcheck" class="text-warning" />
            {{ __('textcorrections.learn.title') }}
        </header>
        <p class="text-sm text-base-content/70">{{ __('textcorrections.learn.question') }}</p>
        <div class="space-y-2">
            @foreach (($txcLearn['pairs'] ?? []) as $pair)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded bg-base-100 border border-base-200 p-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-base-200/60 px-2 py-0.5 font-mono">{{ $pair['wrong'] }}</span>
                        <x-icon name="arrow_forward" class="text-muted" />
                        <span class="rounded bg-base-200/60 px-2 py-0.5 font-mono">{{ $pair['correct'] }}</span>
                    </div>
                    <form method="POST" action="{{ route('text-corrections.learn') }}" class="inline">
                        @csrf
                        <input type="hidden" name="wrong" value="{{ $pair['wrong'] }}">
                        <input type="hidden" name="correct" value="{{ $pair['correct'] }}">
                        <x-button type="submit" tone="warning" size="xs" icon="spellcheck">{{ __('textcorrections.learn.confirm') }}</x-button>
                    </form>
                </div>
            @endforeach
        </div>
        <footer class="flex items-center justify-end">
            <x-button tone="ghost" size="xs" :href="url()->current()">{{ __('textcorrections.learn.dismiss') }}</x-button>
        </footer>
    </section>
@endif
