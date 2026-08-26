{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zertifizierungen (Feature 046, Inkrement B): Konformitätsstatus je
  Geltungsbereich + Norm/Ausgabe mit Status-Badge, Statuswechsel-Dropdown
  (nur erlaubte Übergänge; `certified` nur mit heute gültigem Zertifikat —
  serverseitig im ConformityService erzwungen, die UI zeigt den Hinweis)
  und aufklappbarem Zertifikatsregister je Zeile (Überwachungstermine mit
  Warn-Badge < 60 Tage, Zertifikats-PDF aus dem Dokumentenmodul).
  Variablen: $statuses, $scope, $scopes, $missingPairs, $canManage
--}}

@extends('layouts.app')

@section('title', __('isms.title.conformity'))
@section('nav-title', __('isms.title.conformity'))

@section('content')
    <x-index-page :subtitle="$scope !== null ? __('isms.subtitle.conformity_scope', ['scope' => $scope->name]) : __('isms.subtitle.conformity')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.conformity.create', array_filter(['scope' => $scope?->sqid]))"
                            show-label>{{ __('isms.action.create_norm_status') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        @if ($scopes->count() > 1)
            <x-filter-bar :action="route('isms.conformity.index')" :reset="null">
                <x-filter-field :label="__('isms.field.scope')" for="isms-conf-scope" class="min-w-44">
                    <select id="isms-conf-scope" name="scope" class="select select-sm select-bordered w-full">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->sqid }}" @selected($scope !== null && $scope->is($scopeOption))>{{ $scopeOption->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        @endif

        {{-- Strikte 046-Regel als sichtbarer Hinweis. --}}
        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="workspace_premium" />
            <span>{{ __('isms.conformity.certified_rule') }}</span>
        </div>

        @if ($missingPairs->isNotEmpty() && $canManage && $scope !== null)
            <div class="alert bg-base-200 text-sm">
                <x-icon name="playlist_add" />
                <span>{{ __('isms.conformity.missing_for_scope', ['scope' => $scope->name, 'norms' => $missingPairs->implode(', ')]) }}</span>
                <form method="POST" action="{{ route('isms.conformity.ensure', $scope) }}">
                    @csrf
                    <x-icon-btn icon="playlist_add" tone="outline" size="xs" type="submit"
                                show-label>{{ __('isms.action.ensure_norm_statuses') }}</x-icon-btn>
                </form>
            </div>
        @endif

            {{-- Stichtags-Rekonstruktion (Nachtrag 046b). --}}
    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            @if ($scope !== null)
                <input type="hidden" name="scope" value="{{ $scope->sqid }}">
            @endif
            <x-filter-field :label="__('Bewertungsstand zum Stichtag')" for="isms-conformity-as-of" show-label>
                <input type="date" id="isms-conformity-as-of" name="as_of" value="{{ request('as_of') }}" class="input input-sm input-bordered">
            </x-filter-field>
            <x-icon-btn icon="history" tone="outline" size="sm" type="submit" show-label>{{ __('Rekonstruieren') }}</x-icon-btn>
        </form>
        @if (($reconstruction ?? null) !== null)
            <div class="mt-3 rounded-box border border-base-300 p-3 text-sm">
                <p class="font-medium">{{ __('Stand zum :date', ['date' => $reconstruction['as_of']]) }}</p>
                <p class="text-base-content/70">{{ __(':total SoA-Aussagen erfasst, davon :applicable anwendbar.', ['total' => $reconstruction['statements']['total'], 'applicable' => $reconstruction['statements']['applicable']]) }}</p>
                @if ($reconstruction['norm_statuses'] !== [])
                    <ul class="mt-1 space-y-0.5 text-xs text-base-content/70">
                        @foreach ($reconstruction['norm_statuses'] as $entry)
                            <li>{{ $entry['norm'] }} {{ $entry['edition'] }} — {{ $entry['status'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </x-card>

<x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.norm') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th class="text-center">{{ __('isms.field.certificates') }}</th>
                    <th>{{ __('isms.field.valid_until') }}</th>
                    <th>{{ __('isms.field.surveillance') }}</th>
                    <th>{{ __('isms.field.notes') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($statuses as $status)
                @php
                    /** @var \App\Models\Isms\IsmsNormStatus $status */
                    $today = \Illuminate\Support\Carbon::today();
                    $activeCertificate = $status->certificates->first(fn(\App\Models\Isms\IsmsCertificate $c): bool => $c->isValidOn($today));
                    $nextSurveillance = $status->certificates
                        ->map(fn(\App\Models\Isms\IsmsCertificate $c) => $c->nextSurveillanceOn())
                        ->filter()
                        ->sort()
                        ->first();
                @endphp
                <tr class="hover" id="isms-norm-status-{{ $status->id }}">
                    <td>
                        <details>
                            <summary class="cursor-pointer font-medium">{{ $status->normLabel() }}</summary>
                            <div class="mt-2 space-y-2 text-xs text-base-content/70">
                                {{-- Normprofil-Versionsmetadaten (Nachtrag 046a). --}}
                                @php
                                    $currentProfile = app(\App\Services\Isms\NormProfileRegistry::class)->findByNorm((string) $status->norm, (string) $status->edition);
                                @endphp
                                @if ($status->profile_version !== null)
                                    <p>
                                        {{ __('Bewertet gegen Profilversion :version', ['version' => $status->profile_version]) }}
                                        @if ($status->profile_as_of){{ ' ' }}({{ __('Stand') }} {{ $status->profile_as_of->format('d.m.Y') }})@endif
                                        @if ($currentProfile !== null && $currentProfile['version'] !== $status->profile_version)
                                            <x-status-badge tone="warning" size="xs" class="ml-1">{{ __('Profil aktualisiert: :version', ['version' => $currentProfile['version']]) }}</x-status-badge>
                                        @endif
                                    </p>
                                @elseif ($currentProfile !== null)
                                    <p>{{ __('Aktuelle Profilversion: :version', ['version' => $currentProfile['version']]) }}</p>
                                @endif
                                <p class="font-semibold">{{ __('isms.field.certificates') }}:</p>
                                @forelse ($status->certificates as $certificate)
                                    <div class="rounded border border-base-300 p-2 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono">{{ $certificate->certificate_no }}</span>
                                            <span>{{ $certificate->certification_body }}</span>
                                            @if ($certificate->isValidOn($today))
                                                <x-status-badge tone="success" outline>{{ __('isms.conformity.certificate_valid') }}</x-status-badge>
                                            @else
                                                <x-status-badge tone="error" outline>{{ __('isms.conformity.certificate_invalid') }}</x-status-badge>
                                            @endif
                                        </div>
                                        <p>
                                            {{ __('isms.field.certified_organization') }}: {{ $certificate->certified_organization }}
                                            · {{ __('isms.field.issued_on') }}: {{ $certificate->issued_on->format('d.m.Y') }}
                                            · {{ __('isms.field.validity') }}: {{ $certificate->valid_from->format('d.m.Y') }} – {{ $certificate->valid_until->format('d.m.Y') }}
                                        </p>
                                        <p>{{ __('isms.field.scope_description') }}: {{ $certificate->scope_description }}</p>
                                        @if ($certificate->surveillance_audit_1_on !== null || $certificate->surveillance_audit_2_on !== null)
                                            <p class="flex flex-wrap items-center gap-2">
                                                <span>{{ __('isms.field.surveillance') }}:</span>
                                                @foreach ([$certificate->surveillance_audit_1_on, $certificate->surveillance_audit_2_on] as $surveillanceOn)
                                                    @if ($surveillanceOn !== null)
                                                        <span>{{ $surveillanceOn->format('d.m.Y') }}</span>
                                                        @if ($surveillanceOn->gte($today) && $surveillanceOn->lte($today->copy()->addDays(60)))
                                                            <x-status-badge tone="warning" outline>{{ __('isms.conformity.surveillance_soon') }}</x-status-badge>
                                                        @endif
                                                    @endif
                                                @endforeach
                                            </p>
                                        @endif
                                        @if ($certificate->document !== null)
                                            <p>
                                                <a class="link inline-flex items-center gap-1" href="{{ route('documents.download', $certificate->document) }}">
                                                    <x-icon name="picture_as_pdf" />{{ $certificate->document->getAttribute('title') }}
                                                </a>
                                            </p>
                                        @endif
                                        @if ($certificate->notes)
                                            <p>{{ $certificate->notes }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p>{{ __('isms.conformity.empty_certificates') }}</p>
                                @endforelse
                                @can('addCertificate', $status)
                                    <x-icon-btn icon="add" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.conformity.certificates.create', $status)"
                                                show-label>{{ __('isms.action.add_certificate') }}</x-icon-btn>
                                @endcan
                            </div>
                        </details>
                    </td>
                    <td><x-status-badge :tone="$status->status->tone()">{{ $status->status->label() }}</x-status-badge></td>
                    <td class="text-center text-base-content/70">{{ $status->certificates->count() }}</td>
                    <td>
                        @if ($activeCertificate !== null)
                            <span class="{{ $activeCertificate->valid_until->lte($today->copy()->addDays(60)) ? 'text-warning font-semibold' : 'text-base-content/70' }}">
                                {{ $activeCertificate->valid_until->format('d.m.Y') }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($nextSurveillance !== null)
                            <span class="text-base-content/70">{{ $nextSurveillance->format('d.m.Y') }}</span>
                            @if ($nextSurveillance->lte($today->copy()->addDays(60)))
                                <x-status-badge tone="warning" outline>{{ __('isms.conformity.surveillance_soon') }}</x-status-badge>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="max-w-64 truncate text-base-content/70" title="{{ $status->notes }}">{{ $status->notes ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('addCertificate', $status)
                                <x-icon-btn icon="workspace_premium" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('isms.conformity.certificates.create', $status)"
                                            :label="__('isms.action.add_certificate')" />
                            @endcan
                            @can('transition', $status)
                                @if ($status->status->allowedTransitions() !== [])
                                    <details class="dropdown dropdown-end">
                                        <summary class="btn btn-outline btn-xs gap-1" title="{{ __('isms.action.transition') }}">
                                            <x-icon name="swap_horiz" />
                                        </summary>
                                        <ul class="menu dropdown-content z-10 w-64 rounded-box bg-base-100 p-2 shadow">
                                            @foreach ($status->status->allowedTransitions() as $target)
                                                <li>
                                                    @if ($target === \App\Enums\Isms\NormConformityStatus::Certified && $activeCertificate === null)
                                                        {{-- Hinweis statt Aktion: certified nur mit heute gültigem Zertifikat (serverseitig erzwungen). --}}
                                                        <span class="cursor-not-allowed text-muted"
                                                              title="{{ __('isms.conformity.certified_requires_certificate') }}">
                                                            {{ $target->label() }} — {{ __('isms.conformity.certificate_missing_short') }}
                                                        </span>
                                                    @else
                                                        <form method="POST" action="{{ route('isms.conformity.transition', $status) }}">
                                                            @csrf
                                                            <input type="hidden" name="status" value="{{ $target->value }}">
                                                            <button type="submit" class="w-full text-left">{{ $target->label() }}</button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               :title="__('isms.empty_conformity_title')"
                               :message="__('isms.empty_conformity')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
