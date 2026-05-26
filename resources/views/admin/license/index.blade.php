@extends('layouts.app')

@section('title', __('Lizenz') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Lizenz'))

@php
    /** @var \App\Services\Licensing\LicenseResult $license */
    /** @var string $badgeTone */
    /** @var list<array{code:string, label:string, used:int|null, max:int|null, percent:int|null, status:string}> $limits */
    /** @var list<array{code:string, enabled:bool, source:string}> $features */
    /** @var int|null $expiresIn */
    /** @var bool $isEnforced */
    /** @var bool $canInstall */
    $payload = $license->payload;
    $statusLabel = match ($license->status->value) {
        'valid' => __('Gültig'),
        'grace_period' => __('Grace-Period'),
        'missing' => __('Nicht installiert'),
        'expired' => __('Abgelaufen'),
        'malformed' => __('Ungültiges Format'),
        'bad_signature' => __('Signatur ungültig'),
        'domain_mismatch' => __('Domain stimmt nicht'),
        'public_key_missing' => __('Public-Key fehlt'),
        'tampered' => __('Manipuliert'),
        default => $license->status->value,
    };
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar
            :subtitle="$payload?->licensee ?? __('Keine aktive Lizenz')"
            :badge="$statusLabel"
            :badge-tone="$badgeTone"
        >
            @unless ($isEnforced)
                <span class="text-xs text-base-content/60">{{ __('Lizenzprüfung ist in dieser Umgebung deaktiviert (Dev/Test).') }}</span>
            @endunless

            <x-slot:actions>
                @if ($canInstall)
                    <a href="{{ route('license.show') }}" class="btn btn-sm btn-primary">
                        {{ __('Lizenz installieren / aktualisieren') }}
                    </a>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Lizenz-Karte') }}</h2>

            @if ($payload === null)
                <p class="text-sm text-base-content/70">
                    {{ $license->message ?? __('Es ist aktuell keine gültige Lizenz hinterlegt.') }}
                </p>
            @else
                <dl class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Lizenznehmer') }}</dt>
                        <dd class="font-mono text-base-content">{{ $payload->licensee }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Lizenz-ID') }}</dt>
                        <dd class="font-mono text-xs text-base-content/80 break-all">{{ $payload->licenseId }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Ausgestellt') }}</dt>
                        <dd>{{ $payload->issuedAt->translatedFormat('d.m.Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Gültig bis') }}</dt>
                        <dd>
                            @if ($payload->expiresAt)
                                {{ $payload->expiresAt->translatedFormat('d.m.Y') }}
                                @if ($expiresIn !== null)
                                    <span class="ml-1 text-xs text-base-content/60">
                                        ({{ $expiresIn >= 0 ? __(':n Tage verbleibend', ['n' => $expiresIn]) : __(':n Tage überzogen', ['n' => abs($expiresIn)]) }})
                                    </span>
                                @endif
                            @else
                                <span class="italic text-base-content/60">{{ __('unbefristet') }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($payload->domain)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Domain-Bindung') }}</dt>
                            <dd class="font-mono">{{ $payload->domain }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($license->message)
                    <p class="rounded-box border border-base-300 bg-base-200 px-3 py-2 text-xs text-base-content/70">
                        {{ $license->message }}
                    </p>
                @endif
            @endif
        </div>
    </article>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Limits') }}</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ($limits as $limit)
                    @php
                        $tone = match ($limit['status']) {
                            'critical' => 'progress-error',
                            'warn' => 'progress-warning',
                            default => 'progress-primary',
                        };
                    @endphp
                    <div class="rounded-box border border-base-300 bg-base-200 p-3">
                        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ $limit['label'] }}</p>
                        <p class="mt-1 font-['Space_Grotesk'] text-lg font-bold">
                            {{ $limit['used'] ?? '—' }}
                            @if ($limit['max'])
                                <span class="text-base-content/50 text-sm font-normal">/ {{ $limit['max'] }}</span>
                            @else
                                <span class="text-base-content/50 text-sm font-normal">/ {{ __('unbegrenzt') }}</span>
                            @endif
                        </p>
                        @if ($limit['percent'] !== null)
                            <progress class="progress {{ $tone }} w-full mt-2" value="{{ min(100, $limit['percent']) }}" max="100"></progress>
                            <p class="mt-1 text-xs text-base-content/60">{{ $limit['percent'] }} %</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </article>

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Feature-Flags') }}</h2>
            @if (count($features) === 0)
                <p class="text-sm text-base-content/60">
                    {{ __('Diese Lizenz enthält keine expliziten Feature-Flags.') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Quelle') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($features as $feature)
                                <tr>
                                    <td class="font-mono text-xs">{{ $feature['code'] }}</td>
                                    <td>
                                        @if ($feature['enabled'])
                                            <span class="badge badge-success badge-outline">{{ __('Aktiv') }}</span>
                                        @else
                                            <span class="badge badge-ghost">{{ __('Aus') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-base-content/60">{{ $feature['source'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </article>
</x-page-shell>
@endsection
