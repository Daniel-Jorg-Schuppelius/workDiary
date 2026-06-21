<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="dim">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script @cspNonce>
            (function () {
                var savedTheme = localStorage.getItem('workDiaryTheme');
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
                var root = document.documentElement;
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
            })();
        </script>
        @php
            $brandName = isset($branding) && $branding ? $branding->appName() : config('app.name', 'WorkDiary');
            $brandLogo = isset($branding) && $branding ? $branding->logoUrl() : asset('img/logo/workdiary-logo-512.png');
        @endphp
        <title>{{ __('Bestätigung') }} — {{ $brandName }}</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-primary-content text-base-content">
        <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 py-16 lg:px-10">
            <div class="w-full max-w-md" x-data="twoFactorChallenge">
                <div class="mb-8 text-center">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mx-auto mb-4 h-20 w-auto max-w-xs object-contain">
                    @endif
                    <h1 class="mt-2 font-['Space_Grotesk'] text-3xl font-bold tracking-tight text-base-content">{{ __('Zwei-Faktor-Bestätigung') }}</h1>
                    <p class="mt-3 text-sm text-base-content/70" x-show="authMode">{{ __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.') }}</p>
                    <p class="mt-3 text-sm text-base-content/70" x-show="recovery" x-cloak>{{ __('Geben Sie einen Ihrer Recovery-Codes ein.') }}</p>
                </div>

                <div class="rounded-4xl border border-base-300 bg-base-100 p-8 shadow-xs">
                    @if ($errors->any())
                        <div class="mb-4 alert alert-error text-sm">{{ $errors->first() }}</div>
                    @endif
                    @if (session('success'))
                        <div class="mb-4 alert alert-success text-sm">{{ session('success') }}</div>
                    @endif

                    @if ($hasWebauthn ?? false)
                        <div class="mb-5" data-webauthn-block>
                            <p id="passkey-error" class="mb-2 hidden text-sm text-error"></p>
                            <x-button type="button" tone="primary" class="w-full gap-2 rounded-2xl font-['Space_Grotesk'] font-semibold"
                                    data-webauthn-assert
                                    data-options="{{ route('two-factor.login.webauthn.options') }}"
                                    data-target="{{ route('two-factor.login.webauthn') }}"
                                    data-error="passkey-error" icon="key">{{ __('Mit Passkey / Sicherheitsschlüssel') }}</x-button>
                            <div class="my-4 flex items-center gap-3 text-xs text-base-content/50"><span class="h-px flex-1 bg-base-300"></span>{{ __('oder Code eingeben') }}<span class="h-px flex-1 bg-base-300"></span></div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.login.attempt') }}" class="space-y-5">
                        @csrf
                        <div x-show="authMode">
                            <label for="code" class="mb-2 block text-sm font-medium text-base-content">{{ __('Code') }}</label>
                            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                                   placeholder="000000"
                                   class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-center text-2xl tracking-[0.5em] text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                        </div>
                        <div x-show="recovery" x-cloak>
                            <label for="recovery_code" class="mb-2 block text-sm font-medium text-base-content">{{ __('Recovery-Code') }}</label>
                            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                                   class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                        </div>

                        <x-button type="submit" tone="primary" class="w-full rounded-2xl font-['Space_Grotesk'] font-semibold">
                            {{ __('Bestätigen') }}
                        </x-button>
                    </form>
                    <button type="button" class="mt-4 w-full text-center text-sm text-primary transition hover:opacity-80"
                            x-on:click="toggle()">
                        <span x-show="authMode">{{ __('Stattdessen Recovery-Code verwenden') }}</span>
                        <span x-show="recovery" x-cloak>{{ __('Zurück zum Authenticator-Code') }}</span>
                    </button>

                    @if ($hasEmail ?? false)
                        <div class="mt-6 border-t border-base-300 pt-5">
                            <p class="mb-3 text-sm text-base-content/70">{{ __('Oder Code per E-Mail erhalten:') }}</p>
                            <form method="POST" action="{{ route('two-factor.login.email') }}" class="mb-3">
                                @csrf
                                <x-button type="submit" tone="outline" size="sm" class="w-full rounded-2xl">{{ __('Code per E-Mail senden') }}</x-button>
                            </form>
                            <form method="POST" action="{{ route('two-factor.login.attempt') }}" class="space-y-3">
                                @csrf
                                <input name="email_code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('E-Mail-Code') }}"
                                       class="w-full rounded-2xl border border-base-content/20 bg-base-200/80 px-4 py-3 text-center text-xl tracking-[0.4em] text-base-content transition focus:border-primary/60 focus:outline-none focus:ring-2 focus:ring-primary/25">
                                <x-button type="submit" tone="primary" size="sm" class="w-full rounded-2xl">{{ __('Mit E-Mail-Code bestätigen') }}</x-button>
                            </form>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
                    @csrf
                    <button type="submit" class="text-sm text-base-content/70 transition hover:opacity-80">← {{ __('Abbrechen') }}</button>
                </form>
            </div>
        </div>
        @include('partials.webauthn-script')
    </body>
</html>
