@extends('layouts.app')
@section('title', __('Fernwartung – unzugeordnete Verbindungen'))
@section('nav-title', __('Fernwartung – Inbox'))

@section('content')
<x-page-shell>
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div class="mb-3">
            <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Verbindungen ohne zugeordnetes Gerät') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Diese AnyDesk-/TeamViewer-IDs tauchten in den Reports auf, sind aber keinem Gerät zugeordnet. Weise jede ID einem bestehenden Gerät zu oder lege ein neues an — die gespeicherten Sitzungen werden dann sofort als Zeiteinträge gebucht.') }}
            </p>
        </div>

        @if ($groups->isEmpty())
            <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine offenen Verbindungen. Alles zugeordnet.') }}
            </p>
        @else
            <div class="space-y-3">
                @foreach ($groups as $group)
                    <div class="rounded-box border border-base-300 p-3">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="badge badge-neutral">{{ ucfirst($group->provider) }}</span>
                                <span class="ml-2 font-mono text-base font-semibold">{{ $group->remote_id }}</span>
                                <span class="ml-2 text-sm text-base-content/60">
                                    {{ trans_choice(':count Sitzung|:count Sitzungen', $group->count, ['count' => $group->count]) }},
                                    {{ $group->minutes }} {{ __('Min.') }} ·
                                    {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                                </span>
                                @if ($group->note)
                                    <div class="text-xs text-base-content/50">{{ $group->note }}</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.remote-support.pending.dismiss') }}"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Diese Verbindungen verwerfen? Sie werden nicht gebucht.') }}">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $group->provider }}">
                                <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Verwerfen') }}</button>
                            </form>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            {{-- Bestehendes Gerät zuordnen --}}
                            <form method="POST" action="{{ route('admin.remote-support.pending.assign-existing') }}"
                                  class="flex items-end gap-2 rounded-box bg-base-200/50 p-2">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $group->provider }}">
                                <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                <label class="form-control flex-1">
                                    <span class="label-text text-xs">{{ __('Bestehendem Gerät zuordnen') }}</span>
                                    <select name="asset_id" required class="select select-sm select-bordered">
                                        <option value="">{{ __('— Gerät wählen —') }}</option>
                                        @foreach ($assets as $asset)
                                            <option value="{{ $asset->id }}">{{ $asset->name ?: $asset->asset_no }} ({{ $asset->asset_no }})</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Zuordnen') }}</button>
                            </form>

                            {{-- Neues Gerät anlegen --}}
                            <form method="POST" action="{{ route('admin.remote-support.pending.assign-new') }}"
                                  class="rounded-box bg-base-200/50 p-2 space-y-2">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $group->provider }}">
                                <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                <span class="label-text text-xs">{{ __('Neues Gerät anlegen') }}</span>
                                <div class="flex items-end gap-2">
                                    <label class="form-control flex-1">
                                        <span class="label-text text-xs">{{ __('Name') }}</span>
                                        <input type="text" name="name" required placeholder="{{ __('z. B. PC Empfang') }}"
                                               class="input input-sm input-bordered">
                                    </label>
                                    <label class="form-control flex-1">
                                        <span class="label-text text-xs">{{ __('Kunde') }}</span>
                                        <select name="customer_id" required class="select select-sm select-bordered">
                                            <option value="">{{ __('— Kunde —') }}</option>
                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->id }}">{{ $customer->company ?: $customer->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="btn btn-sm">{{ __('Anlegen & zuordnen') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
