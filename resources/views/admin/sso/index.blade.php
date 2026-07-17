@extends('layouts.app')
@section('title', __('sso.title'))
@section('nav-title', __('sso.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        <x-validation-errors first />

        {{-- Einführung + Endpunkt --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('sso.title') }}</h1>
            <p class="mb-3 text-sm text-base-content/60">{{ __('sso.intro') }}</p>
            <div class="text-sm">
                <span class="text-base-content/60">{{ __('sso.base_url') }}:</span>
                <code class="rounded bg-base-200 px-2 py-0.5">{{ $scimBaseUrl }}</code>
            </div>
        </div>

        {{-- OIDC-/SAML-Verbindungen (MVP-120/121): eine je Protokoll. --}}
        @foreach ([['protocol' => 'oidc', 'connection' => $oidcConnection], ['protocol' => 'saml', 'connection' => $samlConnection]] as $entry)
            @php($conn = $entry['connection'])
            @php($isOidc = $entry['protocol'] === 'oidc')
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $isOidc ? __('sso.oidc_heading') : __('sso.saml_heading') }}</h2>
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
                <p class="mb-3 text-xs text-base-content/60">{{ $isOidc ? __('sso.oidc_hint') : __('sso.saml_hint') }}</p>

                <div class="mb-3 space-y-1 text-xs">
                    <div><span class="text-base-content/60">{{ __('sso.field.start_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $ssoStartUrl }}</code></div>
                    @if ($isOidc)
                        <div><span class="text-base-content/60">{{ __('sso.field.callback_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $oidcCallbackUrl }}</code></div>
                    @else
                        <div><span class="text-base-content/60">{{ __('sso.field.acs_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $samlAcsUrl }}</code></div>
                        <div><span class="text-base-content/60">{{ __('sso.field.metadata_url') }}:</span> <code class="rounded bg-base-200 px-1.5 py-0.5">{{ $samlMetadataUrl }}</code></div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.sso.connections.save') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="protocol" value="{{ $entry['protocol'] }}">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="form-control">
                            <span class="label-text">{{ __('sso.field.label') }}</span>
                            <input type="text" name="label" value="{{ old('label', $conn->label ?? '') }}" class="input input-bordered input-sm" required>
                        </label>
                        @if ($isOidc)
                            <label class="form-control">
                                <span class="label-text">{{ __('sso.field.issuer') }}</span>
                                <input type="url" name="issuer" value="{{ old('issuer', $conn->issuer ?? '') }}" placeholder="https://login.example.org/realms/firma" class="input input-bordered input-sm">
                            </label>
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
            </div>
        @endforeach

        {{-- Break-Glass: nicht föderierte Notfallkonten, die trotz SSO-Pflicht
             lokal anmelden dürfen (DoD MVP-120). Änderung wird auditiert. --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.break_glass_heading') }}</h2>
            <p class="mb-2 text-xs text-base-content/60">{{ __('sso.break_glass_hint') }}</p>

            @if ($breakGlassUsers->isEmpty())
                <p class="mb-2 text-sm text-base-content/60">{{ __('sso.no_break_glass') }}</p>
            @else
                <ul class="mb-2 space-y-1">
                    @foreach ($breakGlassUsers as $bgUser)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ $bgUser->name }} <span class="text-base-content/60">({{ $bgUser->email }})</span></span>
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
        </div>

        {{-- Einmalige Klartext-Anzeige eines frisch ausgestellten Tokens --}}
        @if ($issuedToken)
            <div class="rounded-box border border-warning/40 bg-warning/10 p-4">
                <div class="mb-1 text-sm font-semibold">{{ __('sso.new_token_heading') }}</div>
                <p class="mb-2 text-xs text-base-content/60">{{ __('sso.new_token_hint') }}</p>
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
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.tokens_heading') }}</h2>
            @if ($tokens->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('sso.no_tokens') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('sso.field.label') }}</th>
                                <th>{{ __('sso.col.status') }}</th>
                                <th>{{ __('sso.col.last_used') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    <td class="text-base-content/60">{{ $token->last_used_at?->diffForHumans() ?? '—' }}</td>
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
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- SCIM-Gruppen → Team (Rang 16): Mitgliederprojektion nach team_user
             passiert NUR bei bewusster Zuordnung; SCIM selbst vergibt nie ein Team. --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('sso.groups_heading') }}</h2>
            <p class="mb-2 text-xs text-base-content/60">{{ __('sso.groups_hint') }}</p>
            @if ($groups->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('sso.no_groups') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('sso.col.group') }}</th>
                                <th>{{ __('sso.col.members') }}</th>
                                <th>{{ __('sso.col.team') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $group)
                                <tr>
                                    <td>{{ $group->display_name }}</td>
                                    <td class="text-base-content/60">{{ count($group->members ?? []) }}</td>
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
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
