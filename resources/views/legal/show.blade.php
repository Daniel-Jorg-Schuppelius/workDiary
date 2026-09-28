{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.public')

@section('title', $title)

@section('content')
    <div class="mx-auto w-full max-w-4xl">
        <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ $title }}</h1>

        <section class="mt-8 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs sm:p-8">
            @if ($content !== null)
                {{-- Betreiber-Klartext: escaped + Zeilenumbrüche erhalten. --}}
                <div class="whitespace-pre-line text-sm leading-relaxed text-base-content/90">{{ $content }}</div>
            @else
                <p class="text-sm text-base-content/70">
                    {{ __('Der Betreiber dieser Installation hat diesen Rechtstext noch nicht hinterlegt.') }}
                </p>
                <p class="mt-3 text-sm text-muted">
                    {{ __('Hinterlegt wird der Inhalt in den Systemeinstellungen (Administration → Einstellungen, Schlüssel :key).', ['key' => $settingKey]) }}
                    {{ __('Gibt es die Seite schon an anderer Stelle, leitet der Schlüssel :key dorthin weiter.', ['key' => $urlKey]) }}
                </p>
            @endif
        </section>
    </div>
@endsection
