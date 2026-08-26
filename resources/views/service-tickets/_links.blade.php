{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _links.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verknüpfungs-Widget (Feature 065, MVP-160): zeigt alle ServiceTicketLinks;
  linked_type NIE als roher Morph-Klassenname (EntityType::label), kind über
  Trans::or. Erwartet: $ticket (mit links.linked geladen), $canUpdate.
--}}
@php
    $linkKindLabels = [
        'related' => __('Verwandt'),
        'duplicate' => __('Duplikat'),
        'parent' => __('Übergeordnet'),
        'security' => __('Sicherheitsvorfall'),
        'privacy' => __('Datenschutz'),
    ];
@endphp

@php
    // Problem eröffnen (MVP-156): nur für Incidents und nur mit
    // service_desk.problem.manage — öffnet das Problem-Modal mit
    // vorbelegtem Incident (ProblemService::openFromIncidents()).
    $canOpenProblem = $ticket->kind === \App\Enums\ServiceTicket\ServiceTicketKind::Incident
        && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Problem::class);
@endphp

<x-card :title="__('Verknüpfungen')" icon="link">
    @if ($canUpdate || $canOpenProblem)
        <x-slot:actions>
            @if ($canOpenProblem)
                <x-icon-btn icon="troubleshoot" size="sm"
                            data-entry-modal-trigger
                            :href="route('servicedesk.problems.create', ['incidents' => [$ticket->sqid]])"
                            show-label>{{ __('Problem eröffnen') }}</x-icon-btn>
            @endif
            @if ($canUpdate)
                <x-icon-btn icon="add_link" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('helpdesk.tickets.links.create', $ticket)"
                            show-label>{{ __('Verknüpfen') }}</x-icon-btn>
            @endif
        </x-slot:actions>
    @endif

    @if ($ticket->links->isEmpty())
        <p class="text-sm text-muted">{{ __('Noch keine Verknüpfungen.') }}</p>
    @else
        <ul class="space-y-1 text-sm">
            @foreach ($ticket->links as $link)
                <li class="flex flex-wrap items-center gap-2">
                    <x-status-badge tone="ghost" size="xs" outline>
                        {{ \App\Support\Trans::or('helpdesk.link.kind.' . $link->kind, $linkKindLabels[$link->kind] ?? $link->kind) }}
                    </x-status-badge>
                    <span class="text-muted">{{ \App\Support\EntityType::label($link->linked_type) }}</span>
                    @if ($link->linked instanceof \App\Models\ServiceTicket)
                        <a href="{{ route('service-tickets.show', $link->linked) }}" class="link link-hover">
                            <span class="font-mono text-xs">{{ $link->linked->ticket_no }}</span>
                            {{ $link->linked->title }}
                        </a>
                    @else
                        <span>{{ $link->linked?->getAttribute('title') ?? $link->linked?->getAttribute('name') ?? '#' . $link->linked_id }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($ticket->problems->isNotEmpty())
        <div class="mt-3">
            <div class="text-xs uppercase text-muted mb-1">{{ __('Zugeordnete Probleme') }}</div>
            <ul class="space-y-1 text-sm">
                @foreach ($ticket->problems as $problem)
                    <li>
                        <a href="{{ route('servicedesk.problems.show', $problem) }}" class="link link-hover">{{ $problem->title }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($ticket->changes->isNotEmpty())
        {{-- Changes am Ticket (MVP-157, change_ticket-Pivot) — rein informativ. --}}
        <div class="mt-3">
            <div class="text-xs uppercase text-muted mb-1">{{ __('Zugeordnete Changes') }}</div>
            <ul class="space-y-1 text-sm">
                @foreach ($ticket->changes as $change)
                    <li class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('servicedesk.changes.show', $change) }}" class="link link-hover">{{ $change->title }}</a>
                        <x-status-badge size="xs" outline>{{ \App\Http\Controllers\Helpdesk\ChangeController::statusLabels()[$change->status] ?? $change->status }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>
