{{--
    Fernwartungs-Panel für die Asset-Detailansicht. Wird über
    PluginManager::renderSlot('asset-show.aside', $asset) eingebunden.
    Erwartet: $asset, $anydeskId, $teamviewerId
--}}
<x-card>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold">
            <span class="material-symbols-outlined align-middle" aria-hidden="true">support_agent</span>
            {{ __('Fernwartung') }}
        </h2>
        <div class="flex items-center gap-2">
            @if (($pendingCount ?? 0) > 0)
                <a href="{{ route('admin.remote-support.pending.index') }}" class="btn btn-sm btn-warning">
                    <span class="material-symbols-outlined" aria-hidden="true">help</span>
                    {{ trans_choice(':count unzugeordnet|:count unzugeordnet', $pendingCount, ['count' => $pendingCount]) }}
                </a>
            @endif
            <form method="POST" action="{{ route('assets.remote-support.sync', $asset) }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">sync</span>
                    {{ __('Verbindungen importieren') }}
                </button>
            </form>
        </div>
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach ([['anydesk', 'AnyDesk', $anydeskId], ['teamviewer', 'TeamViewer', $teamviewerId]] as [$provider, $label, $currentId])
            <div class="rounded-box border border-base-300 p-3">
                <div class="mb-2 flex items-center justify-between">
                    <span class="font-medium">{{ $label }}</span>
                    @if ($currentId)
                        <form method="POST" action="{{ route('assets.remote-support.forget', [$asset, $provider]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm text-error" title="{{ __('Entfernen') }}">
                                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                            </button>
                        </form>
                    @endif
                </div>
                <form method="POST" action="{{ route('assets.remote-support.id', $asset) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="provider" value="{{ $provider }}">
                    <label class="form-control flex-1">
                        <span class="label-text text-xs">{{ __('Geräte-ID') }}</span>
                        <input type="text" name="remote_id" value="{{ $currentId }}"
                               placeholder="{{ __('z. B. 123 456 789') }}"
                               class="input input-sm input-bordered">
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Speichern') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Mehrkundengerät: Sitzungen einzeln je Kunde zuordnen statt automatisch buchen. --}}
    <form method="POST" action="{{ route('assets.remote-support.shared', $asset) }}" class="mt-3">
        @csrf
        <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 p-3">
            <input type="checkbox" name="shared_remote" value="1" class="toggle toggle-sm toggle-primary mt-0.5"
                   @checked($asset->shared_remote) onchange="this.form.submit()">
            <span>
                <span class="font-medium">{{ __('Mehrkundengerät') }}</span>
                <span class="block text-xs text-base-content/60">
                    {{ __('Dieser Rechner wird für mehrere Kunden genutzt. Sitzungen werden nicht automatisch gebucht, sondern in der Inbox je Sitzung einem Kunden zugeordnet.') }}
                </span>
            </span>
        </label>
    </form>
</x-card>
