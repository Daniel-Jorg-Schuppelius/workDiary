@extends('layouts.app')
@section('title', __('Fernwartung – unzugeordnete Verbindungen'))
@section('nav-title', __('Fernwartung – Inbox'))

@section('content')
<x-index-page :subtitle="__('Diese AnyDesk-/TeamViewer-IDs tauchten in den Reports auf, sind aber keinem Gerät zugeordnet. Weise jede ID einem bestehenden Gerät zu oder lege ein neues an — die gespeicherten Sitzungen werden dann sofort als Zeiteinträge gebucht.')">
    <x-slot:actions>
        <a href="{{ route('admin.imports.create', ['entity' => \App\Enums\Import\ImportEntity::RemoteSessions->value]) }}"
           class="btn btn-sm btn-primary">
            {{ __('Sitzungen importieren') }}
        </a>
    </x-slot:actions>

    <x-slot:note>
        <span class="material-symbols-outlined align-middle" aria-hidden="true">upload_file</span>
        {{ __('AnyDesk-Sitzungen werden im zentralen Import-Wizard eingelesen.') }}
    </x-slot:note>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">

        @if ($groups->isEmpty())
            <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine offenen Verbindungen. Alles zugeordnet.') }}
            </p>
        @else
            <div class="space-y-3">
                @foreach ($groups as $group)
                    <div class="relative rounded-box border border-base-300 p-3">
                        {{-- Verwerfen: Icon oben rechts --}}
                        <form method="POST" action="{{ route('admin.remote-support.pending.dismiss') }}"
                              class="absolute right-2 top-2"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Diese Verbindungen verwerfen? Sie werden nicht gebucht.') }}">
                            @csrf
                            <input type="hidden" name="provider" value="{{ $group->provider }}">
                            <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                            <button type="submit" class="btn btn-ghost btn-sm btn-square text-base-content/50 hover:text-error"
                                    title="{{ __('Verwerfen') }}" aria-label="{{ __('Verwerfen') }}">
                                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                            </button>
                        </form>

                        <div class="mb-3 pr-10">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge tone="neutral" size="md">{{ ucfirst($group->provider) }}</x-status-badge>
                                <span class="font-mono text-base font-semibold">{{ $group->remote_id }}</span>
                                @if ($group->alias)
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-base-content/80">
                                        <span class="material-symbols-outlined text-[1rem] align-middle" aria-hidden="true">badge</span>{{ $group->alias }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-base-content/60">
                                {{ trans_choice(':count Sitzung|:count Sitzungen', $group->count, ['count' => $group->count]) }},
                                {{ $group->minutes }} {{ __('Min.') }} ·
                                {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                            </div>
                            @if (! empty($group->notes))
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($group->notes as $note)
                                        <span class="inline-flex items-center gap-1 rounded-box bg-base-200 px-2 py-0.5 text-xs text-base-content/70">
                                            <span class="material-symbols-outlined text-[0.9rem] align-middle" aria-hidden="true">sticky_note_2</span>{{ $note }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Zuordnung: Tabs zwischen bestehendem und neuem Gerät --}}
                        @php $tabName = 'assign_'.md5($group->provider.'|'.$group->remote_id); @endphp
                        <div class="tabs tabs-box tabs-sm bg-base-200/50 p-2">
                            <input type="radio" name="{{ $tabName }}" class="tab" aria-label="{{ __('Bestehendes Gerät') }}" checked />
                            <div class="tab-content pt-3">
                                <form method="POST" action="{{ route('admin.remote-support.pending.assign-existing') }}"
                                      class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $group->provider }}">
                                    <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                    <label class="flex w-72 max-w-full flex-col gap-1">
                                        <span class="label-text text-xs">{{ __('Gerät auswählen') }}</span>
                                        <select name="asset_id" required class="select select-sm select-bordered w-full">
                                            <option value="">{{ __('— Gerät wählen —') }}</option>
                                            @foreach ($assets as $asset)
                                                <option value="{{ $asset->sqid }}">{{ $asset->name ?: $asset->asset_no }} ({{ $asset->asset_no }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="btn btn-sm btn-primary ml-auto">
                                        <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">link</span>{{ __('Zuordnen') }}
                                    </button>
                                </form>
                            </div>

                            <input type="radio" name="{{ $tabName }}" class="tab" aria-label="{{ __('Neues Gerät') }}" />
                            <div class="tab-content pt-3">
                                <form method="POST" action="{{ route('admin.remote-support.pending.assign-new') }}"
                                      class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $group->provider }}">
                                    <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                    <label class="flex w-48 flex-col gap-1">
                                        <span class="label-text text-xs">{{ __('Name') }}</span>
                                        <input type="text" name="name" required value="{{ $group->alias }}" placeholder="{{ __('z. B. PC Empfang') }}"
                                               class="input input-sm input-bordered w-full">
                                    </label>
                                    <label class="flex w-40 flex-col gap-1">
                                        <span class="label-text text-xs">{{ __('Kategorie') }}</span>
                                        <select name="category_code" required class="select select-sm select-bordered w-full">
                                            @foreach ($categories as $code => $label)
                                                <option value="{{ $code }}" @selected($code === 'workstation')>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="flex w-48 flex-col gap-1">
                                        <span class="label-text text-xs">{{ __('Kunde') }}</span>
                                        <select name="customer_id" required class="select select-sm select-bordered w-full">
                                            <option value="">{{ __('— Kunde —') }}</option>
                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->sqid }}">{{ $customer->company ?: $customer->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="btn btn-sm btn-primary ml-auto">
                                        <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">add</span>{{ __('Anlegen & zuordnen') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if (! $shared->isEmpty())
        <div class="mt-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Mehrkundengeräte – Sitzungen zuordnen') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Diese Rechner werden für mehrere Kunden genutzt. Markiere die Sitzungen, wähle den Kunden (und optional ein Projekt) und buche sie gesammelt.') }}
                </p>
            </div>

            <div class="space-y-4">
                @foreach ($shared as $device)
                    @php $assetName = $device->asset->name ?: $device->asset->asset_no; @endphp
                    <form method="POST" action="{{ route('admin.remote-support.pending.assign-shared') }}"
                          class="rounded-box border border-base-300 p-3"
                          x-data="{
                              customer: '',
                              projectMap: @js($projectMap),
                              get projects() { return this.projectMap[this.customer] ?? []; },
                              allChecked: false,
                              toggleAll() {
                                  this.$refs.list.querySelectorAll('input[type=checkbox][name=&quot;pending_ids[]&quot;]')
                                      .forEach(cb => cb.checked = this.allChecked);
                              },
                          }">
                        @csrf

                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="material-symbols-outlined align-middle text-base-content/70" aria-hidden="true">computer</span>
                            <span class="font-semibold">{{ $assetName }}</span>
                            <x-status-badge tone="neutral" size="sm">{{ $device->asset->asset_no }}</x-status-badge>
                            <span class="text-sm text-base-content/60">
                                {{ trans_choice(':count Sitzung|:count Sitzungen', $device->sessions->count(), ['count' => $device->sessions->count()]) }}
                            </span>
                        </div>

                        <div class="overflow-x-auto" x-ref="list">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th class="w-8">
                                            <input type="checkbox" class="checkbox checkbox-sm"
                                                   x-model="allChecked" @change="toggleAll()"
                                                   aria-label="{{ __('Alle auswählen') }}">
                                        </th>
                                        <th>{{ __('Zeitraum') }}</th>
                                        <th class="text-right">{{ __('Min.') }}</th>
                                        <th>{{ __('Provider') }}</th>
                                        <th>{{ __('Notiz') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($device->sessions as $session)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="pending_ids[]" value="{{ $session->sqid }}"
                                                       class="checkbox checkbox-sm"
                                                       aria-label="{{ __('Sitzung auswählen') }}">
                                            </td>
                                            <td class="whitespace-nowrap text-sm">
                                                {{ \Illuminate\Support\Carbon::parse($session->started_at)->isoFormat('L HH:mm') }}
                                                – {{ \Illuminate\Support\Carbon::parse($session->ended_at)->isoFormat('HH:mm') }}
                                            </td>
                                            <td class="text-right text-sm">{{ $session->minutes() }}</td>
                                            <td class="text-sm">{{ ucfirst($session->provider) }}</td>
                                            <td class="text-sm text-base-content/70">{{ $session->note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <label class="flex w-48 flex-col gap-1">
                                <span class="label-text text-xs">{{ __('Kunde') }}</span>
                                <select name="customer_id" required x-model="customer" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('— Kunde —') }}</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->sqid }}">{{ $customer->company ?: $customer->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex w-48 flex-col gap-1">
                                <span class="label-text text-xs">{{ __('Projekt') }}</span>
                                <select name="project_id" class="select select-sm select-bordered w-full" :disabled="! customer">
                                    <option value="">{{ __('— Standardprojekt —') }}</option>
                                    <template x-for="p in projects" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-sm btn-primary ml-auto">
                                <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">schedule</span>{{ __('Markierte buchen') }}
                            </button>
                            <button type="submit" formaction="{{ route('admin.remote-support.pending.dismiss-session') }}"
                                    class="btn btn-sm btn-ghost text-error"
                                    formnovalidate
                                    data-confirm-dialog
                                    data-confirm-message="{{ __('Markierte Sitzungen verwerfen? Sie werden nicht gebucht.') }}">
                                <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">delete</span>{{ __('Markierte verwerfen') }}
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</x-index-page>
@endsection
