@extends('layouts.app')

@section('title', __('Datenschutz') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Datenschutz'))

@php
    /** @var \App\Models\Organization $organization */
    /** @var int $memberCount */
    /** @var \Illuminate\Support\Collection $sessions */
    /** @var \Illuminate\Support\Collection $tokens */
    /** @var \Illuminate\Database\Eloquent\Collection $sessionUsers */
    /** @var \Illuminate\Database\Eloquent\Collection $tokenUsers */
    /** @var array<int, array<string, mixed>> $categories */
    /** @var string $operatingMode */
    /** @var string|null $dpaUrl */
    /** @var bool $canRevokeSessions */
    /** @var bool $canRevokeTokens */
    /** @var \Illuminate\Support\Collection $exports */
    /** @var \Illuminate\Support\Collection $supportAccesses */
    /** @var \Illuminate\Database\Eloquent\Collection $auditActors */
    /** @var bool $canViewExports */
    /** @var bool $canViewSupport */
    /** @var bool $canExportReport */
    $modeLabel = match ($operatingMode) {
        'saas' => __('SaaS'),
        'private_cloud' => __('Private Cloud'),
        'on_premise' => __('On-Premise'),
        default => $operatingMode,
    };
    $sensitivityLabel = [
        'high' => __('hoch'),
        'medium' => __('mittel'),
        'low' => __('gering'),
        'special' => __('besonders sensibel'),
        'depends' => __('je nach Inhalt'),
    ];
    $sensitivityClass = [
        'high' => 'badge-warning',
        'special' => 'badge-error',
        'medium' => 'badge-info',
        'low' => 'badge-ghost',
        'depends' => 'badge-ghost',
    ];
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar
            :subtitle="$organization->name"
            :badge="$modeLabel"
            badge-tone="primary"
        >
            @if (! empty($canExportReport))
                <x-slot:actions>
                    <x-icon-btn icon="download" tone="primary" size="sm"
                                :href="route('admin.privacy.export', ['format' => 'json'])"
                                show-label>{{ __('Bericht (JSON)') }}</x-icon-btn>
                    <x-icon-btn icon="table_view" tone="ghost" size="sm"
                                :href="route('admin.privacy.export', ['format' => 'csv'])"
                                show-label>{{ __('Bericht (CSV)') }}</x-icon-btn>
                </x-slot:actions>
            @endif
            {{ __('Übersicht über Datenkategorien, Aufbewahrung, aktive Sessions und API-Tokens dieser Organisation.') }}
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- §3.1 Kopfbereich: Status --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Status auf einen Blick') }}</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktive Nutzer') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $memberCount }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktive Sessions') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $sessions->count() }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('API-Tokens') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $tokens->count() }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('AVV/DPA') }}</p>
                    <p class="mt-1 text-sm">
                        @if ($dpaUrl)
                            <a href="{{ $dpaUrl }}" class="link link-primary" target="_blank" rel="noopener">{{ __('Dokument öffnen') }}</a>
                        @else
                            <span class="italic text-base-content/60">{{ __('nicht hinterlegt') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </article>

    {{-- §3.2 Datenkategorien --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Datenkategorien und Aufbewahrung') }}</h2>
            <p class="text-xs text-base-content/60">
                {{ __('Vorschlag nach deutschem Recht (GoBD). Verbindliche Fristen werden im Folge-MVP über organizations.settings[privacy] gesetzt.') }}
            </p>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Kategorie') }}</th>
                            <th>{{ __('Modelle') }}</th>
                            <th>{{ __('Sensibilität') }}</th>
                            <th>{{ __('Aufbewahrung') }}</th>
                            <th>{{ __('Löschpfad') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $cat)
                            @php
                                $sens = (string) ($cat['sensitivity'] ?? 'medium');
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $cat['label'] ?? $cat['code'] ?? '—' }}</td>
                                <td class="text-xs font-mono text-base-content/70">{{ implode(', ', (array) ($cat['models'] ?? [])) }}</td>
                                <td>
                                    <span class="badge badge-outline {{ $sensitivityClass[$sens] ?? 'badge-ghost' }}">
                                        {{ $sensitivityLabel[$sens] ?? $sens }}
                                    </span>
                                </td>
                                <td>{{ $cat['retention'] ?? '—' }}</td>
                                <td class="text-xs text-base-content/70">{{ $cat['delete_path'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </article>

    {{-- §3.3 Aktive Sessions --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Aktive Sessions') }}</h2>
            @if ($sessions->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine aktiven Sessions.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Nutzer') }}</th>
                                <th>{{ __('IP') }}</th>
                                <th>{{ __('User-Agent') }}</th>
                                <th>{{ __('Letzte Aktivität') }}</th>
                                @if ($canRevokeSessions)
                                    <th class="text-right">{{ __('Aktion') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                @php
                                    $sessionUser = $sessionUsers->get($session->user_id);
                                    $lastActivity = $session->last_activity
                                        ? \Carbon\CarbonImmutable::createFromTimestamp((int) $session->last_activity)
                                        : null;
                                @endphp
                                <tr>
                                    <td>{{ $sessionUser?->name ?? __('Anonym') }}</td>
                                    <td class="font-mono text-xs">{{ $session->ip_address ?? '—' }}</td>
                                    <td class="max-w-xs truncate text-xs text-base-content/70" title="{{ $session->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $session->user_agent, 60) }}</td>
                                    <td class="text-xs">{{ $lastActivity?->diffForHumans() ?? '—' }}</td>
                                    @if ($canRevokeSessions)
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.privacy.sessions.destroy', ['id' => $session->id]) }}"
                                                  onsubmit="return confirm('{{ __('Session wirklich widerrufen?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Widerrufen') }}</button>
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

    {{-- §3.4 API-Tokens --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('API-Tokens') }}</h2>
            @if ($tokens->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine API-Tokens aktiv.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Nutzer') }}</th>
                                <th>{{ __('Angelegt') }}</th>
                                <th>{{ __('Zuletzt genutzt') }}</th>
                                <th>{{ __('Ablauf') }}</th>
                                @if ($canRevokeTokens)
                                    <th class="text-right">{{ __('Aktion') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                @php
                                    $tokenUser = $tokenUsers->get($token->tokenable_id);
                                @endphp
                                <tr>
                                    <td class="font-medium">{{ $token->name }}</td>
                                    <td>{{ $tokenUser?->name ?? '—' }}</td>
                                    <td class="text-xs text-base-content/70">{{ $token->created_at ? \Carbon\CarbonImmutable::parse($token->created_at)->translatedFormat('d.m.Y') : '—' }}</td>
                                    <td class="text-xs">{{ $token->last_used_at ? \Carbon\CarbonImmutable::parse($token->last_used_at)->diffForHumans() : '—' }}</td>
                                    <td class="text-xs">{{ $token->expires_at ? \Carbon\CarbonImmutable::parse($token->expires_at)->translatedFormat('d.m.Y') : __('—') }}</td>
                                    @if ($canRevokeTokens)
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.privacy.tokens.destroy', ['id' => $token->id]) }}"
                                                  onsubmit="return confirm('{{ __('Token wirklich widerrufen?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Widerrufen') }}</button>
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

    {{-- §3.6 Mandantenexporte (letzte 20 Audit-Events mit Präfix tenant.export.*) --}}
    @if ($canViewExports)
        <article class="card border border-base-300 bg-base-100 shadow-sm" data-section="exports">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Mandantenexporte') }}</h2>
                <p class="text-xs text-base-content/60">
                    {{ __('Letzte 20 Export-Ereignisse aus dem Audit-Protokoll dieser Organisation.') }}
                </p>
                @if ($exports->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine Exporte verzeichnet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Zeitpunkt') }}</th>
                                    <th>{{ __('Auslöser') }}</th>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Format / Scope') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exports as $export)
                                    @php
                                        $actor = $export->user_id ? $auditActors->get($export->user_id) : null;
                                        $changes = is_string($export->changes) ? json_decode($export->changes, true) : (array) ($export->changes ?? []);
                                        $format = $changes['format'] ?? ($changes['type'] ?? null);
                                        $scope = $changes['scope'] ?? null;
                                        $bytes = $changes['bytes'] ?? null;
                                        $createdAt = $export->created_at ? \Carbon\CarbonImmutable::parse($export->created_at) : null;
                                    @endphp
                                    <tr>
                                        <td class="text-xs">{{ $createdAt?->translatedFormat('d.m.Y H:i') ?? '—' }}</td>
                                        <td>{{ $actor?->name ?? __('System') }}</td>
                                        <td class="font-mono text-xs">{{ $export->event }}</td>
                                        <td class="text-xs text-base-content/70">
                                            @if ($format)<span class="badge badge-sm badge-outline">{{ $format }}</span>@endif
                                            @if ($scope)<span class="ml-1">{{ $scope }}</span>@endif
                                            @if (is_numeric($bytes))<span class="ml-1 text-base-content/50">({{ number_format((int) $bytes / 1024, 0, ',', '.') }} KB)</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </article>
    @endif

    {{-- §3.7 Letzte Supportzugriffe (Audit-Events mit Präfix support.*) --}}
    @if ($canViewSupport)
        <article class="card border border-base-300 bg-base-100 shadow-sm" data-section="support">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Letzte Supportzugriffe') }}</h2>
                <p class="text-xs text-base-content/60">
                    {{ __('Letzte 20 Audit-Ereignisse aus dem Support-Kontext dieser Organisation.') }}
                </p>
                @if ($supportAccesses->isEmpty())
                    <p class="text-sm text-base-content/60">{{ __('Keine Supportzugriffe verzeichnet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Zeitpunkt') }}</th>
                                    <th>{{ __('Support-Identität') }}</th>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Ticket / Scope') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supportAccesses as $entry)
                                    @php
                                        $actor = $entry->user_id ? $auditActors->get($entry->user_id) : null;
                                        $changes = is_string($entry->changes) ? json_decode($entry->changes, true) : (array) ($entry->changes ?? []);
                                        $ticket = $changes['ticket'] ?? null;
                                        $scope = $changes['scope'] ?? null;
                                        $createdAt = $entry->created_at ? \Carbon\CarbonImmutable::parse($entry->created_at) : null;
                                    @endphp
                                    <tr>
                                        <td class="text-xs">{{ $createdAt?->translatedFormat('d.m.Y H:i') ?? '—' }}</td>
                                        <td>{{ $actor?->name ?? __('System') }}</td>
                                        <td class="font-mono text-xs">{{ $entry->event }}</td>
                                        <td class="text-xs text-base-content/70">
                                            @if ($ticket)<span class="font-mono">{{ $ticket }}</span>@endif
                                            @if ($scope) {{ $scope }}@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </article>
    @endif

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Folge-Sektionen') }}</h2>
            <p class="text-sm text-base-content/70">
                {{ __('Externe Integrationen und der DSGVO-PDF-Bericht folgen in separaten MVPs (siehe Doku-Verweis unten).') }}
            </p>
            <ul class="list-disc pl-6 text-sm text-base-content/70">
                <li><a class="link" href="{{ asset('docs/security/datenschutzseite-konzept.md') }}">{{ __('Datenschutzseite-Konzept (Quelle)') }}</a></li>
                <li><a class="link" href="{{ asset('docs/security/supportzugriff-grundsaetze.md') }}">{{ __('Supportzugriff-Grundsätze') }}</a></li>
                <li><a class="link" href="{{ asset('docs/security/rollen-matrix.md') }}">{{ __('Rollen-Matrix') }}</a></li>
            </ul>
        </div>
    </article>
</x-page-shell>
@endsection
