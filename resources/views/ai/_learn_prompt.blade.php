{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _learn_prompt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Merken?"-Dialog (Feature 025/084, MVP-404): erscheint nach einer
     Übernahme MIT Bearbeitung. Lernen passiert NUR über die explizite
     Bestätigung hier — nie still. Wegklicken = nichts wird gespeichert. --}}
@if (session('ai_learn') !== null)
    @php $aiLearn = session('ai_learn'); @endphp
    <section class="rounded-box border border-warning/40 bg-warning/5 p-3 space-y-2"
             aria-label="{{ __('ai.learn.title') }}">
        <header class="flex items-center gap-2 text-sm font-semibold">
            <x-icon name="psychology" class="text-warning" />
            {{ __('ai.learn.title') }}
        </header>
        <p class="text-sm text-base-content/70">{{ __('ai.learn.question') }}</p>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 text-sm">
            <div>
                <div class="text-xs text-base-content/60">{{ __('ai.suggestion.original') }}</div>
                <div class="rounded bg-base-200/60 p-2 whitespace-pre-wrap">{{ $aiLearn['source_text'] }}</div>
            </div>
            <div>
                <div class="text-xs text-base-content/60">{{ __('ai.learn.corrected') }}</div>
                <div class="rounded bg-base-100 border border-base-200 p-2 whitespace-pre-wrap">{{ $aiLearn['content'] }}</div>
            </div>
        </div>
        <footer class="flex flex-wrap items-center justify-end gap-2">
            <x-button tone="ghost" size="xs" :href="url()->current()">{{ __('ai.learn.dismiss') }}</x-button>
            <form method="POST" action="{{ route('ai.suggestions.learn') }}" class="inline">
                @csrf
                <input type="hidden" name="entry_type" value="example">
                <input type="hidden" name="source_text" value="{{ $aiLearn['source_text'] }}">
                <input type="hidden" name="content" value="{{ $aiLearn['content'] }}">
                <input type="hidden" name="customer_id" value="{{ $aiLearn['customer_id'] }}">
                <input type="hidden" name="capability" value="{{ $aiLearn['capability'] }}">
                <x-button type="submit" tone="warning" size="xs" icon="psychology">{{ __('ai.learn.confirm') }}</x-button>
            </form>
        </footer>
    </section>
@endif
