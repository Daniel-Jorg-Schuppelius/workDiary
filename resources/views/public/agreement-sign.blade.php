{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-sign.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Öffentliche Unterzeichnungsseite (Feature 157): genau eine Fassung und
     Partei, ohne Kontozwang, ohne externe Ressourcen. Beide Wege — im
     Browser unterzeichnen oder unterschriebenes PDF hochladen. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>{{ __('contract-signing.public.title', ['kind' => $contract?->kind->label()]) }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/signature.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4">
    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <p class="text-xs uppercase text-muted">{{ $contract?->organization?->name }}</p>
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ $contract?->kind->label() }} — {{ $contract?->title }}</h1>
        <div class="mt-1 text-sm text-base-content/70">
            {{ $contract?->number }} · {{ $revision->label() }} ·
            {{ __('contract-signing.public.for_party', ['party' => $signatureRequest->party->label(), 'name' => $signatureRequest->signer_name]) }}
        </div>
        <div class="mt-2 text-xs text-muted">
            {{ __('contract-signing.party.customer') }}: {{ $contract?->customer?->name }} ·
            {{ __('contract-signing.party.organization') }}: {{ $contract?->organization?->name }}
            @if ($revision->controller_party) · {{ __('contract-signing.revision.controller', ['party' => $revision->controller_party->label()]) }} @endif
            · {{ __('contract-signing.link.expires', ['at' => $link->expires_at->fdatetime()]) }}
        </div>
    </div>

    <x-validation-errors class="mb-4" />

    <div class="mb-4 rounded-box bg-base-100 p-4 shadow">
        <h2 class="mb-2 text-sm font-semibold">{{ __('contract-signing.revision.files') }}</h2>
        <p class="mb-2 text-xs text-muted">{{ __('contract-signing.public.files_intro') }}</p>
        <ul class="divide-y divide-base-300">
            @foreach ($items as $item)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span><span class="badge badge-ghost badge-xs">{{ $item->roleLabel() }}</span> {{ $item->original_name }}
                        <span class="block font-mono text-[11px] text-muted">{{ $item->sha256 }}</span></span>
                    <x-button tone="outline" size="xs" icon="download" :href="route('agreements.public-sign.file', ['token' => $token, 'item' => $item->sort])">
                        <span>{{ __('contract-signing.action.download_file') }}</span>
                    </x-button>
                </li>
            @endforeach
        </ul>
    </div>

    @if (! $signatureRequest->status->acceptsSubmission())
        <div class="alert alert-info mb-4">
            <span>
                @if ($signatureRequest->status === \App\Enums\Contract\SignatureRequestStatus::EvidenceReceived)
                    {{ __('contract-signing.public.evidence_pending') }}
                @else
                    {{ __('contract-signing.public.already_fulfilled') }}
                @endif
            </span>
        </div>
    @else
        <form method="POST" action="{{ route('agreements.public-sign.submit', ['token' => $token]) }}" class="mb-4 rounded-box bg-base-100 p-4 shadow" autocomplete="off">
            @csrf
            <h2 class="mb-1 text-sm font-semibold">{{ __('contract-signing.public.sign_heading') }}</h2>
            <p class="mb-3 text-xs text-muted">{{ __('contract-signing.public.sign_intro') }}</p>
            @include('contracts.signing._signature_fields', [
                'signerName' => $signatureRequest->signer_name,
                'signerFunction' => $signatureRequest->signer_function,
                'declaration' => $revision->declaration_text,
                'idPrefix' => 'public',
            ])
            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="primary">{{ __('contract-signing.action.sign_now') }}</x-button>
            </div>
        </form>

        <form method="POST" action="{{ route('agreements.public-sign.upload', ['token' => $token]) }}" enctype="multipart/form-data" class="mb-4 rounded-box bg-base-100 p-4 shadow">
            @csrf
            <h2 class="mb-1 text-sm font-semibold">{{ __('contract-signing.public.upload_heading') }}</h2>
            <p class="mb-3 text-xs text-muted">{{ __('contract-signing.public.upload_intro') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="fieldset sm:col-span-2">
                    <label class="fieldset-label" for="public-evidence-file">{{ __('contract-signing.field.evidence_file') }}</label>
                    <input id="public-evidence-file" type="file" name="evidence_file" accept="application/pdf" class="file-input file-input-bordered w-full" required>
                    <span class="text-xs text-muted">{{ __('contract-signing.hint.evidence_file') }}</span>
                </div>
                <x-input-field name="signer_name" id="public-upload-signer-name" :label="__('contract-signing.field.signer_name')" :value="old('signer_name', $signatureRequest->signer_name)" required />
                <x-input-field name="stated_signed_on" id="public-stated-signed-on" type="date" :label="__('contract-signing.field.stated_signed_on')" :value="old('stated_signed_on')" :max="now()->toDateString()" />
            </div>
            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="secondary">{{ __('contract-signing.action.upload_now') }}</x-button>
            </div>
        </form>
    @endif

    <p class="text-xs text-muted">{{ __('contract-signing.public.legal_note') }}</p>
</main>
@stack('scripts')
</body>
</html>
