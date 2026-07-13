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
        @if ($canIssue ?? false)
            <x-button :href="route('admin.license.issuer')" tone="secondary" size="sm">
                {{ __('Lizenzen ausstellen') }}
            </x-button>
        @endif
        @if ($canInstall)
            <x-button :href="route('license.show')" tone="primary" size="sm">
                {{ __('Lizenz installieren / aktualisieren') }}
            </x-button>
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

    {{-- Mandantenstatus (SaaS): trial/active/suspended/expired (Feature 021) --}}
    @if ($org !== null && $tenantStatus !== null)
        @php
            /** @var \App\Enums\Organization\TenantStatus $tenantStatus */
            /** @var \App\Enums\Organization\TenantStatus|null $tenantStatusExplicit */
            /** @var list<\App\Enums\Organization\TenantStatus> $tenantStatusOptions */
            $nearExpiry = $orgExpiresIn !== null && $orgExpiresIn >= 0 && $orgExpiresIn <= 30;
        @endphp
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Mandantenstatus') }}</h2>
                    <x-status-badge :tone="$tenantStatus->tone()" size="md" outline>{{ $tenantStatus->label() }}</x-status-badge>
                </div>

                <p class="text-sm text-base-content/70">
                    @switch($tenantStatus->value)
                        @case('suspended')
                            {{ __('Der Mandant ist gesperrt. Schreibende Aktionen sind deaktiviert (nur Lesezugriff).') }}
                            @break
                        @case('expired')
                            {{ __('Die Lizenz ist endgültig abgelaufen. Schreibende Aktionen sind deaktiviert.') }}
                            @break
                        @case('trial')
                            {{ __('Der Mandant befindet sich in der Testphase.') }}
                            @break
                        @default
                            {{ __('Der Mandant ist regulär aktiv.') }}
                    @endswitch
                    @unless ($tenantStatusExplicit !== null)
                        <span class="text-xs text-base-content/50">{{ __('(aus Lizenz/Testphase abgeleitet)') }}</span>
                    @endunless
                </p>

                @if ($nearExpiry)
                    <p class="rounded-box border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-base-content/80">
                        {{ __('Achtung: Die Lizenz läuft in :n Tagen ab. Bitte rechtzeitig erneuern.', ['n' => $orgExpiresIn]) }}
                    </p>
                @endif

                @if ($canManageTenant ?? false)
                    <form method="POST" action="{{ route('admin.license.tenantStatus') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Status setzen') }}</label>
                            <select name="tenant_status" class="select select-sm select-bordered">
                                <option value="inherit" @selected($tenantStatusExplicit === null)>{{ __('Automatisch (ableiten)') }}</option>
                                @foreach ($tenantStatusOptions as $opt)
                                    <option value="{{ $opt->value }}" @selected($tenantStatusExplicit === $opt)>{{ $opt->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-button type="submit" tone="primary" size="sm">{{ __('Übernehmen') }}</x-button>
                    </form>
                @endif
            </div>
        </article>
    @endif

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
                        <dd><x-status-badge :tone="$orgUsable ? 'success' : 'neutral'" size="md">{{ __('values.' . $orgModules['plan']) }}</x-status-badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Zugebuchte Module') }}</dt>
                        <dd class="text-xs break-all">{{ count($orgModules['addons']) ? collect($orgModules['addons'])->map(fn ($c) => config('plans.labels')[$c] ?? $c)->implode(', ') : '—' }}</dd>
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
                            <x-button type="submit" tone="primary" size="sm">{{ __('Installieren') }}</x-button>
                            @if ($op !== null)
                                <x-button type="submit" form="org-license-remove" tone="ghost" size="sm" class="text-error">{{ __('Entfernen') }}</x-button>
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
                                        @foreach (['free', 'pro', 'enterprise'] as $val)
                                            <option value="{{ $val }}" @selected(old('plan', $orgModules['plan']) === $val)>{{ __('values.' . $val) }}</option>
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
                                            <span class="text-sm">{{ config('plans.labels')[$code] ?? $code }}</span>
                                            <span class="font-mono text-[0.65rem] text-base-content/40">{{ $code }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-base-content/50">{{ __('Tier-Module sind bereits enthalten; hier nur zusätzliche Module zubuchen.') }}</p>
                            </div>
                            <x-button type="submit" tone="primary" size="sm">{{ __('Ausstellen & installieren') }}</x-button>
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
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">flag</span>' :title="__('Diese Lizenz enthält keine expliziten Feature-Flags.')" compact />
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

    {{-- MVP-052: Modulkonfiguration (lizenzierte Module org-bezogen schalten) --}}
    @if (count($modules) > 0)
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Module der Organisation') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Lizenzierte Module für diese Organisation ein- oder ausblenden. Deaktivieren löscht keine Daten und kann jederzeit rückgängig gemacht werden.') }}
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($modules as $module)
                        @php
                            /** @var \App\Enums\Licensing\ModuleStatus $status */
                            $status = $module['status'];
                            $dialogId = 'module-disable-' . \Illuminate\Support\Str::slug($module['code']);
                        @endphp
                        <div class="flex items-start justify-between gap-3 rounded-box border border-base-300 bg-base-200/30 p-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $module['label'] }}</span>
                                    <x-status-badge :tone="$status->tone()" size="sm" outline>{{ $status->label() }}</x-status-badge>
                                </div>
                                <p class="mt-1 text-xs text-base-content/60">{{ $module['description'] }}</p>
                                @if ($module['licensed'] && $module['source'])
                                    <p class="mt-1 text-[11px] uppercase tracking-wider text-base-content/40">
                                        {{ __('Quelle') }}: {{ $module['source'] === 'plan' ? __('Plan') : __('Add-on') }}
                                    </p>
                                @endif
                                @if ($module['reason'])
                                    <p class="mt-1 text-xs text-warning">{{ $module['reason'] }}</p>
                                @endif
                            </div>
                            @if ($canConfigureModules)
                                <div class="shrink-0">
                                    @if ($status === \App\Enums\Licensing\ModuleStatus::Active)
                                        <button type="button" class="btn btn-sm btn-outline btn-warning"
                                                data-open-dialog="{{ $dialogId }}">
                                            {{ __('Deaktivieren') }}
                                        </button>
                                        <dialog id="{{ $dialogId }}" class="modal">
                                            <div class="modal-box">
                                                <h3 class="text-base font-semibold">{{ __('Modul deaktivieren') }}: {{ $module['label'] }}</h3>
                                                <p class="mt-2 text-sm text-base-content/70">
                                                    {{ __('Das Modul verschwindet aus Navigation, Dashboard, Suche, Onboarding und Hilfe. Direkte Aufrufe werden serverseitig gesperrt.') }}
                                                </p>
                                                <p class="mt-2 text-sm font-medium text-success">
                                                    {{ __('Es werden keine Daten gelöscht. Eine Reaktivierung stellt den Zugriff sofort wieder her.') }}
                                                </p>
                                                <form method="POST" action="{{ route('admin.license.modules.disable') }}" class="mt-3 space-y-2">
                                                    @csrf
                                                    <input type="hidden" name="module" value="{{ $module['code'] }}">
                                                    <textarea name="reason" rows="2" class="textarea textarea-bordered textarea-sm w-full"
                                                              placeholder="{{ __('Interne Begründung (optional)') }}"></textarea>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" class="btn btn-sm btn-ghost"
                                                                data-entry-modal-close>{{ __('Abbrechen') }}</button>
                                                        <button type="submit" class="btn btn-sm btn-warning">{{ __('Deaktivieren') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                            <form method="dialog" class="modal-backdrop"><button>{{ __('Schließen') }}</button></form>
                                        </dialog>
                                    @elseif ($status === \App\Enums\Licensing\ModuleStatus::InactiveByCustomer)
                                        <form method="POST" action="{{ route('admin.license.modules.enable') }}">
                                            @csrf
                                            <input type="hidden" name="module" value="{{ $module['code'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline btn-success">{{ __('Aktivieren') }}</button>
                                        </form>
                                    @elseif ($status === \App\Enums\Licensing\ModuleStatus::NotLicensed)
                                        <span class="text-xs text-base-content/40">{{ __('Nicht lizenziert') }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </article>
    @endif
</x-index-page>
@endsection
