{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('sso.title'))
@section('nav-title', __('sso.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('sso.intro')" />

        {{-- Endpunkt für die IdP-Konfiguration --}}
        <x-card>
            <div class="text-sm">
                <span class="text-muted">{{ __('sso.base_url') }}:</span>
                <code class="rounded bg-base-200 px-2 py-0.5">{{ $scimBaseUrl }}</code>
            </div>
        </x-card>

        {{-- OIDC-Verbindungen je Anbieter (custom/Microsoft/Google) + SAML. --}}
        @php
            $entries = [];
            foreach (\App\Enums\Auth\SsoProviderType::cases() as $pt) {
                $entries[] = ['protocol' => 'oidc', 'provider_type' => $pt, 'connection' => $oidcConnections->get($pt->value)];
            }
            $entries[] = ['protocol' => 'saml', 'provider_type' => \App\Enums\Auth\SsoProviderType::Custom, 'connection' => $samlConnection];
        @endphp
        @foreach ($entries as $entry)
            @php($conn = $entry['connection'])
            @php($isOidc = $entry['protocol'] === 'oidc')
            @php($providerType = $entry['provider_type'])
            <x-card>
                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $isOidc ? __('sso.oidc_heading') . ' — ' . $providerType->label() : __('sso.saml_heading') }}</h2>
                    <div class="flex items-center gap-2">
                        @if ($conn)
                            @if ($conn->active)
                                <span class="badge badge-success badge-sm">{{ __('sso.status.active') }}</span>
                            @else
                                <span class="badge badge-ghost badge-sm">{{ __('sso.status.inactive') }}</span>
                            @endif
                            @if ($conn->enforced)
                                <span class="badge badge-warning badge-sm">{{ __('sso.status.enforced') }}</span>
                            @endif
                        @endif
                    </div>
                </div>
                <p class="mb-3 text-xs text-muted">{{ $isOidc ? __('sso.oidc_hint') : __('sso.saml_hint') }}</p>

                <div class="mb-3 space-y-1 text-xs">
                    <div><span class="text-muted">{{ __('sso.field.start_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $ssoStartUrl }}</code></div>
                    @if ($isOidc)
                        <div><span class="text-muted">{{ __('sso.field.callback_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $oidcCallbackUrl }}</code></div>
                    @else
                        <div><span class="text-muted">{{ __('sso.field.acs_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $samlAcsUrl }}</code></div>
                        <div><span class="text-muted">{{ __('sso.field.metadata_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $samlMetadataUrl }}</code></div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.sso.connections.save') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="protocol" value="{{ $entry['protocol'] }}">
                    @if ($isOidc)
                        <input type="hidden" name="provider_type" value="{{ $providerType->value }}">
                    @endif
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="form-control">
                            <span class="label-text">{{ __('sso.field.label') }}</span>
                            <input type="text" name="label" value="{{ old('label', $conn->label ?? '') }}" class="input input-bordered input-sm" required>
                        </label>
                        @if ($isOidc)
                            @if ($providerType === \App\Enums\Auth\SsoProviderType::Microsoft)
                                <label class="form-control">
                                    <span class="label-text">{{ __('sso.field.tenant') }}</span>
                                    <input type="text" name="tenant" value="{{ old('tenant') }}" placeholder="{{ __('sso.field.tenant_placeholder') }}" class="input input-bordered input-sm" autocomplete="off">
                                    <span class="label-text-alt text-muted">{{ $conn?->issuer ? __('sso.field.tenant_keep') : __('sso.field.tenant_hint') }}</span>
                                </label>
                            @elseif ($providerType === \App\Enums\Auth\SsoProviderType::Google)
                                <div class="form-control">
                                    <span class="label-text">{{ __('sso.field.issuer') }}</span>
                                    <code class="mt-1 inline-block rounded bg-base-200 px-2 py-1.5 text-xs">{{ \App\Enums\Auth\SsoProviderType::GOOGLE_ISSUER }}</code>
                                </div>
                            @else
                                <label class="form-control">
                                    <span class="label-text">{{ __('sso.field.issuer') }}</span>
                                    <input type="url" name="issuer" value="{{ old('issuer', $conn->issuer ?? '') }}" placeholder="https://login.example.org/realms/firma" class="input input-bordered input-sm">
                                </label>
                            @endif
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.client_id') }}</span>
                                <input type="text" name="client_id" value="{{ old('client_id', $conn->client_id ?? '') }}" class="input input-bordered input-sm" autocomplete="off">
                            </label>
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.client_secret') }}</span>
                                <input type="password" name="client_secret" value="" placeholder="{{ $conn ? __('sso.field.secret_keep') : '' }}" class="input input-bordered input-sm" autocomplete="new-password">
                            </label>
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.scopes') }}</span>
                                <input type="text" name="scopes" value="{{ old('scopes', $conn->scopes ?? '') }}" placeholder="openid profile email" class="input input-bordered input-sm">
                            </label>
                        @else
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.idp_entity_id') }}</span>
                                <input type="text" name="idp_entity_id" value="{{ old('idp_entity_id', $conn->idp_entity_id ?? '') }}" class="input input-bordered input-sm">
                            </label>
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.idp_sso_url') }}</span>
                                <input type="url" name="idp_sso_url" value="{{ old('idp_sso_url', $conn->idp_sso_url ?? '') }}" class="input input-bordered input-sm">
                            </label>
                            <label class="form-control md:col-span-2">
                                <span class="label-text">{{ __('sso.field.idp_certificate') }}</span>
                                <textarea name="idp_certificate" rows="3" class="textarea textarea-bordered textarea-sm font-mono text-xs" placeholder="-----BEGIN CERTIFICATE-----">{{ old('idp_certificate', $conn->idp_certificate ?? '') }}</textarea>
                            </label>
                            <label class="form-control md:col-span-2">
                                <span class="label-text">{{ __('sso.field.idp_certificate_next') }}</span>
                                <textarea name="idp_certificate_next" rows="3" class="textarea textarea-bordered textarea-sm font-mono text-xs" placeholder="{{ __('sso.field.idp_certificate_next_hint') }}">{{ old('idp_certificate_next', $conn->idp_certificate_next ?? '') }}</textarea>
                            </label>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="active" value="1" class="checkbox checkbox-sm" @checked(old('active', $conn->active ?? false))>
                            {{ __('sso.field.active') }}
                        </label>
                        <label class="flex items-center gap-2" title="{{ __('sso.field.enforced_hint') }}">
                            <input type="checkbox" name="enforced" value="1" class="checkbox checkbox-sm" @checked(old('enforced', $conn->enforced ?? false))>
                            {{ __('sso.field.enforced') }}
                        </label>
                        <label class="flex items-center gap-2" title="{{ __('sso.field.email_link_hint') }}">
                            <input type="checkbox" name="allow_email_link" value="1" class="checkbox checkbox-sm" @checked(old('allow_email_link', $conn->allow_email_link ?? false))>
                            {{ __('sso.field.email_link') }}
                        </label>
                        <label class="flex items-center gap-2" title="{{ __('sso.field.jit_hint') }}">
                            <input type="checkbox" name="jit_provisioning" value="1" class="checkbox checkbox-sm" @checked(old('jit_provisioning', $conn->jit_provisioning ?? false))>
                            {{ __('sso.field.jit') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <span>{{ __('sso.field.jit_role') }}</span>
                            <select name="jit_role" class="select select-bordered select-xs">
                                <option value="">{{ __('sso.field.jit_role_none') }}</option>
                                @foreach ([\App\Enums\User\UserRole::User->value, \App\Enums\User\UserRole::Buchhaltung->value, \App\Enums\User\UserRole::Admin->value] as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('jit_role', $conn->jit_role ?? '') === $roleOption)>{{ $roleOption }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-2" title="{{ __('sso.field.private_network_hint') }}">
                            <input type="checkbox" name="allow_private_network" value="1" class="checkbox checkbox-sm" @checked(old('allow_private_network', $conn->allow_private_network ?? false))>
                            {{ __('sso.field.private_network') }}
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('sso.action.save_connection') }}</button>
                    </div>
                </form>

                @if ($conn)
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('admin.sso.connections.test', $conn->sqid) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-xs">{{ __('sso.action.test_connection') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.sso.connections.destroy', $conn->sqid) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('sso.action.remove_connection') }}</button>
                        </form>
                    </div>
                @endif
            </x-card>
        @endforeach

        {{-- E-Mail-Domain-Discovery: aus der E-Mail-Adresse leitet der Login die
             passende Organisation ab. Domains sind global eindeutig. --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.domains_heading') }}</h2>
            <p class="mb-2 text-xs text-muted">{{ __('sso.domains_hint') }}</p>

            @if ($ssoDomains->isEmpty())
                <p class="mb-2 text-sm text-muted">{{ __('sso.no_domains') }}</p>
            @else
                <ul class="mb-2 space-y-1">
                    @foreach ($ssoDomains as $ssoDomain)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-mono">{{ $ssoDomain->domain }}</span>
                            <form method="POST" action="{{ route('admin.sso.domains.remove', $ssoDomain->sqid) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('sso.action.domain_remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.sso.domains.add') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control">
                    <span class="label-text">{{ __('sso.field.domain') }}</span>
                    <input type="text" name="domain" value="{{ old('domain') }}" placeholder="{{ __('sso.field.domain_placeholder') }}" class="input input-bordered input-sm w-64">
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('sso.action.domain_add') }}</button>
            </form>
        </x-card>

        {{-- Break-Glass: nicht föderierte Notfallkonten, die trotz SSO-Pflicht
             lokal anmelden dürfen (DoD MVP-120). Änderung wird auditiert. --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.break_glass_heading') }}</h2>
            <p class="mb-2 text-xs text-muted">{{ __('sso.break_glass_hint') }}</p>

            @if ($breakGlassUsers->isEmpty())
                <p class="mb-2 text-sm text-muted">{{ __('sso.no_break_glass') }}</p>
            @else
                <ul class="mb-2 space-y-1">
                    @foreach ($breakGlassUsers as $bgUser)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ $bgUser->name }} <span class="text-muted">({{ $bgUser->email }})</span></span>
                            <form method="POST" action="{{ route('admin.sso.break-glass.toggle') }}">
                                @csrf
                                <input type="hidden" name="user" value="{{ $bgUser->sqid }}">
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('sso.action.break_glass_remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.sso.break-glass.toggle') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <label class="form-control grow">
                    <span class="label-text">{{ __('sso.field.break_glass_user') }}</span>
                    <select name="user" class="select select-bordered select-sm" required>
                        <option value="">—</option>
                        @foreach ($eligibleUsers as $eligible)
                            @unless ($eligible->sso_exempt)
                                <option value="{{ $eligible->sqid }}">{{ $eligible->name }} ({{ $eligible->email }})</option>
                            @endunless
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-sm">{{ __('sso.action.break_glass_add') }}</button>
            </form>
        </x-card>

        {{-- Einmalige Klartext-Anzeige eines frisch ausgestellten Tokens --}}
        @if ($issuedToken)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
                <div class="mb-1 text-sm font-semibold">{{ __('sso.new_token_heading') }}</div>
                <p class="mb-2 text-xs text-muted">{{ __('sso.new_token_hint') }}</p>
                <code class="block break-all rounded bg-base-100 px-3 py-2 text-sm">{{ $issuedToken }}</code>
            </div>
        @endif

        {{-- Token ausstellen --}}
        <form method="POST" action="{{ route('admin.sso.tokens.issue') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            @csrf
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.issue_heading') }}</h2>
            <div class="flex flex-wrap items-end gap-2">
                <label class="form-control grow">
                    <span class="label-text">{{ __('sso.field.label') }}</span>
                    <input type="text" name="label" value="{{ old('label') }}"
                           placeholder="{{ __('sso.field.label_placeholder') }}" class="input input-bordered input-sm" required>
                </label>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('sso.action.issue') }}</button>
            </div>
        </form>

        {{-- Token-Liste --}}
        <x-card>
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.tokens_heading') }}</h2>
            @if ($tokens->isEmpty())
                <p class="text-sm text-muted">{{ __('sso.no_tokens') }}</p>
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('sso.field.label') }}</th>
                                <th>{{ __('sso.col.status') }}</th>
                                <th>{{ __('sso.col.last_used') }}</th>
                                <th></th>
                            </tr>
                    </x-slot:head>
                            @foreach ($tokens as $token)
                                <tr>
                                    <td>{{ $token->label }}</td>
                                    <td>
                                        @if ($token->isActive())
                                            <span class="badge badge-success badge-sm">{{ __('sso.status.active') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('sso.status.revoked') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $token->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($token->isActive())
                                            <form method="POST" action="{{ route('admin.sso.tokens.revoke', $token->sqid) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('sso.action.revoke') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>

        {{-- SCIM-Gruppen → Team (Rang 16): Mitgliederprojektion nach team_user
             passiert NUR bei bewusster Zuordnung; SCIM selbst vergibt nie ein Team. --}}
        <x-card>
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.groups_heading') }}</h2>
            <p class="mb-2 text-xs text-muted">{{ __('sso.groups_hint') }}</p>
            @if ($groups->isEmpty())
                <p class="text-sm text-muted">{{ __('sso.no_groups') }}</p>
            @else
                <x-table>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('sso.col.group') }}</th>
                                <th>{{ __('sso.col.members') }}</th>
                                <th>{{ __('sso.col.team') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($groups as $group)
                                <tr>
                                    <td>{{ $group->display_name }}</td>
                                    <td class="text-muted">{{ count($group->members ?? []) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.sso.groups.map', $group->sqid) }}"
                                              class="flex items-center gap-2">
                                            @csrf
                                            <select name="team" class="select select-bordered select-xs">
                                                <option value="">{{ __('sso.field.team_none') }}</option>
                                                @foreach ($teams as $team)
                                                    <option value="{{ $team->sqid }}" @selected($group->team_id === $team->id)>{{ $team->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-ghost btn-xs">{{ __('sso.action.save_mapping') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</x-page-shell>
@endsection
