<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintIntakeTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Intake;

use App\Enums\Customer\IntakeKind;
use App\Enums\Print\{PrintOrderStatus, PrintOutputKind};
use App\Models\Article\Article;
use App\Models\Attachments\Attachment;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\{Organization, User};
use App\Models\Print\PrintOrder;
use App\Services\Customer\Contracts\IntakeHandoverTarget;
use App\Services\Customer\Dto\IntakeStage;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Print\Contracts\ProductionOrderFactory;
use App\Services\Print\PrintOrderService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Druckerei-Ziel der Auftragsübernahme (MVP-1076): Fertigungsauftrag
 * (Entwurf) plus Druckauftrag aus dem beauftragten Eingang. Übernimmt die
 * Konditionen des angenommenen Angebots; die Produktionsdatei wählt der
 * Betrieb ausdrücklich, die Kundenfreigabe ist Pflicht vor der internen.
 */
class PrintIntakeTarget implements IntakeHandoverTarget {
    /** Lizenz der Fertigung, an der jeder Druckauftrag hängt. */
    private const MANUFACTURING_MODULE = 'module.lager';

    public function __construct(
        private readonly PrintOrderService $orders,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function kind(): IntakeKind {
        return IntakeKind::Print;
    }

    public function isAvailable(Organization $organization): bool {
        return $organization->hasBranchProfile(PrintOrderService::PROFILE_CODE) && $this->features->isEnabled(self::MANUFACTURING_MODULE);
    }

    public function canCreate(User $actor): bool {
        return Gate::forUser($actor)->allows('create', PrintOrder::class);
    }

    public function formView(): ?string {
        return 'print.orders._intake_handover_fields';
    }

    public function formData(CustomerIntake $intake): array {
        $values = $intake->form?->values;
        $accepted = collect((array) data_get($intake->quote?->decision_snapshot, 'items', []))
            ->first(fn (array $item): bool => ($item['accepted'] ?? false) && ($item['article_id'] ?? null) !== null);
        $articles = Article::query()->where('manufacturable', true)->orderBy('name')->get(['id', 'name']);
        $article = $accepted !== null ? $articles->firstWhere('id', (int) $accepted['article_id']) : null;

        return [
            'articles' => $articles,
            'defaultArticle' => $article?->sqid,
            'defaultQuantity' => $accepted['quantity'] ?? ($values?->get('quantity') ?: null),
            'defaultUnit' => $accepted['unit'] ?? 'Stk',
            'defaultOutput' => $values?->get('delivery') === 'shipping' ? PrintOutputKind::Shipping->value : PrintOutputKind::Pickup->value,
            'defaultDue' => $intake->desired_date?->toDateString(),
            'files' => $intake->attachments()->orderBy('created_at')->get(['id', 'original_name', 'created_at']),
        ];
    }

    public function rules(): array {
        return [
            'article_id' => ['required', 'string', 'max:64'],
            'target_qty' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', 'string', 'max:16'],
            'due_at' => ['nullable', 'date'],
            'output_kind' => ['required', 'string', 'in:' . implode(',', PrintOutputKind::values())],
            'files_retain_until' => ['nullable', 'date', 'after:today'],
            'production_file' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function handOver(CustomerIntake $intake, User $actor, array $input): Model {
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($intake->organization_id);
        $article = Article::query()->withoutGlobalScopes()
            ->where('organization_id', $intake->organization_id)
            ->where('manufacturable', true)
            ->whereKey(Sqid::decode(Article::class, (string) $input['article_id']))
            ->first() ?? throw ValidationException::withMessages(['article_id' => (string) __('print.intake.article_invalid')]);

        $manufacturing = app(ProductionOrderFactory::class)->createDraft($organization, $article, null, (string) $input['target_qty'], (string) $input['unit'], [
            'customer_id' => $intake->customer_id,
            'due_at' => $input['due_at'] ?? null,
        ]);
        $order = $this->orders->open($manufacturing, $actor, [
            'output_kind' => $input['output_kind'],
            'files_retain_until' => $input['files_retain_until'] ?? null,
        ]);
        $order->forceFill(['is_customer_approval_required' => true])->save();
        $order->audit('print.opened_from_intake', ['customer_intake' => $intake->number, 'quote_id' => $intake->quote_id]);

        $fileSqid = (string) ($input['production_file'] ?? '');
        if ($fileSqid !== '') {
            $attachment = $intake->attachments()->whereKey(Sqid::decode(Attachment::class, $fileSqid))->first()
                ?? throw ValidationException::withMessages(['production_file' => (string) __('print.intake.file_invalid')]);
            $order = $this->orders->bindIntakeFile($order, $attachment, $actor);
            $intake->record('production_file_selected', ['file' => $attachment->original_name], $actor);
        }

        return $order;
    }

    public function customerStage(Model $target): IntakeStage {
        /** @var PrintOrder $target */
        if ($target->customerApprovalPending()) {
            return new IntakeStage((string) __('print.intake.stage.approval_pending'), 'warning', (string) __('print.intake.next_step.approve'), true);
        }
        if ($target->customer_declined_at !== null && ! $target->status->isFinal()) {
            return new IntakeStage((string) __('print.intake.stage.approval_declined'), 'warning');
        }

        return match ($target->status) {
            PrintOrderStatus::DataCheck => new IntakeStage((string) __('print.intake.stage.data_check'), 'info'),
            PrintOrderStatus::Approved => new IntakeStage((string) __('print.intake.stage.approved'), 'primary'),
            PrintOrderStatus::InProduction, PrintOrderStatus::QualityCheck, PrintOrderStatus::Rework => new IntakeStage((string) __('print.intake.stage.in_production'), 'primary'),
            PrintOrderStatus::Ready => new IntakeStage((string) __('print.intake.stage.ready_' . $target->output_kind->value), 'success'),
            PrintOrderStatus::Issued => new IntakeStage((string) __('print.intake.stage.issued'), 'success'),
            PrintOrderStatus::Cancelled => new IntakeStage((string) __('print.intake.stage.cancelled'), 'neutral'),
        };
    }

    public function targetLabel(Model $target): string {
        /** @var PrintOrder $target */
        return (string) __('print.intake.target_label', ['number' => (string) ($target->manufacturingOrder->number ?? $target->sqid)]);
    }

    public function internalUrl(Model $target, User $viewer): ?string {
        return Gate::forUser($viewer)->allows('view', $target) ? route('print-orders.show', $target) : null;
    }

    public function internalPanelView(): ?string {
        return 'print.orders._intake_panel';
    }

    public function portalPanelView(): ?string {
        return 'print.orders._portal_approval';
    }
}
