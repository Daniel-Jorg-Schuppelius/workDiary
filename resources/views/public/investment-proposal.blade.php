{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : investment-proposal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Öffentliches Formular für Investitionsvorschläge (MVP-936). Erwartet: $orgName, $token, $sent --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('investment.proposal.public_title', ['org' => $orgName]) }}</title>
@include('partials.font-bootstrap', ['icons' => false])
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="max-w-2xl mx-auto p-4 flex flex-col gap-4">
    <x-card class="flex flex-col gap-1">
        <h1 class="card-title">{{ __('investment.proposal.public_title', ['org' => $orgName]) }}</h1>
        <p class="text-sm opacity-70">{{ __('investment.proposal.intro') }}</p>
    </x-card>
    @if ($sent)
        <div role="status" class="alert alert-success">{{ __('investment.proposal.flash.submitted') }}</div>
    @else
        <x-card>
            <form method="POST" action="{{ route('investment-proposal.public.store', $token) }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                <x-input-field name="submitter_name" :label="__('investment.proposal.field.submitter_name')" :value="old('submitter_name')" required />
                <x-input-field name="submitter_email" type="email" :label="__('investment.proposal.field.submitter_email')" :value="old('submitter_email')" required />
                @include('investments._proposal_fields')
                <div class="md:col-span-2 flex justify-end"><x-button type="submit">{{ __('investment.proposal.submit') }}</x-button></div>
            </form>
        </x-card>
    @endif
</main>
</body>
</html>
