<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSubmissionWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr\WorkQueue;

use App\Enums\Hr\PersonnelFileSubmissionStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Document\Document;
use App\Models\Hr\PersonnelFileSubmission;
use App\Models\Platform\{Organization, User};
use App\Services\Hr\PersonnelFilePermissions;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/**
 * Einreichungen zur Personalakte, die der Akten-Kreis übernehmen oder
 * ablehnen darf (DocumentPolicy::createPersonnelFile) — nie die eigene.
 * Admins sehen sie nur mit Aktenrecht: die Personalakte kennt keinen Admin-Durchgriff.
 */
final class PersonnelFileSubmissionWorkItemSource implements WorkItemSource {
    private const string ROUTE = 'personnel-file.submissions.index';

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'personnel_file_submission';
    }

    public function label(): string {
        return (string) __('work-queue.source.personnel_file_submission');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled(self::ROUTE)
            && $viewer->hasEffectivePermission(PersonnelFilePermissions::VIEW_ANY)
            && $viewer->hasEffectivePermission(PersonnelFilePermissions::CREATE);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $submitted = PersonnelFileSubmission::query()
            ->where('organization_id', $organization->id)
            ->where('status', PersonnelFileSubmissionStatus::Submitted->value)
            ->where('user_id', '!=', $viewer->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        foreach ($submitted as $submission) {
            $member = $submission->user;
            if (! $member instanceof User || ! $gate->allows('createPersonnelFile', [Document::class, $member])) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $submission->getMorphClass() . ':' . $submission->id,
                title: (string) __('work-queue.item.personnel_file_submission', ['name' => $member->name, 'title' => $submission->title]),
                url: route(self::ROUTE),
                detail: $submission->created_at !== null ? CarbonFmt::fdate($submission->created_at) : null,
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($submission->updated_at),
            );
        }
    }
}
