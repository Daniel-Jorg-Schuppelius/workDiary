{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-download.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abrufseite (Feature 157): Ergebnis einer vollständig unterzeichneten
     Fassung über den getrennten, befristeten Abruflink. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>{{ __('contract-signing.public.download_title', ['kind' => $contract?->kind->label()]) }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4">
    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <p class="text-xs uppercase text-muted">{{ $contract?->organization?->name }}</p>
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ $contract?->kind->label() }} — {{ $contract?->title }}</h1>
        <div class="mt-1 text-sm text-base-content/70">
            {{ $contract?->number }} · {{ $revision->label() }} ·
            <x-status-badge size="sm" :tone="$revision->status->tone()" :label="$revision->status->label()" />
            @if ($revision->completed_at) · {{ __('contract-signing.revision.completed_at', ['at' => $revision->completed_at->fdatetime()]) }} @endif
        </div>
        <div class="mt-2 text-xs text-muted">{{ __('contract-signing.link.expires', ['at' => $link->expires_at->fdatetime()]) }}</div>
    </div>

    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <h2 class="mb-2 text-sm font-semibold">{{ __('contract-signing.public.download_heading') }}</h2>
        <div class="flex flex-wrap gap-2">
            <x-button tone="primary" size="sm" icon="folder_zip" :href="route('agreements.public-download.package', ['token' => $token])">
                <span>{{ __('contract-signing.action.package') }}</span>
            </x-button>
            <x-button tone="outline" size="sm" icon="verified" :href="route('agreements.public-download.certificate', ['token' => $token])">
                <span>{{ __('contract-signing.action.certificate') }}</span>
            </x-button>
        </div>
        <h3 class="mb-1 mt-4 text-xs font-semibold uppercase text-muted">{{ __('contract-signing.revision.files') }}</h3>
        <ul class="divide-y divide-base-300">
            @foreach ($items as $item)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span><span class="badge badge-ghost badge-xs">{{ $item->roleLabel() }}</span> {{ $item->original_name }}</span>
                    <x-button tone="outline" size="xs" icon="download" :href="route('agreements.public-download.file', ['token' => $token, 'item' => $item->sort])">
                        <span>{{ __('contract-signing.action.download_file') }}</span>
                    </x-button>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mb-4 rounded-box bg-base-100 p-4 shadow text-sm">
        <h2 class="mb-2 text-sm font-semibold">{{ __('contract-signing.certificate.signatures_heading') }}</h2>
        <ul class="space-y-1">
            @foreach ($revision->requests as $req)
                @php $ev = $req->fulfillingEvidence(); @endphp
                <li>
                    <span class="font-medium">{{ $req->party->label() }}:</span>
                    {{ $ev?->signer_name ?? $req->signer_name }}
                    @if ($ev) — {{ $ev->method->label() }}, {{ $ev->signed_at->fdatetime() }} @elseif (! $req->required) — {{ __('contract-signing.request_status.waived') }} @endif
                </li>
            @endforeach
        </ul>
    </div>

    <p class="text-xs text-muted">{{ __('contract-signing.public.legal_note') }}</p>
</main>
</body>
</html>
