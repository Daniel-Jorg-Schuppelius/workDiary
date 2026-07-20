<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDetailAssembler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Models\{Asset, Attachment, DiaryEntry, MaintenancePlan, MaterialUsage, Protocol, User};
use Illuminate\Support\Facades\Gate;

/**
 * Aggregiert alle Daten der Asset-Detailansicht (Verknüpfungen, Timeline,
 * Zuweisungen, Mängel, Wartungspläne) inkl. Sichtbarkeitsfilterung je
 * Betrachter. Aus AssetController::show() extrahiert
 * (Refactoring Welle 2, B6b) — die Autorisierung des Aufrufs selbst
 * bleibt im Controller.
 */
class AssetDetailAssembler {
    public function __construct(
        private readonly AssetTimelineService $timeline,
        private readonly AssetStatusVisibilityService $statusVisibility,
        private readonly AssetAssignmentService $assignments,
        private readonly AssetLifecycleService $lifecycle,
        private readonly AssetTimelinePresenter $timelinePresenter,
        private readonly AssetFormOptions $options,
    ) {}

    /**
     * @return array<string, mixed> View-Daten für assets.show
     */
    public function assemble(Asset $asset, User $user): array {
        $asset->load([
            'customer:id,name',
            'room.floorRelation.building.site',
            'room.cleaningProfile',
            'room.requirements' => fn($q) => $q->where('is_active', true),
            'softwareInstallations.software',
            'operatingSystem.software',
            'tags:id,name,color,slug',
        ]);
        $asset->loadCount(['diaryEntries', 'protocols', 'materialUsages', 'attachments']);

        $currentAssignment = $this->assignments->openAssignment($asset);
        $currentAssignment?->load(['assignedToUser:id,name', 'assignedToTeam:id,name', 'checkedOutBy:id,name', 'diaryEntry:id,title']);
        $assignmentHistory = $asset->assignments()
            ->whereNotNull('returned_at')
            ->with(['assignedToUser:id,name', 'assignedToTeam:id,name'])
            ->limit(12)
            ->get();
        $defects = $asset->defects()
            ->with(['reportedBy:id,name', 'resolvedBy:id,name', 'attachments'])
            ->limit(20)
            ->get();

        $diaryEntries = $asset->diaryEntries()
            ->with(['user:id,name', 'project:id,name'])
            ->limit(12)
            ->get()
            ->filter(fn(DiaryEntry $entry): bool => Gate::forUser($user)->allows('view', $entry))
            ->values();

        $protocols = $asset->protocols()
            ->with(['creator:id,name'])
            ->limit(12)
            ->get()
            ->filter(fn(Protocol $protocol): bool => Gate::forUser($user)->allows('view', $protocol))
            ->values();

        $materialUsages = $asset->materialUsages()
            ->with(['timesheet:id,work_date,user_id', 'timesheet.user:id,name'])
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->filter(fn(MaterialUsage $usage): bool => Gate::forUser($user)->allows('view', $usage))
            ->values();

        $attachments = $asset->attachments()
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->filter(fn(Attachment $attachment): bool => Gate::forUser($user)->allows('view', $attachment))
            ->values();

        $visibleDiaryIds = $diaryEntries->pluck('id')->all();
        $visibleProtocolIds = $protocols->pluck('id')->all();
        $visibleMaterialIds = $materialUsages->pluck('id')->all();
        $visibleAttachmentIds = $attachments->pluck('id')->all();

        $timelineEntries = collect($this->timeline->build($asset, 24))
            ->filter(function (array $event) use ($visibleAttachmentIds, $visibleDiaryIds, $visibleMaterialIds, $visibleProtocolIds): bool {
                $kind = (string) ($event['kind'] ?? '');
                $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
                $id = (int) ($payload['id'] ?? 0);

                return match ($kind) {
                    'order.linked' => in_array($id, $visibleDiaryIds, true),
                    'protocol.linked' => in_array($id, $visibleProtocolIds, true),
                    'material.linked' => in_array($id, $visibleMaterialIds, true),
                    'attachment.linked' => in_array($id, $visibleAttachmentIds, true),
                    default => true,
                };
            })
            ->map(fn(array $event): array => $this->timelinePresenter->present($event))
            ->values();

        $visibilitySummary = $this->statusVisibility->summarize($asset);

        $maintenancePlans = $asset->maintenancePlans()->get();
        $canManageMaintenance = Gate::forUser($user)->allows('create', MaintenancePlan::class);

        return [
            'asset' => $asset,
            'lifecycle' => $this->lifecycle->summary($asset),
            'roomRequirements' => $asset->room_id !== null && $asset->room !== null ? $asset->room->requirements : collect(),
            'classOptions' => $this->options->classOptions(),
            'statusOptions' => $this->options->statusOptions(),
            'diaryEntries' => $diaryEntries,
            'protocols' => $protocols,
            'materialUsages' => $materialUsages,
            'attachments' => $attachments,
            'timelineEntries' => $timelineEntries,
            'statusSummary' => $visibilitySummary,
            'currentAssignment' => $currentAssignment,
            'assignmentHistory' => $assignmentHistory,
            'defects' => $defects,
            'isCheckedOut' => $currentAssignment !== null,
            'isDefectBlocked' => $this->assignments->isBlocked($asset),
            // Vollaudit 2026-07 (H3): aktive D12-Sperren sind auf der Akte
            // sichtbar und blenden den Ausgeben-Button aus (statusSummary).
            'activeBlocks' => $visibilitySummary['active_blocks'],
            'canCheckout' => Gate::forUser($user)->allows('checkout', $asset),
            'canManageDefects' => Gate::forUser($user)->allows('manageDefects', $asset),
            'canUnblock' => Gate::forUser($user)->allows('update', $asset),
            'maintenancePlans' => $maintenancePlans,
            'intervalKindOptions' => $this->options->intervalKindOptions(),
            'canManageMaintenance' => $canManageMaintenance,
            'visibleCounts' => [
                'diary' => $diaryEntries->count(),
                'protocols' => $protocols->count(),
                'material' => $materialUsages->count(),
                'attachments' => $attachments->count(),
            ],
        ];
    }
}
