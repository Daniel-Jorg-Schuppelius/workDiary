@extends('layouts.app')
@section('title', __('sso.title'))
@section('nav-title', __('sso.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Einführung + Endpunkt --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h1 class="mb-1 font-['Space_Grotesk'] text-lg font-semibold">{{ __('sso.title') }}</h1>
            <p class="mb-3 text-sm text-base-content/60">{{ __('sso.intro') }}</p>
            <div class="text-sm">
                <span class="text-base-content/60">{{ __('sso.base_url') }}:</span>
                <code class="rounded bg-base-200 px-2 py-0.5">{{ $scimBaseUrl }}</code>
            </div>
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
