<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileSignedEmploymentContract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Hr;

use App\Enums\Contract\ContractKind;
use App\Enums\Hr\HrDocumentCategory;
use App\Events\Contract\ContractSigningCompleted;
use App\Listeners\ModuleListener;
use App\Models\Contract\Contract;
use App\Models\Platform\User;
use App\Services\Hr\PersonnelFileService;
use Illuminate\Support\Facades\Storage;

/** Unterschriebener Arbeitsvertrag (MVP-939) wandert als Vertrag in die Personalakte des Teammitglieds. */
final class FileSignedEmploymentContract extends ModuleListener {
    public function __construct(private readonly PersonnelFileService $files) {}

    protected function module(): string {
        return 'hr';
    }

    public function handle(ContractSigningCompleted $event): void {
        $contract = Contract::query()->withoutGlobalScopes()->find($event->revision->contract_id);
        if (! $contract instanceof Contract || $contract->kind !== ContractKind::Employment || ! $this->shouldHandle($contract->organization_id)) {
            return;
        }
        $member = User::query()->withoutGlobalScopes()->find($contract->employee_user_id);
        $actor = User::query()->withoutGlobalScopes()->find($contract->responsible_user_id ?? $contract->created_by);
        $version = $event->revision->manifestItems()->orderBy('sort')->with('documentVersion')->first()?->documentVersion;
        if (! $member instanceof User || ! $actor instanceof User || $version === null) {
            return;
        }
        $this->files->createFromContents($member, $actor, [
            'title' => $contract->title,
            'hr_category' => HrDocumentCategory::Contract->value,
            'valid_from' => $contract->starts_on->toDateString(),
            'description' => (string) __('hr.employment.filed_note', ['number' => $contract->number]),
        ], (string) Storage::disk($version->disk)->get($version->path), $version->original_name, $version->mime ?? 'application/pdf');
    }
}
