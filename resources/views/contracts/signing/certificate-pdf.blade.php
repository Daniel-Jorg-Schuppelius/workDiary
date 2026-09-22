{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : certificate-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abschlussnachweis (Feature 157): Parteien, Fassungskennung, Dateiliste mit
     Hashes, Signaturmethoden und Zeitpunkte. Nachträglich gerendert — keine
     kryptografische PDF-Signatur, das sagt das Dokument selbst. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('contract-signing.certificate.title') }} {{ $contract?->number }}</title>
@php
    /** @var \App\Services\DocumentDesign\DesignContext $design */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $accent = $design->accentColor();
    $legalName = $orgLegal['company_name'] ?? $orgLegal['name'] ?? $contract?->organization?->name ?? '';
@endphp
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
    h1 { font-size: 17px; margin: 0 0 4px; color: {{ $accent }}; }
    h2 { font-size: 12px; margin: 16px 0 6px; color: {{ $accent }}; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #ccc; vertical-align: top; text-align: left; }
    th { background: #f3f3f3; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px; word-break: break-all; }
    .muted { color: #6b7280; }
    .meta { display: table; width: 100%; margin-bottom: 10px; }
    .meta div { display: table-cell; width: 50%; }
    .note { margin-top: 18px; padding: 8px 10px; border: 1px solid #ddd; font-size: 9px; color: #555; }
    .tpl-header { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #ddd; white-space: pre-line; }
    .tpl-footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #555; white-space: pre-line; }
    @page { margin: 20mm; }
</style>
</head>
<body>
@if ($design->headerText() !== null)
    <div class="tpl-header">{{ $design->headerText() }}</div>
@endif

<h1>{{ __('contract-signing.certificate.title') }}</h1>
<div class="meta">
    <div>
        <strong>{{ $contract?->kind->label() }}</strong><br>
        {{ $contract?->number }} — {{ $contract?->title }}<br>
        {{ __('contract-signing.certificate.revision', ['no' => $revision->revision_no]) }}
        @if ($revision->effective_on) · {{ __('contract-signing.revision.effective_on', ['date' => $revision->effective_on->fdate()]) }} @endif
    </div>
    <div>
        <strong>{{ __('contract-signing.certificate.parties_heading') }}</strong><br>
        {{ __('contract-signing.party.customer') }}: {{ $contract?->customer?->name }}<br>
        {{ __('contract-signing.party.organization') }}: {{ $legalName }}
        @if ($revision->controller_party)
            <br>{{ __('contract-signing.revision.controller', ['party' => $revision->controller_party->label()]) }}
        @endif
    </div>
</div>

<p>
    {{ __('contract-signing.certificate.status', ['status' => $revision->status->label()]) }}
    @if ($revision->completed_at) — {{ __('contract-signing.revision.completed_at', ['at' => $revision->completed_at->fdatetime()]) }} @endif
    @if ($revision->supersededBy) — {{ __('contract-signing.revision.replaced_by', ['no' => $revision->supersededBy->revision_no, 'at' => $revision->superseded_at?->fdatetime()]) }} @endif
</p>

<h2>{{ __('contract-signing.revision.files') }}</h2>
<table>
    <thead><tr><th>#</th><th>{{ __('contract-signing.field.role') }}</th><th>{{ __('contract-signing.field.file') }}</th><th>{{ __('contract-signing.field.size') }}</th><th>SHA-256</th></tr></thead>
    <tbody>
    @foreach ($items as $item)
        <tr>
            <td>{{ $item->sort + 1 }}</td>
            <td>{{ $item->roleLabel() }}</td>
            <td>{{ $item->original_name }}</td>
            <td>{{ \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $item->size, 1) }}</td>
            <td class="mono">{{ $item->sha256 }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="mono muted">{{ __('contract-signing.revision.manifest_hash') }}: {{ $revision->manifest_hash }}</p>

<h2>{{ __('contract-signing.certificate.signatures_heading') }}</h2>
<table>
    <thead><tr><th>{{ __('contract-signing.field.party') }}</th><th>{{ __('contract-signing.field.signer') }}</th><th>{{ __('contract-signing.field.method') }}</th><th>{{ __('contract-signing.field.signed_at') }}</th><th>{{ __('contract-signing.field.evidence') }}</th></tr></thead>
    <tbody>
    @foreach ($requests as $req)
        @php $ev = $req->fulfillingEvidence(); @endphp
        <tr>
            <td>{{ $req->party->label() }}@unless ($req->required)<br><span class="muted">{{ __('contract-signing.request.waived', ['reason' => $req->waiver_reason]) }}</span>@endunless</td>
            <td>{{ $ev?->signer_name ?? $req->signer_name }}@if ($ev?->signer_function ?? $req->signer_function)<br><span class="muted">{{ $ev?->signer_function ?? $req->signer_function }}</span>@endif</td>
            <td>{{ $ev?->method->label() ?? '—' }}<br><span class="muted">{{ $ev ? __('contract-signing.evidence.via.' . $ev->submitted_via) : '' }}</span></td>
            <td>
                {{ $ev?->signed_at->fdatetime() ?? '—' }}
                @if ($ev?->stated_signed_on)<br><span class="muted">{{ __('contract-signing.evidence.stated_signed_on', ['date' => $ev->stated_signed_on->fdate()]) }}</span>@endif
                @if ($ev?->reviewer)<br><span class="muted">{{ __('contract-signing.evidence.reviewed_by', ['name' => $ev->reviewer->name, 'at' => $ev->reviewed_at?->fdatetime()]) }}</span>@endif
            </td>
            <td class="mono">{{ $ev?->file_hash ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>{{ __('contract-signing.revision.declaration') }}</h2>
<p style="white-space: pre-line">{{ $revision->declaration_text }}</p>

<div class="note">
    {{ __('contract-signing.certificate.disclaimer') }}<br>
    {{ __('contract-signing.certificate.generated_at', ['at' => now()->fdatetime()]) }}
</div>

@if ($design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
</body>
</html>
