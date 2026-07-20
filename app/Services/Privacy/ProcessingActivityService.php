<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\{ControllerRole, ProcessingActivityStatus};
use App\Models\{Organization, User};
use App\Models\Privacy\{ProcessingActivity, ProcessingActivityVersion};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet Verarbeitungstaetigkeiten und ihre Versionierung (Art. 30): Entwurf,
 * neue Version, Einreichung zur Pruefung und Freigabe. `current_version_id` zeigt
 * auf die freigegebene (gueltige) Version; der Audit-Trail laeuft ueber Auditable.
 */
class ProcessingActivityService {
    public function __construct(private readonly TechnicalMeasureService $tom) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(
        Organization $organization,
        string $name,
        ?string $purpose,
        ControllerRole $role,
        array $payload,
        ?User $actor = null,
        ?string $area = null,
    ): ProcessingActivity {
        return DB::transaction(function () use ($organization, $name, $purpose, $role, $payload, $actor, $area): ProcessingActivity {
            $activity = ProcessingActivity::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'purpose' => $purpose,
                'controller_role' => $role,
                'area' => $area,
                'status' => ProcessingActivityStatus::Draft,
                'created_by' => $actor?->id,
            ]);

            $this->addVersion($activity, $payload, $actor, 'Erstentwurf');

            return $activity;
        });
    }

    /**
     * Legt eine neue (Entwurfs-)Version an.
     *
     * @param  array<string, mixed>  $payload
     */
    public function addVersion(ProcessingActivity $activity, array $payload, ?User $actor = null, ?string $note = null): ProcessingActivityVersion {
        $next = (int) $activity->versions()->max('version_no') + 1;

        return ProcessingActivityVersion::create([
            'organization_id' => $activity->organization_id,
            'activity_id' => $activity->id,
            'version_no' => $next,
            'payload' => $payload,
            'note' => $note,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * VVT-Vorlagenkatalog (Feature 043 MVP 1; Vollaudit 2026-07, M17).
     *
     * @return array<string, array{name: string, purpose: string, controller_role: string, area: string, payload: array<string, string>}>
     */
    public function templates(): array {
        /** @var array<string, array{name: string, purpose: string, controller_role: string, area: string, payload: array<string, string>}> $templates */
        $templates = require database_path('data/privacy/vvt-templates.php');

        return $templates;
    }

    /**
     * Anlage aus Vorlage (M17): Entwurfsstatus, org-scoped, idempotent über
     * den Vorlagen-Namen — existiert die Tätigkeit bereits, wird sie
     * unverändert zurückgegeben (keine Dublette, kein Überschreiben).
     */
    public function createFromTemplate(Organization $organization, string $templateKey, ?User $actor = null): ProcessingActivity {
        $template = $this->templates()[$templateKey]
            ?? throw new \InvalidArgumentException('Unbekannte VVT-Vorlage: ' . $templateKey);

        $existing = ProcessingActivity::query()
            ->where('organization_id', $organization->id)
            ->where('name', $template['name'])
            ->first();
        if ($existing instanceof ProcessingActivity) {
            return $existing;
        }

        return $this->createDraft(
            $organization,
            $template['name'],
            $template['purpose'],
            ControllerRole::from($template['controller_role']),
            $template['payload'],
            $actor,
            $template['area'],
        );
    }

    public function submitForReview(ProcessingActivity $activity): ProcessingActivity {
        $activity->forceFill(['status' => ProcessingActivityStatus::InReview])->save();

        return $activity;
    }

    /** Gibt eine Version frei: setzt sie als aktuelle gueltige Version + naechsten Review. */
    public function approve(ProcessingActivity $activity, ProcessingActivityVersion $version, User $approver): ProcessingActivity {
        return DB::transaction(function () use ($activity, $version, $approver): ProcessingActivity {
            $now = Carbon::now();
            // Unveraenderlichen TOM-Snapshot in die freigegebene Version einfrieren
            // (Art. 32 / Nachweis): spaetere TOM-Aenderungen aendern die Historie nicht.
            $payload = $version->payload;
            $payload['tom_snapshot'] = $this->tom->snapshotForActivity($activity);
            $version->forceFill([
                'payload' => $payload,
                'approved_by' => $approver->id,
                'approved_at' => $now,
                'valid_from' => $now->toDateString(),
            ])->save();

            $cycle = (int) config('dataprotection.review_cycle_months', 12);
            $activity->forceFill([
                'status' => ProcessingActivityStatus::Approved,
                'current_version_id' => $version->id,
                'review_due_at' => $now->copy()->addMonths($cycle)->toDateString(),
            ])->save();

            return $activity;
        });
    }
}
