<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDossierController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\User\Permission;
use App\Models\{Asset, Attachment, DiaryEntry, MaterialUsage, Protocol, ServiceTicket, User};
use App\Services\Asset\{AssetLifecycleService, AssetTimelineService};
use App\Services\ServiceTicket\SlaTimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Objektakte / Lebenszyklus-Dossier (Feature 027): zusammenhängende
 * Read-Only-Gesamtsicht eines Assets über den vollständigen Lebenszyklus —
 * Pendant zur Auftrags-Fallakte (diary/case-file). Druckbar über Print-CSS
 * (?print=1 öffnet direkt den Druckdialog), kein eigener PDF-Generator.
 *
 * Wiederverwendung statt Duplikat:
 *  - {@see AssetTimelineService} liefert die aggregierte Historie (inkl. der
 *    additiv ergänzten Quellen Assignment/Defect/Maintenance).
 *  - {@see AssetLifecycleService} leitet die Lebenszyklus-Phase ab.
 *  - 009-Relationen assignments/defects/maintenancePlans direkt vom Asset.
 *
 * Zugriff = asset.view (Gate view), Cross-Org via Tenant-Scope → 404.
 */
class AssetDossierController extends Controller {
    public function __invoke(
        Asset $asset,
        Request $request,
        AssetTimelineService $timeline,
        AssetLifecycleService $lifecycle,
    ): View {
        Gate::authorize('view', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $asset->load([
            'customer:id,name',
            'foreignCustomer:id,name',
            'room.floorRelation.building.site',
            'room.cleaningProfile',
            'room.requirements',
            'tags:id,name,color,slug',
        ]);

        $diaryEntries = $asset->diaryEntries()
            ->with(['user:id,name', 'project:id,name'])
            ->get()
            ->filter(fn(DiaryEntry $entry): bool => Gate::forUser($user)->allows('view', $entry))
            ->values();

        $protocols = $asset->protocols()
            ->with(['creator:id,name'])
            ->get()
            ->filter(fn(Protocol $protocol): bool => Gate::forUser($user)->allows('view', $protocol))
            ->values();

        $materialUsages = $asset->materialUsages()
            ->with(['timesheet:id,work_date,user_id', 'timesheet.user:id,name'])
            ->latest('updated_at')
            ->get()
            ->filter(fn(MaterialUsage $usage): bool => Gate::forUser($user)->allows('view', $usage))
            ->values();

        $attachments = $asset->attachments()
            ->latest('created_at')
            ->get()
            ->filter(fn(Attachment $attachment): bool => Gate::forUser($user)->allows('view', $attachment))
            ->values();

        $openIssues = $asset->openIssues()
            ->with(['assignee:id,name', 'creator:id,name'])
            ->get();

        $assignments = $asset->assignments()
            ->with(['assignedToUser:id,name', 'assignedToTeam:id,name', 'checkedOutBy:id,name'])
            ->get();

        $defects = $asset->defects()
            ->with(['reportedBy:id,name', 'resolvedBy:id,name'])
            ->get();

        $maintenancePlans = $asset->maintenancePlans()->get();

        $visibleDiaryIds = $diaryEntries->pluck('id')->all();
        $visibleProtocolIds = $protocols->pluck('id')->all();
        $visibleMaterialIds = $materialUsages->pluck('id')->all();
        $visibleAttachmentIds = $attachments->pluck('id')->all();

        $timelineItems = collect($timeline->build($asset, 500))
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
            ->values()
            ->all();

        return view('assets.dossier', [
            'asset' => $asset,
            'lifecycle' => $lifecycle->summary($asset),
            'diaryEntries' => $diaryEntries,
            'protocols' => $protocols,
            'materialUsages' => $materialUsages,
            'attachments' => $attachments,
            'openIssues' => $openIssues,
            'assignments' => $assignments,
            'defects' => $defects,
            'recurringDefect' => app(\App\Services\Asset\RecurringDefectService::class)->isRecurring($asset),
            'maintenancePlans' => $maintenancePlans,
            // Eigentümerwechsel-Historie (Feature 027 → Rang 49), append-only.
            'ownershipChanges' => $asset->ownershipChanges()
                ->with(['changedBy:id,name', 'toCustomer:id,name', 'fromCustomer:id,name'])
                ->get(),
            // SLA-/Vertrags-Sektion (Feature 027 → Rang 48): geltender Vertrag =
            // direkter Override, sonst Kunden-/Default-Auflösung. Anzeige nur mit
            // Recht slaContract.view.
            'canViewSla' => $user->can(Permission::SlaContractView->value),
            'slaContract' => $asset->slaContract
                ?? app(SlaTimer::class)->resolveContract((int) $asset->organization_id, $asset->customer_id),
            'slaTickets' => ServiceTicket::query()
                ->where('asset_id', $asset->id)
                ->whereNotIn('status', [ServiceTicketStatus::Closed->value, ServiceTicketStatus::Rejected->value])
                ->orderByDesc('reported_at')
                ->limit(20)
                ->get(),
            'roomRequirements' => $asset->room_id !== null && $asset->room !== null ? $asset->room->requirements : collect(),
            'timelineItems' => $timelineItems,
            'autoPrint' => $request->boolean('print'),
            'generatedAt' => now(),
        ]);
    }
}
