{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _negotiations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Vertragsverhandlungen (Feature 068, MVP-195): gemeinsame Sektion für
    Ausschreibungs- und Bewerbungsakten.
    Variablen: $negotiations (Collection), $storeRoute (string), $canOpen (bool)
--}}
<x-card :title="__('Vertragsverhandlungen')">
    @if ($canOpen)
        <form method="POST" action="{{ $storeRoute }}" class="mb-3 flex flex-wrap items-end gap-2">
            @csrf
            <input aria-label="{{ __('Titel (z. B. Rahmenvertrag 2027)') }}" name="title" required maxlength="200" class="input input-sm input-bordered flex-1" placeholder="{{ __('Titel (z. B. Rahmenvertrag 2027)') }}">
            <input name="due_on" type="date" class="input input-sm input-bordered" aria-label="{{ __('Frist') }}">
            <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Verhandlung eröffnen') }}</x-icon-btn>
        </form>
    @endif

    @if ($negotiations->isEmpty())
        <x-empty-state icon="handshake" :title="__('Keine Vertragsverhandlung.')" compact />
    @else
        <div class="space-y-4">
            @foreach ($negotiations as $negotiation)
                <div class="rounded-box border border-base-300 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $negotiation->title }}</span>
                        <x-status-badge size="xs" outline>{{ $negotiation->status->label() }}</x-status-badge>
                        @if ($negotiation->due_on)<span class="text-xs text-muted">{{ __('Frist: :date', ['date' => $negotiation->due_on->fdate()]) }}</span>@endif
                        @if ($negotiation->decided_at)<span class="text-xs text-muted">{{ __('Entschieden: :date', ['date' => $negotiation->decided_at->fdatetime()]) }}</span>@endif
                    </div>

                    {{-- Versionen (append-only) --}}
                    <div class="mt-2 text-sm">
                        <span class="font-semibold">{{ __('Versionen:') }}</span>
                        @forelse ($negotiation->versions as $version)
                            <x-status-badge tone="plain" outline>V{{ $version->version }} · {{ __("values.{$version->kind}") }}@if ($version->summary) · {{ \Illuminate\Support\Str::limit($version->summary, 40) }}@endif</x-status-badge>
                        @empty
                            <span class="text-muted">{{ __('noch keine') }}</span>
                        @endforelse
                    </div>

                    {{-- Review-Punkte --}}
                    @if ($negotiation->reviewItems->isNotEmpty())
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($negotiation->reviewItems as $item)
                                @php $itemOpen = $item->status === \App\Enums\Applications\ApplicationContractReviewStatus::Open; @endphp
                                <li class="flex flex-wrap items-center gap-2">
                                    <x-status-badge size="xs" :tone="$item->severity === 'blocker' && $itemOpen ? 'error' : 'ghost'">{{ __("values.{$item->severity}") }}</x-status-badge>
                                    <span @class(['line-through opacity-60' => ! $itemOpen])>{{ $item->label }}</span>
                                    <span class="text-xs text-muted">{{ $item->status->label() }}</span>
                                    @if ($itemOpen && ! $negotiation->isDecided())
                                        @can('update', $negotiation)
                                            <form method="POST" action="{{ route('applications.negotiations.reviews.resolve', [$negotiation, $item->sqid]) }}" class="ml-auto flex items-center gap-1">
                                                @csrf
                                                <select name="resolution" class="select select-xs select-bordered">
                                                    <option value="resolved">{{ __('gelöst') }}</option>
                                                    <option value="accepted">{{ __('akzeptiert') }}</option>
                                                </select>
                                                <x-button type="submit" tone="plain" size="xs">{{ __('Entscheiden') }}</x-button>
                                            </form>
                                        @endcan
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Freigaben: die letzte Runde gilt, frühere stehen als Historie darunter --}}
                    @php
                        $approvalRounds = $negotiation->approvalRounds();
                        $currentRound = $approvalRounds->keys()->last();
                    @endphp
                    <div class="mt-2 text-sm">
                        <span class="font-semibold">{{ $approvalRounds->count() > 1 ? __('Freigaben (Runde :round):', ['round' => $currentRound]) : __('Freigaben:') }}</span>
                        @foreach ($approvalRounds->last() ?? [] as $approval)
                            <x-status-badge :tone="$approval->decision === 'approved' ? 'success' : 'ghost'">
                                {{ __('Stufe :step', ['step' => $approval->step]) }}: {{ $approval->decision !== null ? __("values.{$approval->decision}") : __('offen') }}
                            </x-status-badge>
                        @endforeach
                    </div>
                    @if ($approvalRounds->count() > 1)
                        <details class="mt-1 text-sm">
                            <summary class="cursor-pointer text-muted">{{ __('Frühere Freigaberunden') }}</summary>
                            <ul class="mt-1 space-y-1">
                                @foreach ($approvalRounds->except($currentRound) as $round => $steps)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ __('Runde :round – abgelöst durch Version :version', ['round' => $round, 'version' => $negotiation->supersedingVersion((int) $round)?->version ?? '–']) }}</span>
                                        @foreach ($steps as $approval)
                                            <x-status-badge size="xs" :tone="$approval->decision === 'approved' ? 'success' : 'ghost'">
                                                {{ __('Stufe :step', ['step' => $approval->step]) }}: {{ $approval->decision !== null ? __("values.{$approval->decision}") : __('nicht entschieden') }}
                                            </x-status-badge>
                                            @if ($approval->decided_at)
                                                <span class="text-xs text-muted">{{ $approval->decidedBy?->name ?? '—' }}, {{ $approval->decided_at->fdatetime() }}</span>
                                            @endif
                                        @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @unless ($negotiation->isDecided())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @can('update', $negotiation)
                                <form method="POST" action="{{ route('applications.negotiations.versions.store', $negotiation) }}" class="flex flex-wrap items-end gap-1">
                                    @csrf
                                    <select name="kind" class="select select-xs select-bordered">
                                        <option value="draft">{{ __('Entwurf') }}</option>
                                        <option value="counter">{{ __('Gegenentwurf') }}</option>
                                        <option value="final">{{ __('Endstand') }}</option>
                                    </select>
                                    <input aria-label="{{ __('Zusammenfassung/Änderung') }}" name="summary" maxlength="500" class="input input-xs input-bordered w-52" placeholder="{{ __('Zusammenfassung/Änderung') }}">
                                    <x-button type="submit" tone="plain" size="xs">{{ __('Version ablegen') }}</x-button>
                                </form>
                                <form method="POST" action="{{ route('applications.negotiations.reviews.store', $negotiation) }}" class="flex flex-wrap items-end gap-1">
                                    @csrf
                                    <input aria-label="{{ __('Review-Punkt') }}" name="label" required maxlength="500" class="input input-xs input-bordered w-52" placeholder="{{ __('Review-Punkt') }}">
                                    <select name="severity" class="select select-xs select-bordered">
                                        <option value="info">{{ __('Info') }}</option>
                                        <option value="important">{{ __('Wichtig') }}</option>
                                        <option value="blocker">{{ __('Blocker') }}</option>
                                    </select>
                                    <x-button type="submit" tone="plain" size="xs">{{ __('Erfassen') }}</x-button>
                                </form>
                            @endcan
                            @can('decide', $negotiation)
                                <x-action-form :action="route('applications.negotiations.approve', $negotiation)">
                                    <x-icon-btn icon="verified" tone="info" size="xs" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                                </x-action-form>
                                <x-action-form :action="route('applications.negotiations.conclude', $negotiation)"
                                      :confirm="__('Verhandlung abschließen? Offene Blocker verhindern den Abschluss.')"
                                      confirm-icon="handshake" confirm-tone="primary" :confirm-label="__('Abschließen')">
                                    <input type="hidden" name="decision" value="concluded">
                                    <x-icon-btn icon="handshake" tone="primary" size="xs" type="submit" show-label>{{ __('Abschließen') }}</x-icon-btn>
                                </x-action-form>
                                <x-action-form :action="route('applications.negotiations.conclude', $negotiation)"
                                      :confirm="__('Verhandlung ablehnen/beenden?')"
                                      confirm-icon="block" confirm-tone="warning" :confirm-label="__('Ablehnen')">
                                    <input type="hidden" name="decision" value="declined">
                                    <x-icon-btn icon="block" tone="warning" size="xs" type="submit" show-label>{{ __('Ablehnen') }}</x-icon-btn>
                                </x-action-form>
                            @endcan
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</x-card>
