{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : accessibility.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-bootstrap')
        <title>{{ $title }} – {{ config('app.name', 'WorkDiary') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-base-200 text-base-content">
        <header class="border-b border-base-300 bg-base-100">
            <div class="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-6 py-4 lg:px-10">
                <a href="{{ route('home') }}" class="font-['Space_Grotesk'] text-lg font-bold tracking-tight text-base-content transition hover:text-primary">
                    {{ config('app.name', 'WorkDiary') }}
                </a>
                <a href="{{ route('home') }}" class="text-sm text-base-content/70 transition hover:text-base-content">
                    {{ __('Zurück zur Startseite') }}
                </a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 py-10 lg:px-10">
            <h1 class="font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ $title }}</h1>

            <section class="mt-8 rounded-box border border-base-300 bg-base-100 p-6 shadow-xs sm:p-8">
                @if ($content !== null)
                    {{-- Betreiber-Klartext: escaped + Zeilenumbrüche erhalten. --}}
                    <div class="whitespace-pre-line text-sm leading-relaxed text-base-content/90">{{ $content }}</div>
                @else
                    {{-- Anlage-3-Gerüst (BFSG) als strukturierter Default (Vollscan 2026-08-23, H18):
                         der Betreiber ergänzt/ersetzt über legal.accessibility. --}}
                    <div class="space-y-6 text-sm leading-relaxed text-base-content/90">
                        <p>{{ __('Diese Erklärung zur Barrierefreiheit gilt für die unter dieser Adresse betriebene Installation von :app.', ['app' => config('app.name', 'WorkDiary')]) }}</p>

                        <div>
                            <h2 class="mb-2 text-base font-semibold text-base-content">{{ __('Stand der Vereinbarkeit mit den Anforderungen') }}</h2>
                            <p>{{ __('Diese Anwendung wird fortlaufend auf Vereinbarkeit mit den Anforderungen der WCAG 2.1 (Stufe AA) geprüft und weiterentwickelt. Sie ist mit den Anforderungen teilweise vereinbar; bekannte Einschränkungen werden sukzessive abgebaut.') }}</p>
                        </div>

                        <div>
                            <h2 class="mb-2 text-base font-semibold text-base-content">{{ __('Nicht barrierefreie Inhalte') }}</h2>
                            <ul class="list-disc space-y-1 pl-5">
                                <li>{{ __('Einzelne Bestandsseiten unterschreiten die geforderten Kontrastverhältnisse; die betroffenen Stellen sind erfasst und werden schrittweise korrigiert.') }}</li>
                                <li>{{ __('Vereinzelte ältere Formularfelder sind noch nicht programmatisch mit ihrer Beschriftung verknüpft.') }}</li>
                                <li>{{ __('Komplexe interaktive Ansichten (z. B. Planungstafeln) sind noch nicht vollständig per Tastatur bedienbar.') }}</li>
                            </ul>
                        </div>

                        <div>
                            <h2 class="mb-2 text-base font-semibold text-base-content">{{ __('Erstellung dieser Erklärung') }}</h2>
                            <p>{{ __('Diese Erklärung beruht auf einer Selbstbewertung mit automatisierten Prüfwerkzeugen (WCAG-2.1-AA-Prüfläufe) und manuellen Stichproben.') }}</p>
                        </div>

                        <div>
                            <h2 class="mb-2 text-base font-semibold text-base-content">{{ __('Feedback und Kontakt') }}</h2>
                            <p>{{ __('Wenn Ihnen Barrieren auffallen oder Sie Informationen in barrierefreier Form benötigen, wenden Sie sich bitte an den Betreiber dieser Installation; die Kontaktdaten finden Sie im Impressum.') }}</p>
                        </div>

                        <div>
                            <h2 class="mb-2 text-base font-semibold text-base-content">{{ __('Durchsetzungsverfahren') }}</h2>
                            <p>{{ __('Erhalten Sie auf eine Rückmeldung zu festgestellten Barrieren keine zufriedenstellende Antwort, können Sie sich an die zuständige Marktüberwachungsbehörde Ihres Landes wenden.') }}</p>
                        </div>
                    </div>
                @endif
            </section>
        </main>

        <footer class="border-t border-base-300 bg-base-100">
            <div class="mx-auto flex w-full max-w-4xl flex-col items-center justify-between gap-4 px-6 py-6 text-sm text-base-content/70 sm:flex-row lg:px-10">
                <span><x-footer-copyright /></span>
                <nav class="flex items-center gap-5">
                    <a href="{{ route('legal.imprint') }}" class="transition hover:text-base-content">{{ __('Impressum') }}</a>
                    <a href="{{ route('legal.privacy') }}" class="transition hover:text-base-content">{{ __('Datenschutz') }}</a>
                    <a href="{{ route('legal.accessibility') }}" class="transition hover:text-base-content">{{ __('Barrierefreiheit') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
