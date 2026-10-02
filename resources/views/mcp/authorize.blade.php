{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : authorize.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zustimmung zum Zugriff eines KI-Assistenten (MVP-1065). Erwartet: $client, $scopes, $params, $organization, $redirectTarget, $trusted --}}
@extends('layouts.app')

@section('title', __('mcp.oauth.title'))
@section('nav-title', __('mcp.oauth.title'))

@section('content')
<x-page-shell gap="4">
    <div class="mx-auto w-full max-w-xl">
        <x-card :title="__('mcp.oauth.title')" icon="smart_toy">
            <form method="POST" action="{{ route('mcp.oauth.approve') }}" class="space-y-4">
                @csrf
                @foreach ($params as $key => $value)
                    @if (is_string($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <p>{{ __('mcp.oauth.intro', ['client' => $client->name, 'user' => auth()->user()->name, 'organization' => $organization->name]) }}</p>
                <div class="rounded-box border p-3 {{ $trusted ? 'border-base-300' : 'border-warning bg-warning/10' }}">
                    <p class="text-xs text-muted">{{ __('mcp.oauth.target') }}</p>
                    <p class="text-lg font-semibold break-all">{{ $redirectTarget }}</p>
                    @unless ($trusted)
                        <p class="mt-2 text-sm">{{ __('mcp.oauth.untrusted_warning') }}</p>
                        <label class="mt-2 flex items-start gap-2 text-sm">
                            <input type="checkbox" name="confirm_untrusted" value="1" class="checkbox checkbox-sm checkbox-warning mt-0.5">
                            <span>{{ __('mcp.oauth.untrusted_confirm', ['target' => $redirectTarget]) }}</span>
                        </label>
                    @endunless
                </div>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <x-icon name="visibility" class="text-primary" />
                        <span>{{ __('mcp.oauth.scope.read') }}</span>
                    </li>
                    @if (in_array(\App\Enums\Api\ApiAbility::McpWrite->value, $scopes, true))
                        <li>
                            <label class="flex items-start gap-2">
                                <input type="checkbox" name="grant_write" value="1" class="checkbox checkbox-sm mt-0.5" checked>
                                <span>{{ __('mcp.oauth.scope.write') }}</span>
                            </label>
                        </li>
                    @endif
                </ul>
                <p class="text-xs text-muted">{{ __('mcp.oauth.revoke_hint') }}</p>
                <div class="flex justify-end gap-2">
                    <x-button type="submit" name="decision" value="deny" tone="ghost">{{ __('mcp.oauth.deny') }}</x-button>
                    <x-button type="submit" name="decision" value="approve" tone="primary">{{ __('mcp.oauth.approve') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-page-shell>
@endsection
