{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dossier.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Objektakte / Lebenszyklus-Dossier (Feature 027): zusammenhängende
  Read-Only-/Druck-Sicht eines Assets. Standalone-HTML mit Print-CSS
  (Muster: diary/case-file.blade.php).
--}}
@php
    /** @var \App\Models\Asset $asset */
    /** @var array<string, mixed> $lifecycle */
    $classLabels = [
        'device' => __('Gerät'), 'machine' => __('Maschine'), 'tool' => __('Werkzeug'),
        'vehicle' => __('Fahrzeug'), 'installation' => __('Installation'), 'software' => __('Software'),
        'building' => __('Gebäude'), 'area' => __('Bereich'), 'room' => __('Raum'), 'other' => __('Sonstiges'),
    ];
    $statusLabels = [
        'active' => __('Aktiv'), 'inMaintenance' => __('In Wartung'), 'inRepair' => __('In Reparatur'),
        'blocked' => __('Gesperrt'), 'reserved' => __('Reserviert'), 'loanOut' => __('Ausgeliehen'),
        'replaced' => __('Ersetzt'), 'decommissioned' => __('Außer Betrieb'), 'lost' => __('Verloren'),
    ];
    $healthLabels = ['ok' => __('OK'), 'degraded' => __('Eingeschränkt'), 'critical' => __('Kritisch')];
    $classValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
    $statusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
    $healthValue = $asset->health instanceof \BackedEnum ? $asset->health->value : (string) $asset->health;
    $room = $asset->room;
    $building = $room?->floorRelation?->building;
    $site = $building?->site;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('asset.dossier.title') }} {{ $asset->asset_no }} — WorkDiary</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: #111; margin: 24px auto; max-width: 960px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
        tr:nth-child(even) td { background: #fafafa; }
        .kv th { width: 200px; background: #f5f5f5; }
        .badge { display: inline-block; padding: 1px 6px; border: 1px solid #888; border-radius: 8px; font-size: 10px; white-space: nowrap; }
        .badge.ok { border-color: #1a7f37; color: #1a7f37; }
        .badge.warn { border-color: #9a6700; color: #9a6700; }
        .badge.crit { border-color: #b42318; color: #b42318; }
        .muted { color: #666; }
        .pre { white-space: pre-wrap; }
        .actions { margin: 8px 0 16px; }
        .btn { padding: 6px 12px; border: 1px solid #555; background: #fff; cursor: pointer; text-decoration: none; color: #111; display: inline-block; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; max-width: none; }
            h2 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn" onclick="window.print()">{{ __('timeline.action.print') }}</button>
        <a class="btn" href="{{ route('assets.show', $asset) }}">{{ __('asset.dossier.back') }}</a>
    </div>

    {{-- Kopf --}}
    <h1>{{ __('asset.dossier.title') }}: {{ $asset->name }}</h1>
    <div class="meta">
        {{ __('asset.dossier.generated_at') }} {{ $generatedAt->fdatetime() }}
        — {{ __('Asset-Nr.') }} {{ $asset->asset_no }}
        — {{ __('asset.dossier.lifecycle') }}:
        <span class="badge {{ $lifecycle['phase_tone'] === 'success' ? 'ok' : ($lifecycle['phase_tone'] === 'error' ? 'crit' : 'warn') }}">{{ $lifecycle['phase_label'] }}</span>
    </div>

    {{-- Stammdaten --}}
    <h2>{{ __('asset.dossier.master_data') }}</h2>
    <table class="kv">
        <tr><th>{{ __('Bezeichnung') }}</th><td>{{ $asset->name }}</td></tr>
        <tr><th>{{ __('Typ') }}</th><td>{{ $classLabels[$classValue] ?? $classValue }}</td></tr>
        <tr><th>{{ __('Status') }}</th><td>{{ $statusLabels[$statusValue] ?? $statusValue }} · {{ __('asset.dossier.health') }}: {{ $healthLabels[$healthValue] ?? $healthValue }}</td></tr>
        <tr><th>{{ __('asset.dossier.lifecycle') }}</th><td><span class="badge {{ $lifecycle['phase_tone'] === 'success' ? 'ok' : ($lifecycle['phase_tone'] === 'error' ? 'crit' : 'warn') }}">{{ $lifecycle['phase_label'] }}</span></td></tr>
        @if ($asset->manufacturer || $asset->model)
            <tr><th>{{ __('Hersteller / Modell') }}</th><td>{{ trim(($asset->manufacturer ?? '') . ' ' . ($asset->model ?? '')) ?: '—' }}</td></tr>
        @endif
        @if ($asset->serial_no)<tr><th>{{ __('Seriennummer') }}</th><td>{{ $asset->serial_no }}</td></tr>@endif
        @if ($asset->inventory_no)<tr><th>{{ __('Inventarnummer') }}</th><td>{{ $asset->inventory_no }}</td></tr>@endif
        <tr><th>{{ __('Kunde') }}</th><td>{{ $asset->customer?->name ?? $asset->foreignCustomer?->name ?? '—' }}</td></tr>
        <tr><th>{{ __('Standort') }}</th><td>
            @if ($room)
                {{ collect([$site?->name, $building?->name, $room->floorRelation?->label, $room->name])->filter()->implode(' · ') }}
            @else
                {{ $asset->location_text ?? '—' }}
            @endif
        </td></tr>
        <tr><th>{{ __('asset.dossier.commissioned') }}</th><td>{{ $lifecycle['commissioned_on']?->fdate() ?? '—' }}</td></tr>
        <tr><th>{{ __('asset.dossier.decommissioned') }}</th><td>{{ $lifecycle['decommissioned_on']?->fdate() ?? '—' }}</td></tr>
        <tr><th>{{ __('asset.dossier.warranty') }}</th><td>
            {{ $lifecycle['warranty_until']?->fdate() ?? '—' }}
            @if ($lifecycle['warranty_expired'])<span class="badge crit">{{ __('asset.dossier.warranty_expired') }}</span>@endif
        </td></tr>
        @if ($lifecycle['in_service_days'] !== null)
            <tr><th>{{ __('asset.dossier.in_service_days') }}</th><td>{{ $lifecycle['in_service_days'] }}</td></tr>
        @endif
        @if ($asset->tags->isNotEmpty())
            <tr><th>{{ __('Tags') }}</th><td>{{ $asset->tags->pluck('name')->map(fn($n) => '#' . $n)->implode(' ') }}</td></tr>
        @endif
        @if ($asset->notes)<tr><th>{{ __('Notizen') }}</th><td class="pre">{{ $asset->notes }}</td></tr>@endif
    </table>

    {{-- Raumbezogene Anforderungen des Standort-Raums --}}
    @if ($roomRequirements->isNotEmpty() || $room?->cleaningProfile)
        <h2>{{ __('asset.dossier.room_requirements') }}</h2>
        <table>
            <thead><tr><th>{{ __('Anforderung') }}</th><th>{{ __('Stufe / Wert') }}</th><th>{{ __('Notiz') }}</th></tr></thead>
            <tbody>
                @if ($room?->cleaningProfile)
                    <tr>
                        <td>{{ __('Reinigungsprofil') }}</td>
                        <td>{{ $room->cleaningProfile->label }}</td>
                        <td>@if ($room->cleaningProfile->interval_days){{ __(':n Tage', ['n' => $room->cleaningProfile->interval_days]) }}@endif</td>
                    </tr>
                @endif
                @foreach ($roomRequirements as $req)
                    <tr>
                        <td>{{ $req->kind->label() }}</td>
                        <td>{{ $req->level ?? '—' }}</td>
                        <td>{{ $req->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Wartungen --}}
    @if ($maintenancePlans->isNotEmpty())
        <h2>{{ __('asset.dossier.maintenance') }}</h2>
        <table>
            <thead><tr><th>{{ __('Bezeichnung') }}</th><th>{{ __('asset.dossier.next_due') }}</th><th>{{ __('asset.dossier.last_run') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($maintenancePlans as $plan)
                    <tr>
                        <td>{{ $plan->label }}</td>
                        <td>{{ $plan->next_due_on?->fdate() ?? '—' }}</td>
                        <td>{{ $plan->last_run_at?->fdatetime() ?? '—' }}</td>
                        <td><span class="badge {{ $plan->isDue() ? 'warn' : 'ok' }}">{{ $plan->isDue() ? __('asset.dossier.due') : __('asset.dossier.scheduled') }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Verträge / SLA (Feature 027 → Rang 48): geltender Vertrag (Direktzuordnung
         oder Kunden-/Default-Auflösung) + Fristen der offenen Asset-Tickets.
         Anzeige nur mit Recht slaContract.view. --}}
    @if ($canViewSla)
        <h2>{{ __('Verträge & SLA') }}</h2>
        <table>
            <tbody>
                <tr>
                    <th>{{ __('Geltender SLA-Vertrag') }}</th>
                    <td>
                        @if ($slaContract)
                            <a href="{{ route('sla-contracts.show', $slaContract) }}">{{ $slaContract->code }} — {{ $slaContract->label }}</a>
                            @if ($asset->sla_contract_id)
                                <span class="badge">{{ __('Direktzuordnung') }}</span>
                            @endif
                        @else
                            {{ __('Kein Vertrag zugeordnet.') }}
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        @if ($slaTickets->isNotEmpty())
            <table>
                <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('timeline.case.title_col') }}</th><th>SLA</th><th>{{ __('Fällig') }}</th></tr></thead>
                <tbody>
                    @foreach ($slaTickets as $ticket)
                        <tr>
                            <td>{{ $ticket->ticket_no }}</td>
                            <td>{{ $ticket->title }}</td>
                            <td><span class="badge">{{ $ticket->slaStatus()->label() }}</span></td>
                            <td>{{ $ticket->resolution_due_at?->fdatetime() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    {{-- Eigentümerwechsel-Historie (Feature 027 → Rang 49): append-only Nachweis. --}}
    @if ($ownershipChanges->isNotEmpty())
        @php $ownershipLabels = ['org' => __('Organisation'), 'customer' => __('Kunde'), 'external' => __('Extern')]; @endphp
        <h2>{{ __('Eigentümerwechsel-Historie') }}</h2>
        <table>
            <thead><tr><th>{{ __('Datum') }}</th><th>{{ __('Von') }}</th><th>{{ __('Nach') }}</th><th>{{ __('Kunde') }}</th><th>{{ __('Durch') }}</th><th>{{ __('Notizen') }}</th></tr></thead>
            <tbody>
                @foreach ($ownershipChanges as $change)
                    <tr>
                        <td>{{ $change->changed_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $change->from_ownership ? ($ownershipLabels[$change->from_ownership->value] ?? $change->from_ownership->value) : '—' }}</td>
                        <td>{{ $ownershipLabels[$change->to_ownership->value] ?? $change->to_ownership->value }}</td>
                        <td>{{ $change->toCustomer?->name ?? '—' }}</td>
                        <td>{{ $change->changedBy?->name ?? '—' }}</td>
                        <td class="pre">{{ $change->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Ausgaben / Rückgaben --}}
    @if ($assignments->isNotEmpty())
        <h2>{{ __('asset.dossier.assignments') }}</h2>
        <table>
            <thead><tr><th>{{ __('asset.dossier.checked_out') }}</th><th>{{ __('asset.dossier.assignee') }}</th><th>{{ __('asset.dossier.returned') }}</th></tr></thead>
            <tbody>
                @foreach ($assignments as $assignment)
                    <tr>
                        <td>{{ $assignment->checked_out_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $assignment->assignedToUser?->name ?? $assignment->assignedToTeam?->name ?? '—' }}</td>
                        <td>{{ $assignment->returned_at?->fdatetime() ?? __('asset.dossier.open') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Defekte / Sperren --}}
    @if ($defects->isNotEmpty())
        <h2>{{ __('asset.dossier.defects') }}
            @if ($recurringDefect ?? false)
                {{-- Wiederholdefekt-Fall (Feature 009 → Rang 47): >= 3 Defekte in 12 Monaten. --}}
                <span class="badge badge-warning badge-sm align-middle">{{ __('Wiederholdefekt') }}</span>
            @endif
        </h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.date') }}</th><th>{{ __('timeline.case.title_col') }}</th><th>{{ __('timeline.case.severity') }}</th><th>{{ __('Status') }}</th><th>{{ __('asset.dossier.blocks') }}</th></tr></thead>
            <tbody>
                @foreach ($defects as $defect)
                    <tr>
                        <td>{{ $defect->reported_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $defect->title }}</td>
                        <td>{{ $defect->severity->label() }}</td>
                        <td><span class="badge">{{ $defect->status->label() }}</span></td>
                        <td>{{ $defect->blocks_usage ? __('timeline.case.yes') : __('timeline.case.no') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Aufträge --}}
    @if ($diaryEntries->isNotEmpty())
        <h2>{{ __('asset.dossier.orders') }}</h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.date') }}</th><th>{{ __('timeline.case.title_col') }}</th><th>{{ __('timeline.case.status') }}</th><th>{{ __('timeline.case.person') }}</th></tr></thead>
            <tbody>
                @foreach ($diaryEntries as $entry)
                    <tr>
                        <td>{{ $entry->start_at?->fdate() ?? $entry->created_at?->fdate() ?? '—' }}</td>
                        <td>{{ $entry->title }}</td>
                        <td><span class="badge">{{ $entry->statusLabel() }}</span></td>
                        <td>{{ $entry->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Protokolle --}}
    @if ($protocols->isNotEmpty())
        <h2>{{ __('timeline.case.protocols') }}</h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.date') }}</th><th>{{ __('timeline.case.title_col') }}</th><th>{{ __('timeline.case.status') }}</th></tr></thead>
            <tbody>
                @foreach ($protocols as $protocol)
                    <tr>
                        <td>{{ $protocol->occurred_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $protocol->title }}</td>
                        <td><span class="badge">{{ $protocol->status->label() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Materialeinsatz --}}
    @if ($materialUsages->isNotEmpty())
        <h2>{{ __('timeline.case.material') }}</h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.quantity') }}</th><th>{{ __('timeline.case.description') }}</th></tr></thead>
            <tbody>
                @foreach ($materialUsages as $usage)
                    <tr>
                        <td>{{ rtrim(rtrim((string) $usage->quantity, '0'), '.') }} {{ $usage->unit }}</td>
                        <td>{{ $usage->description }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Offene Punkte --}}
    @if ($openIssues->isNotEmpty())
        <h2>{{ __('timeline.case.open_issues') }}</h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.title_col') }}</th><th>{{ __('timeline.case.severity') }}</th><th>{{ __('timeline.case.status') }}</th><th>{{ __('timeline.case.due') }}</th></tr></thead>
            <tbody>
                @foreach ($openIssues as $issue)
                    <tr>
                        <td>{{ $issue->title }}</td>
                        <td>{{ $issue->severity->label() }}</td>
                        <td><span class="badge">{{ $issue->status->label() }}</span></td>
                        <td>{{ $issue->due_at?->fdate() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Dokumente / Anhänge --}}
    @if ($attachments->isNotEmpty())
        <h2>{{ __('timeline.case.attachments') }}</h2>
        <table>
            <thead><tr><th>{{ __('timeline.case.date') }}</th><th>{{ __('timeline.case.title_col') }}</th></tr></thead>
            <tbody>
                @foreach ($attachments as $attachment)
                    <tr>
                        <td>{{ $attachment->created_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $attachment->original_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Vollständige Lebenszyklus-Timeline --}}
    <h2>{{ __('asset.dossier.timeline') }}</h2>
    @if (empty($timelineItems))
        <p class="muted">{{ __('timeline.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('timeline.case.date') }}</th><th>{{ __('timeline.case.event') }}</th></tr></thead>
            <tbody>
                @foreach ($timelineItems as $item)
                    <tr>
                        <td>{{ isset($item['occurred_at']) ? \Illuminate\Support\Carbon::parse($item['occurred_at'])->fdatetime() : '—' }}</td>
                        <td>{{ __('asset.dossier.event.' . ($item['kind'] ?? 'unknown')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($autoPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
