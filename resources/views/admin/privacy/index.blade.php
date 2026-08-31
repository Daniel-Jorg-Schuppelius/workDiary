{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
    /** @var array<int, array<string, mixed>> $integrations */
    /** @var \Illuminate\Database\Eloquent\Collection $auditActors */
    /** @var bool $canViewIntegrations */
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
<x-index-page
    :subtitle="$organization->name"
    :badge="$modeLabel"
    badge-tone="primary"
>
    @if (! empty($canExportReport))
        <x-slot:actions>
            <x-icon-btn icon="picture_as_pdf" tone="primary" size="sm"
                        :href="route('admin.privacy.report')"
                        show-label>{{ __('Bericht (PDF)') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('admin.privacy.export', ['format' => 'json'])"
                        show-label>{{ __('Bericht (JSON)') }}</x-icon-btn>
            <x-icon-btn icon="table_view" tone="ghost" size="sm"
                        :href="route('admin.privacy.export', ['format' => 'csv'])"
                        show-label>{{ __('Bericht (CSV)') }}</x-icon-btn>
        </x-slot:actions>
    @endif
    <x-slot:note>{{ __('Übersicht über Datenkategorien, Aufbewahrung, aktive Sessions und API-Tokens dieser Organisation.') }}</x-slot:note>

    {{-- §3.1 Kopfbereich: Status --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Status auf einen Blick') }}</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('Aktive Nutzer') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $memberCount }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('Aktive Sessions') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $sessions->count() }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('API-Tokens') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold">{{ $tokens->count() }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200 p-3">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('AVV/DPA') }}</p>
                    <p class="mt-1 text-sm">
                        @if ($dpaUrl)
                            <a href="{{ $dpaUrl }}" class="link link-primary" target="_blank" rel="noopener">{{ __('Dokument öffnen') }}</a>
                        @else
                            <span class="italic text-muted">{{ __('nicht hinterlegt') }}</span>
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
            <p class="text-xs text-muted">
                {{ __('Vorschlag nach deutschem Recht (GoBD). Verbindliche Fristen werden im Folge-MVP über organizations.settings[privacy] gesetzt.') }}
            </p>
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('Kategorie') }}</th>
                            <th>{{ __('Modelle') }}</th>
                            <th>{{ __('Sensibilität') }}</th>
                            <th>{{ __('Aufbewahrung') }}</th>
                            <th>{{ __('Löschpfad') }}</th>
                        </tr>
                </x-slot:head>
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
            </x-table>
        </div>
    </article>

    {{-- §3.3 Aktive Sessions --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Aktive Sessions') }}</h2>
            @if ($sessions->isEmpty())
                <x-empty-state icon="devices" :title="__('Keine aktiven Sessions.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Nutzer') }}</th>
                                <th>{{ __('IP') }}</th>
                                <th>{{ __('User-Agent') }}</th>
                                <th>{{ __('Letzte Aktivität') }}</th>
                                @if ($canRevokeSessions)
                                    <th class="text-right">{{ __('Aktion') }}</th>
                                @endif
                            </tr>
                    </x-slot:head>
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
                                            <x-action-form :action="route('admin.privacy.sessions.destroy', ['id' => $session->handle])"
                                                  method="DELETE"
                                                  :confirm="__('Session wirklich widerrufen?')"
                                                  confirm-icon="logout"
                                                  confirm-tone="error"
                                                  :confirm-label="__('Widerrufen')">
                                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('Widerrufen') }}</x-button>
                                            </x-action-form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </div>
    </article>

    {{-- §3.4 API-Tokens --}}
    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('API-Tokens') }}</h2>
            @if ($tokens->isEmpty())
                <x-empty-state icon="key" :title="__('Keine API-Tokens aktiv.')" compact />
            @else
                <x-table bare>
                    <x-slot:head>
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
                    </x-slot:head>
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
                                            <x-action-form :action="route('admin.privacy.tokens.destroy', ['id' => \App\Support\Sqid::encode(\Laravel\Sanctum\PersonalAccessToken::class, $token->id)])"
                                                  method="DELETE"
                                                  :confirm="__('Token wirklich widerrufen?')"
                                                  confirm-icon="key_off"
                                                  confirm-tone="error"
                                                  :confirm-label="__('Widerrufen')">
                                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('Widerrufen') }}</x-button>
                                            </x-action-form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </div>
    </article>

    {{-- §3.5 Externe Integrationen / Datenflüsse (MVP-327): Config-Dienste + org-aktive Plugins --}}
    @if ($canViewIntegrations)
        <article class="card border border-base-300 bg-base-100 shadow-sm" data-section="integrations">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Externe Integrationen und Datenflüsse') }}</h2>
                <p class="text-xs text-muted">
                    {{ __('Systemweite Dienste mit Datenabfluss sowie die in dieser Organisation aktivierten Plugins. Angezeigt werden nur Identität, Quelle und Status — niemals Zugangsdaten.') }}
                </p>
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('Integration') }}</th>
                                <th>{{ __('Quelle') }}</th>
                                <th>{{ __('Daten, die abfließen') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Dokumentation') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($integrations as $integration)
                                @php
                                    $integrationStatus = (string) ($integration['status'] ?? 'not_configured');
                                    $integrationStatusLabel = match ($integrationStatus) {
                                        'active' => __('aktiv'),
                                        'inactive' => __('inaktiv'),
                                        default => __('nicht konfiguriert'),
                                    };
                                    $integrationStatusClass = $integrationStatus === 'active' ? 'badge-success' : 'badge-ghost';
                                @endphp
                                <tr>
                                    <td class="font-medium">
                                        {{ $integration['name'] }}
                                        @if (($integration['type'] ?? '') === 'plugin')
                                            <span class="badge badge-sm badge-outline badge-info">{{ __('Plugin') }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs text-base-content/70">{{ $integration['source'] }}</td>
                                    <td class="text-xs text-base-content/70">{{ $integration['data'] }}</td>
                                    <td>
                                        <span class="badge badge-outline {{ $integrationStatusClass }}">{{ $integrationStatusLabel }}</span>
                                    </td>
                                    <td class="text-xs">
                                        @if (! empty($integration['docs_url']))
                                            <a href="{{ $integration['docs_url'] }}" class="link link-primary" target="_blank" rel="noopener">{{ __('Anbieter-Doku') }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
                <div class="rounded-box border border-base-300 bg-base-200 p-3 text-xs text-base-content/70">
                    <p>{{ __('WorkDiary nutzt keine Tracking-, Analytics- oder Werbe-Dienste.') }}</p>
                    <p>{{ __('Es findet keine produktübergreifende Auswertung von Kundendaten statt.') }}</p>
                </div>
            </div>
        </article>
    @endif

    {{-- §3.6 Mandantenexporte (letzte 20 Audit-Events mit Präfix tenant.export.*) --}}
    @if ($canViewExports)
        <article class="card border border-base-300 bg-base-100 shadow-sm" data-section="exports">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Mandantenexporte') }}</h2>
                <p class="text-xs text-muted">
                    {{ __('Letzte 20 Export-Ereignisse aus dem Audit-Protokoll dieser Organisation.') }}
                </p>
                @if ($exports->isEmpty())
                    <x-empty-state icon="download" :title="__('Keine Exporte verzeichnet.')" compact />
                @else
                    <x-table bare>
                        <x-slot:head>
                                <tr>
                                    <th>{{ __('Zeitpunkt') }}</th>
                                    <th>{{ __('Auslöser') }}</th>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Format / Scope') }}</th>
                                </tr>
                        </x-slot:head>
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
                                            @if (is_numeric($bytes))<span class="ml-1 text-muted">({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $bytes / 1024, 0, withThousandsSeparator: true) }} KB)</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                    </x-table>
                @endif
            </div>
        </article>
    @endif

    {{-- §3.7 Letzte Supportzugriffe (Audit-Events mit Präfix support.*) --}}
    @if ($canViewSupport)
        <article class="card border border-base-300 bg-base-100 shadow-sm" data-section="support">
            <div class="card-body gap-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Letzte Supportzugriffe') }}</h2>
                <p class="text-xs text-muted">
                    {{ __('Letzte 20 Audit-Ereignisse aus dem Support-Kontext dieser Organisation.') }}
                </p>
                @if ($supportAccesses->isEmpty())
                    <x-empty-state icon="support_agent" :title="__('Keine Supportzugriffe verzeichnet.')" compact />
                @else
                    <x-table bare>
                        <x-slot:head>
                                <tr>
                                    <th>{{ __('Zeitpunkt') }}</th>
                                    <th>{{ __('Support-Identität') }}</th>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Ticket / Scope') }}</th>
                                </tr>
                        </x-slot:head>
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
                    </x-table>
                @endif
            </div>
        </article>
    @endif

    <article class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Dokumentation') }}</h2>
            <p class="text-sm text-base-content/70">
                {{ __('Konzeptdokumente (Datenschutzseite-Konzept, Supportzugriff-Grundsätze, Rollen-Matrix) liegen im internen Architektur-Repository unter security/.') }}
            </p>
        </div>
    </article>
</x-index-page>
@endsection
