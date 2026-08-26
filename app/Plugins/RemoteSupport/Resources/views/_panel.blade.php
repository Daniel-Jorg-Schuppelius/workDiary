{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Fernwartungs-Panel für die Asset-Detailansicht. Wird über
    PluginManager::renderSlot('asset-show.aside', $asset) eingebunden.
    Erwartet: $asset, $anydeskId, $teamviewerId
--}}
<x-card>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold">
            <x-icon name="support_agent" class="align-middle" />
            {{ __('Fernwartung') }}
        </h2>
        <div class="flex items-center gap-2">
            @if (($pendingCount ?? 0) > 0)
                <a href="{{ route('admin.remote-support.pending.index') }}" class="btn btn-sm btn-warning">
                    <x-icon name="help" />
                    {{ trans_choice(':count unzugeordnet|:count unzugeordnet', $pendingCount, ['count' => $pendingCount]) }}
                </a>
            @endif
            <form method="POST" action="{{ route('assets.remote-support.sync', $asset) }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-sm">
                    <x-icon name="sync" />
                    {{ __('Verbindungen importieren') }}
                </button>
            </form>
        </div>
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach ([['anydesk', 'AnyDesk', $anydeskIds], ['teamviewer', 'TeamViewer', $teamviewerIds]] as [$provider, $label, $ids])
            <div class="rounded-box border border-base-300 p-3">
                <div class="mb-2 font-medium">{{ $label }}</div>

                {{-- Ein Gerät kann mehrere IDs tragen (Neuinstallation, Zweitinstanz). --}}
                @if ($ids !== [])
                    <ul class="mb-2 space-y-1">
                        @foreach ($ids as $id)
                            <li class="flex items-center justify-between gap-2">
                                <span class="font-mono text-sm">{{ $id }}</span>
                                <form method="POST" action="{{ route('assets.remote-support.forget', [$asset, $provider]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="remote_id" value="{{ $id }}">
                                    <button type="submit" class="btn btn-ghost btn-sm btn-square text-error"
                                            title="{{ __('Entfernen') }}" aria-label="{{ __('Entfernen') }}">
                                        <x-icon name="delete" class="text-[1.1rem]" />
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('assets.remote-support.id', $asset) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="provider" value="{{ $provider }}">
                    <label class="form-control flex-1">
                        <span class="label-text text-xs">{{ $ids === [] ? __('Geräte-ID') : __('Weitere Geräte-ID') }}</span>
                        <input type="text" name="remote_id" required
                               placeholder="{{ __('z. B. 123 456 789') }}"
                               class="input input-sm input-bordered">
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Hinzufügen') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Mehrkundengerät: Sitzungen einzeln je Kunde zuordnen statt automatisch buchen. --}}
    <form method="POST" action="{{ route('assets.remote-support.shared', $asset) }}" class="mt-3">
        @csrf
        <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 p-3">
            <input type="checkbox" name="shared_remote" value="1" class="toggle toggle-sm toggle-primary mt-0.5"
                   @checked($asset->shared_remote) data-autosubmit>
            <span>
                <span class="font-medium">{{ __('Mehrkundengerät') }}</span>
                <span class="block text-xs text-muted">
                    {{ __('Dieser Rechner wird für mehrere Kunden genutzt. Sitzungen werden nicht automatisch gebucht, sondern in der Inbox je Sitzung einem Kunden zugeordnet.') }}
                </span>
            </span>
        </label>
    </form>

    {{-- Duplikat-Bereinigung: IDs + Pending-Sitzungen auf ein anderes Gerät überführen. --}}
    @if (($mergeTargets ?? collect())->isNotEmpty() && ($anydeskIds !== [] || $teamviewerIds !== []))
        <form method="POST" action="{{ route('assets.remote-support.merge', $asset) }}" class="mt-3"
              data-confirm-dialog
              data-confirm-message="{{ __('Alle Geräte-IDs und offenen Sitzungen dieses Geräts auf das gewählte Gerät übertragen?') }}">
            @csrf
            <div class="rounded-box border border-base-300 p-3">
                <span class="font-medium">{{ __('Fernwartungsdaten übertragen') }}</span>
                <span class="block text-xs text-muted">
                    {{ __('Für doppelt angelegte Geräte: verschiebt alle Geräte-IDs und Pending-Sitzungen auf das Zielgerät. Das leere Duplikat kann danach archiviert werden.') }}
                </span>
                <div class="mt-2 flex items-end gap-2">
                    <label class="form-control flex-1">
                        <span class="label-text text-xs">{{ __('Zielgerät') }}</span>
                        <select name="target_asset_id" required class="select select-sm select-bordered w-full">
                            <option value="">{{ __('— Gerät wählen —') }}</option>
                            @foreach ($mergeTargets as $target)
                                <option value="{{ $target->sqid }}">{{ $target->name ?: $target->asset_no }} ({{ $target->asset_no }})</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-sm">
                        <x-icon name="move_up" class="text-[1.1rem]" />{{ __('Übertragen') }}
                    </button>
                </div>
            </div>
        </form>
    @endif
</x-card>
