{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auditpakete (Feature 046, Inkrement E): stichtagsbezogene, integritäts-
  geschützte JSON-Exportpakete je Geltungsbereich. Liste mit Status-Badge,
  gekürztem SHA-256, Finalisieren (confirm), Integritätsprüfung (POST,
  Flash-Ergebnis), internem Download und aufklappbarem Prüfer-Link-Bereich
  (Token-Liste + „Prüfer-Link erstellen"; der vollständige Link wird nach
  der Erstellung genau EINMAL als Flash angezeigt).
  Variablen: $packages, $canManage
--}}

@extends('layouts.app')

@section('title', __('isms.title.packages'))
@section('nav-title', __('isms.title.packages'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.packages')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.packages.create')"
                            show-label>{{ __('isms.action.create_package') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        {{-- Einmalige Anzeige des vollständigen Prüfer-Links (Klartext-Token
             wird nicht gespeichert — nur hier kopierbar). --}}
        @if (session('isms_package_token_url'))
            <div class="alert alert-warning bg-warning/10 border-warning/40 text-sm" role="alert">
                <x-icon name="key" />
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="font-semibold">{{ __('isms.package.token_url_once') }}</p>
                    <input type="text" readonly
                           class="input input-sm input-bordered w-full font-mono text-xs"
                           value="{{ session('isms_package_token_url') }}"
                           onclick="this.select()">
                </div>
            </div>
        @endif

        {{-- Ehrliche Stichtags-Semantik als sichtbarer Hinweis. --}}
        <div class="alert alert-info bg-info/10 border-info/30 text-sm" role="note">
            <x-icon name="history_toggle_off" />
            <span>{{ __('isms.package.as_of_note') }}</span>
        </div>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.package_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.scope') }}</th>
                    <th>{{ __('isms.field.as_of_date') }}</th>
                    <th>{{ __('isms.field.norm') }}</th>
                    <th>{{ __('isms.field.status') }}</th>
                    <th>{{ __('isms.field.file_hash') }}</th>
                    <th>{{ __('isms.field.finalized_by') }}</th>
                    <th class="text-center">{{ __('isms.field.tokens') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($packages as $package)
                @php
                    /** @var \App\Models\Isms\IsmsAuditPackage $package */
                    $activeTokens = $package->tokens->filter(fn(\App\Models\Isms\IsmsAuditPackageToken $t): bool => $t->isUsable())->count();
                @endphp
                <tr class="hover" id="isms-package-{{ $package->id }}">
                    <td class="font-mono">{{ $package->displayNo() }}</td>
                    <td class="font-medium">{{ $package->title }}</td>
                    <td class="text-base-content/70">{{ $package->scope?->name ?? '—' }}</td>
                    <td>{{ $package->as_of_date->format('d.m.Y') }}</td>
                    <td class="text-base-content/70">{{ $package->normLabel() ?? __('isms.package.norm_all') }}</td>
                    <td><x-status-badge :tone="$package->status->tone()">{{ $package->status->label() }}</x-status-badge></td>
                    <td>
                        @if ($package->file_hash !== null)
                            <span class="font-mono text-xs" title="{{ $package->file_hash }}">{{ substr($package->file_hash, 0, 12) }}…</span>
                        @else
                            <span class="text-base-content/50">—</span>
                        @endif
                    </td>
                    <td class="text-base-content/70 text-xs">
                        @if ($package->isFinalized())
                            {{ $package->finalizedBy?->name ?? '—' }}<br>
                            {{ $package->finalized_at?->format('d.m.Y H:i') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($package->tokens->isEmpty())
                            <span class="text-base-content/50">—</span>
                        @else
                            <details>
                                <summary class="cursor-pointer text-base-content/70">{{ $activeTokens }} / {{ $package->tokens->count() }}</summary>
                                <div class="mt-2 space-y-2 text-left text-xs text-base-content/70">
                                    @foreach ($package->tokens as $token)
                                        <div class="rounded border border-base-300 p-2 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium">{{ $token->label }}</span>
                                                @if ($token->revoked_at !== null)
                                                    <x-status-badge tone="error" outline>{{ __('isms.package.token_revoked') }}</x-status-badge>
                                                @elseif (! $token->expires_at->isFuture())
                                                    <x-status-badge tone="warning" outline>{{ __('isms.package.token_expired') }}</x-status-badge>
                                                @else
                                                    <x-status-badge tone="success" outline>{{ __('isms.package.token_active') }}</x-status-badge>
                                                @endif
                                            </div>
                                            <p>
                                                {{ __('isms.field.token_expires_at') }}: {{ $token->expires_at->format('d.m.Y H:i') }}
                                                · {{ __('isms.field.token_last_accessed') }}:
                                                {{ $token->last_accessed_at?->format('d.m.Y H:i') ?? '—' }}
                                            </p>
                                            @if ($canManage && $token->revoked_at === null)
                                                <x-action-form :action="route('isms.packages.tokens.revoke', $token)"
                                                      data-confirm-title="{{ __('isms.action.revoke_token') }}"
                                                      :confirm="__('isms.confirm_revoke_token')"
                                                      confirm-icon="link_off"
                                                      confirm-tone="error"
                                                      :confirm-label="__('isms.action.revoke_token')">
                                                    <x-icon-btn icon="link_off" tone="outline" size="xs" type="submit"
                                                                show-label>{{ __('isms.action.revoke_token') }}</x-icon-btn>
                                                </x-action-form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($package->isFinalized())
                                @can('download', $package)
                                    <x-icon-btn icon="download" tone="outline" size="xs"
                                                :href="route('isms.packages.download', $package)"
                                                :label="__('isms.action.download_package')" />
                                @endcan
                                @can('verify', $package)
                                    <form method="POST" action="{{ route('isms.packages.verify', $package) }}">
                                        @csrf
                                        <x-icon-btn icon="verified" tone="outline" size="xs" type="submit"
                                                    :label="__('isms.action.verify_package')" />
                                    </form>
                                @endcan
                                @can('manageTokens', $package)
                                    <x-icon-btn icon="key" tone="outline" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('isms.packages.tokens.create', $package)"
                                                :label="__('isms.action.create_token')" />
                                @endcan
                            @else
                                @can('finalize', $package)
                                    <x-action-form :action="route('isms.packages.finalize', $package)"
                                          data-confirm-title="{{ __('isms.action.finalize_package') }}"
                                          :confirm="__('isms.confirm_finalize_package')"
                                          confirm-icon="lock"
                                          confirm-tone="primary"
                                          :confirm-label="__('isms.action.finalize_package')">
                                        <x-icon-btn icon="lock" tone="primary" size="xs" type="submit"
                                                    :label="__('isms.action.finalize_package')" />
                                    </x-action-form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="10"
                               :title="__('isms.empty_packages_title')"
                               :message="__('isms.empty_packages')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
