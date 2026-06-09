@extends('layouts.app')

@section('title', __('Lizenz') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Lizenz'))

@php
    /** @var \App\Services\Licensing\LicenseResult $license */
    /** @var string $badgeTone */
    /** @var list<array{code:string, label:string, used:int|null, max:int|null, percent:int|null, status:string}> $limits */
    /** @var list<array{code:string, enabled:bool, source:string, overridden:bool}> $features */
    /** @var int|null $expiresIn */
    /** @var bool $isEnforced */
    /** @var bool $canInstall */
    /** @var bool $canToggleFlag */
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
<x-index-page
    :subtitle="$payload?->licensee ?? __('Keine aktive Lizenz')"
    :badge="$statusLabel"
    :badge-tone="$badgeTone"
>
    <x-slot:note>
        @unless ($isEnforced)
            <span class="text-xs text-base-content/60">{{ __('Lizenzprüfung ist in dieser Umgebung deaktiviert (Dev/Test).') }}</span>
        @endunless
    </x-slot:note>
    <x-slot:actions>
        @if ($canInstall)
            <a href="{{ route('license.show') }}" class="btn btn-sm btn-primary">
                {{ __('Lizenz installieren / aktualisieren') }}
            </a>
        @endif
    </x-slot:actions>

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

    {{-- Org-gebundene Lizenz (Tier + Add-on-Module) --}}
    @php
        /** @var \App\Models\Organization|null $org */
        /** @var \App\Services\Licensing\LicenseResult|null $orgLicense */
        /** @var string $orgBadgeTone */
        /** @var int|null $orgExpiresIn */
        /** @var array{plan:string, addons:list<string>} $orgModules */
        $op = $orgLicense?->payload;
        $orgUsable = $orgLicense !== null && $orgLicense->isUsable();
        $orgStatusLabel = $orgLicense === null ? __('Keine') : match ($orgLicense->status->value) {
            'valid' => __('Gültig'),
            'grace_period' => __('Grace-Period'),
            'missing' => __('Keine'),
            'expired' => __('Abgelaufen'),
            'org_mismatch' => __('Falsche Organisation'),
            'bad_signature' => __('Signatur ungültig'),
            'malformed' => __('Ungültiges Format'),
            default => $orgLicense->status->value,
        };
        $planLabels = ['free' => 'Free', 'pro' => 'Pro', 'enterprise' => 'Enterprise'];
    @endphp
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Org-Lizenz') }}</h2>
                <x-status-badge :tone="$orgBadgeTone" size="md" outline>{{ $orgStatusLabel }}</x-status-badge>
            </div>

            @if ($org === null)
                <p class="text-sm text-base-content/70">{{ __('Keine aktive Organisation im Kontext.') }}</p>
            @else
                <dl class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Plan (Tier)') }}</dt>
                        <dd><x-status-badge :tone="$orgUsable ? 'success' : 'neutral'" size="md">{{ $planLabels[$orgModules['plan']] ?? $orgModules['plan'] }}</x-status-badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Zugebuchte Module') }}</dt>
                        <dd class="font-mono text-xs break-all">{{ count($orgModules['addons']) ? implode(', ', $orgModules['addons']) : '—' }}</dd>
                    </div>
                    @if ($op !== null && $orgUsable)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Lizenznehmer') }}</dt>
                            <dd class="font-mono">{{ $op->licensee }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Gültig bis') }}</dt>
                            <dd>
                                @if ($op->expiresAt)
                                    {{ $op->expiresAt->translatedFormat('d.m.Y') }}
                                    @if ($orgExpiresIn !== null)
                                        <span class="ml-1 text-xs text-base-content/60">({{ $orgExpiresIn >= 0 ? __(':n Tage verbleibend', ['n' => $orgExpiresIn]) : __(':n Tage überzogen', ['n' => abs($orgExpiresIn)]) }})</span>
                                    @endif
                                @else
                                    <span class="italic text-base-content/60">{{ __('unbefristet') }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bindungs-ID (für die Ausstellung)') }}</dt>
                        <dd class="font-mono text-xs text-base-content/80 break-all select-all">{{ $org->license_uid }}</dd>
                    </div>
                </dl>

                @if ($orgLicense !== null && $orgLicense->message && ! $orgUsable)
                    <p class="rounded-box border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-base-content/80">{{ $orgLicense->message }}</p>
                @endif

                @if ($canInstall)
                    <form method="POST" action="{{ route('admin.license.org.install') }}" class="mt-1 space-y-2">
                        @csrf
                        <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Lizenzschlüssel einspielen') }}</label>
                        <textarea name="license_key" rows="3" required
                            class="textarea textarea-bordered w-full font-mono text-xs @error('license_key') textarea-error @enderror"
                            placeholder="payload.signature">{{ old('license_key') }}</textarea>
                        @error('license_key')
                            <p class="text-xs text-error">{{ $message }}</p>
                        @enderror
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Installieren') }}</button>
                            @if ($op !== null)
                                <button type="submit" form="org-license-remove" class="btn btn-sm btn-ghost text-error">{{ __('Entfernen') }}</button>
                            @endif
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.license.org.remove') }}" id="org-license-remove" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endif

                {{-- Lizenz direkt ausstellen (nur auf einer Herausgeber-Instanz mit Private Key) --}}
                @if ($canInstall && ($canIssue ?? false))
                    <details class="rounded-box border border-base-300 bg-base-200/50 mt-2" @if ($errors->has('issue')) open @endif>
                        <summary class="cursor-pointer px-3 py-2 text-sm font-semibold">{{ __('Lizenz hier ausstellen') }}</summary>
                        <form method="POST" action="{{ route('admin.license.org.issue') }}" class="space-y-3 px-3 pb-3">
                            @csrf
                            @error('issue')<p class="text-xs text-error">{{ $message }}</p>@enderror
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Lizenznehmer') }}</label>
                                    <input type="text" name="licensee" required value="{{ old('licensee', $op->licensee ?? $org->name) }}"
                                        class="input input-sm input-bordered w-full @error('licensee') input-error @enderror">
                                </div>
                                <div>
                                    <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Plan (Tier)') }}</label>
                                    <select name="plan" class="select select-sm select-bordered w-full">
                                        @foreach (['free' => 'Free', 'pro' => 'Pro', 'enterprise' => 'Enterprise'] as $val => $lbl)
                                            <option value="{{ $val }}" @selected(old('plan', $orgModules['plan']) === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Gültig bis (optional)') }}</label>
                                    <input type="date" name="expires" value="{{ old('expires') }}"
                                        class="input input-sm input-bordered w-full @error('expires') input-error @enderror">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Einzeln gebuchte Module (Add-ons)') }}</label>
                                <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2">
                                    @php $oldAddons = (array) old('addons', $orgModules['addons']); @endphp
                                    @foreach ($moduleCodes as $code)
                                        <label class="label cursor-pointer justify-start gap-2 py-0.5">
                                            <input type="checkbox" name="addons[]" value="{{ $code }}" class="checkbox checkbox-xs" @checked(in_array($code, $oldAddons, true))>
                                            <span class="font-mono text-xs">{{ $code }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-base-content/50">{{ __('Tier-Module sind bereits enthalten; hier nur zusätzliche Module zubuchen.') }}</p>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Ausstellen & installieren') }}</button>
                        </form>
                    </details>
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
                                @if ($canToggleFlag)
                                    <th class="text-right">{{ __('Aktion') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($features as $feature)
                                <tr>
                                    <td class="font-mono text-xs">{{ $feature['code'] }}</td>
                                    <td>
                                        @if ($feature['enabled'])
                                            <x-status-badge tone="success" size="md" outline>{{ __('Aktiv') }}</x-status-badge>
                                        @else
                                            <x-status-badge tone="warning" size="md" outline>{{ __('Lokal deaktiviert') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-xs text-base-content/60">{{ $feature['source'] }}</td>
                                    @if ($canToggleFlag)
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.license.flags.toggle', ['flag' => $feature['code']]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $feature['overridden'] ? 'btn-success' : 'btn-warning' }} btn-outline">
                                                    {{ $feature['overridden'] ? __('Reaktivieren') : __('Deaktivieren') }}
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </article>
</x-index-page>
@endsection
