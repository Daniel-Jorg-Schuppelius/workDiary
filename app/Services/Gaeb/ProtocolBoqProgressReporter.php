<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolBoqProgressReporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqProgressSource};
use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\BoqItem;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\Protocol;
use App\Services\Protocol\Contracts\BoqProgressReporter;
use App\Support\Sqid;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fortschrittsmeldung von der Protokollseite (MVP-1582): dieselbe Meldung wie
 * im LV-Dialog, mit dem Protokoll als Beleg. Recht wie dort (`project.update`).
 */
class ProtocolBoqProgressReporter implements BoqProgressReporter {
    private const int LIMIT = 500;

    public function __construct(private readonly BoqProgressService $progress) {}

    public function positionsFor(Protocol $protocol, User $user): array {
        $subject = $protocol->subject;
        $projectId = match (true) {
            $subject instanceof Project => $subject->id,
            $subject instanceof DiaryEntry => $subject->project_id,
            default => null,
        };
        if ($projectId === null || ! $user->can(Permission::ProjectUpdate->value)) {
            return [];
        }

        return BoqItem::query()
            ->where('organization_id', $protocol->organization_id)
            ->whereHas('billOfQuantity', fn ($q) => $q->where('project_id', $projectId))
            ->whereNotIn('status', [BoqItemStatus::Completed->value, BoqItemStatus::Cancelled->value, BoqItemStatus::Replaced->value])
            ->orderBy('bill_of_quantity_id')->orderBy('reference_no')
            ->limit(self::LIMIT)
            ->get()
            ->mapWithKeys(fn (BoqItem $item): array => [
                $item->sqid => trim($item->reference_no . ' ' . Str::limit((string) $item->short_text, 60)) . ($item->unit ? ' (' . $item->unit . ')' : ''),
            ])
            ->all();
    }

    public function report(Protocol $protocol, string $position, string $quantity, ?string $note, User $actor): void {
        if (! array_key_exists($position, $this->positionsFor($protocol, $actor))) {
            throw ValidationException::withMessages(['position' => __('gaeb.progress.error.position')]);
        }

        $this->progress->record(BoqItem::query()->findOrFail(Sqid::decode(BoqItem::class, $position)), $quantity, [
            'source' => BoqProgressSource::Protocol,
            'protocol_id' => $protocol->id,
            'note' => $note,
            'created_by' => $actor->id,
        ]);
    }
}
