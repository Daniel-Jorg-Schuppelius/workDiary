{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Panel „Externe Beteiligte" (Feature 033) für die Subject-Detailseite
  (Auftrag/Protokoll/Dokument). Listet Eingeladene mit Status und bietet
  Einladen (Modal) sowie Widerruf.
  Variablen: $subject (Model), $externalType ('diary'|'protocol'|'document')
--}}

@php
    use App\Models\ExternalParticipant;
    $canManage = auth()->check() && \Illuminate\Support\Facades\Gate::allows('manageForSubject', [ExternalParticipant::class, $subject]);
    $participants = $subject->relationLoaded('externalParticipants')
        ? $subject->externalParticipants
        : ExternalParticipant::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest('id')
            ->get();
@endphp

@if ($canManage || $participants->isNotEmpty())
    <section class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="badge" /> {{ __('external.panel.title') }}
            </h2>
            @if ($canManage)
                <x-icon-btn icon="person_add" tone="primary" size="sm" show-label
                            data-entry-modal-trigger
                            :href="route('external.create', ['type' => $externalType, 'id' => $subject->getRouteKey()])">
                    {{ __('external.panel.invite') }}
                </x-icon-btn>
            @endif
        </div>

        {{-- Einmalige Anzeige des vollständigen externen Links. --}}
        @if (session('external_participant_link'))
            <div class="alert alert-warning bg-warning/10 border-warning/40 text-sm" role="alert">
                <x-icon name="link" />
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="font-semibold">{{ __('external.panel.link_once') }}</p>
                    <input type="text" readonly
                           class="input input-sm input-bordered w-full font-mono text-xs"
                           value="{{ session('external_participant_link') }}"
                           onclick="this.select()">
                </div>
            </div>
        @endif

        @if ($participants->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('external.panel.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('external.col.name') }}</th>
                            <th>{{ __('external.col.party') }}</th>
                            <th>{{ __('external.col.abilities') }}</th>
                            <th>{{ __('external.col.status') }}</th>
                            <th>{{ __('external.col.expires') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participants as $p)
                            <tr>
                                <td>
                                    <span class="font-medium">{{ $p->name }}</span>
                                    @if ($p->role)<span class="block text-xs text-base-content/60">{{ $p->role }}</span>@endif
                                </td>
                                <td>{{ $p->party->label() }}</td>
                                <td>
                                    <span class="text-xs">{{ __('external.ability.view') }}</span>
                                    @foreach ((array) $p->abilities as $ab)
                                        <span class="badge badge-ghost badge-sm">{{ __('external.ability.' . $ab) }}</span>
                                    @endforeach
                                </td>
                                <td><span class="badge badge-sm">{{ __('external.status.' . $p->status()) }}</span></td>
                                <td class="text-xs">{{ $p->expires_at?->fdate() }}</td>
                                <td class="text-right">
                                    @if ($canManage && $p->revoked_at === null)
                                        <form method="POST" action="{{ route('external.revoke', $p) }}" class="inline"
                                              data-confirm-dialog
                                              data-confirm-title="{{ __('external.revoke.title') }}"
                                              data-confirm-message="{{ __('external.revoke.message') }}"
                                              data-confirm-label="{{ __('external.revoke.confirm') }}">
                                            @csrf
                                            <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" show-label>{{ __('external.revoke.action') }}</x-icon-btn>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
