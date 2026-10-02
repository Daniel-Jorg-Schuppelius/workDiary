<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Takeoff;

use App\Enums\Takeoff\{TakeoffFormula, TakeoffStatus, TakeoffTransferKind};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Article\Article;
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Models\Takeoff\{Takeoff, TakeoffLine};
use App\Modules\ModuleRegistry;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Attachments\FileAttacher;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Takeoff\{TakeoffPdfRenderer, TakeoffService, TakeoffTransferService};
use App\Settings\SettingsRegistry;
use App\Support\{ErrorText, Sqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request, Response, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Aufmaßblatt (MVP-1058): anlegen am Träger, Zeilen erfassen, abschließen, PDF; Übernahme in Belege (MVP-1059). */
class TakeoffController extends Controller {
    use ResolvesCurrentOrganization;

    /** Trägerart in Formular und URL → Modell. */
    public const CARRIERS = [
        'diary' => DiaryEntry::class,
        'project' => Project::class,
        'boq' => BillOfQuantity::class,
    ];

    public function __construct(
        private readonly TakeoffService $takeoffs,
        private readonly ModuleRegistry $modules,
        private readonly ModuleStatusResolver $moduleStatus,
    ) {}

    public function create(Request $request): View {
        $carrier = $this->carrierFrom((string) $request->query('carrier'), (string) $request->query('id'));
        Gate::authorize('createFor', [Takeoff::class, $carrier]);

        return view('takeoffs._form_dialog', [
            'takeoff' => new Takeoff(['title' => $this->defaultTitle($carrier), 'measured_on' => now()]),
            'carrierType' => (string) $request->query('carrier'),
            'carrierId' => (string) $request->query('id'),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'carrier_type' => ['required', Rule::in(array_keys(self::CARRIERS))],
            'carrier_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'measured_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
        $carrier = $this->carrierFrom($data['carrier_type'], $data['carrier_id']);
        Gate::authorize('createFor', [Takeoff::class, $carrier]);

        $takeoff = Takeoff::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'diary_entry_id' => $carrier instanceof DiaryEntry ? $carrier->id : null,
            'project_id' => $carrier instanceof Project ? $carrier->id : ($carrier instanceof DiaryEntry ? $carrier->project_id : null),
            'bill_of_quantity_id' => $carrier instanceof BillOfQuantity ? $carrier->id : null,
            'title' => $data['title'],
            'measured_on' => $data['measured_on'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.flash.created'));
    }

    public function show(Takeoff $takeoff): View {
        Gate::authorize('view', $takeoff);
        $takeoff->load(['lines.boqItem', 'lines.article', 'diaryEntry', 'project', 'billOfQuantity', 'attachments', 'transfers.target']);

        return view('takeoffs.show', [
            'takeoff' => $takeoff,
            'totals' => $this->takeoffs->totals($takeoff),
            'canEdit' => Gate::allows('update', $takeoff),
            'canTransition' => Gate::allows('transition', $takeoff),
            'transferKinds' => $this->transferKinds($takeoff),
            'presets' => $this->presets(),
        ]);
    }

    public function edit(Takeoff $takeoff): View {
        Gate::authorize('update', $takeoff);

        return view('takeoffs._form_dialog', ['takeoff' => $takeoff, 'carrierType' => null, 'carrierId' => null]);
    }

    public function update(Request $request, Takeoff $takeoff): RedirectResponse {
        Gate::authorize('update', $takeoff);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'measured_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
        $takeoff->update([...$data, 'updated_by' => Auth::id()]);

        return redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.flash.saved'));
    }

    public function destroy(Takeoff $takeoff): RedirectResponse {
        Gate::authorize('delete', $takeoff);
        $carrier = $takeoff->carrier();
        $takeoff->delete();

        return redirect()->to($carrier !== null ? $this->carrierUrl($carrier) : route('dashboard'))->with('success', __('takeoff.flash.deleted'));
    }

    public function transition(Request $request, Takeoff $takeoff): RedirectResponse {
        Gate::authorize('transition', $takeoff);
        $data = $request->validate(['status' => ['required', Rule::enum(TakeoffStatus::class)]]);
        /** @var User $user */
        $user = Auth::user();
        try {
            $this->takeoffs->transition($takeoff, TakeoffStatus::from($data['status']), $user);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.flash.status'));
    }

    public function transfer(Request $request, Takeoff $takeoff, TakeoffTransferService $transfers): RedirectResponse {
        Gate::authorize('transition', $takeoff);
        $data = $request->validate(['kind' => ['required', Rule::enum(TakeoffTransferKind::class)]]);
        $kind = TakeoffTransferKind::from($data['kind']);
        abort_unless(in_array($kind, $this->transferKinds($takeoff), true), 403);
        /** @var User $user */
        $user = Auth::user();
        try {
            return match ($kind) {
                TakeoffTransferKind::Quote => redirect()->route('quotes.show', $transfers->toQuote($takeoff, $user))->with('success', __('takeoff.transfer.flash.quote')),
                TakeoffTransferKind::Invoice => redirect()->route('invoices.show', $transfers->toInvoice($takeoff, $user))->with('success', __('takeoff.transfer.flash.invoice')),
                TakeoffTransferKind::Progress => redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.transfer.flash.progress', ['count' => $transfers->toProgress($takeoff, $user)])),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }
    }

    public function lineForm(Request $request, Takeoff $takeoff, ?TakeoffLine $line = null): View {
        Gate::authorize('update', $takeoff);
        abort_if($line !== null && $line->takeoff_id !== $takeoff->id, 404);
        $formula = $line->formula ?? TakeoffFormula::tryFrom((string) $request->query('formula')) ?? TakeoffFormula::Rectangle;
        $preset = [
            'formula' => $formula->value,
            'values' => [],
            'factor' => is_numeric($request->query('factor')) ? (string) $request->query('factor') : '1',
            'description' => is_string($request->query('description')) ? mb_substr($request->query('description'), 0, 255) : null,
            'unit' => is_string($request->query('unit')) ? mb_substr($request->query('unit'), 0, 16) : null,
        ];

        return view('takeoffs._line_dialog', [
            'takeoff' => $takeoff,
            'line' => $line ?? new TakeoffLine($preset),
            'formula' => $formula,
            'boqItems' => $takeoff->bill_of_quantity_id !== null
                ? BoqItem::query()->where('bill_of_quantity_id', $takeoff->bill_of_quantity_id)->orderBy('position')->get()->filter(fn (BoqItem $i): bool => $i->type->isPriceable())->values()
                : collect(),
            'articles' => Article::query()->where('sellable', true)->orderBy('name')->limit(500)->get(['id', 'number', 'name', 'base_unit']),
        ]);
    }

    public function storeLine(Request $request, Takeoff $takeoff): RedirectResponse {
        Gate::authorize('update', $takeoff);

        return $this->saveLine($request, $takeoff, null);
    }

    public function updateLine(Request $request, Takeoff $takeoff, TakeoffLine $line): RedirectResponse {
        Gate::authorize('update', $takeoff);
        abort_unless($line->takeoff_id === $takeoff->id, 404);

        return $this->saveLine($request, $takeoff, $line);
    }

    public function destroyLine(Takeoff $takeoff, TakeoffLine $line): RedirectResponse {
        Gate::authorize('update', $takeoff);
        abort_unless($line->takeoff_id === $takeoff->id, 404);
        $line->delete();

        return redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.flash.line_deleted'));
    }

    public function pdf(Takeoff $takeoff, TakeoffPdfRenderer $renderer): Response {
        Gate::authorize('view', $takeoff);

        return response($renderer->render($takeoff), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($takeoff) . '"',
        ]);
    }

    private function saveLine(Request $request, Takeoff $takeoff, ?TakeoffLine $line): RedirectResponse {
        $request->merge([
            'boq_item_id' => Sqid::decodeOrNumeric(BoqItem::class, $request->input('boq_item_id')),
            'article_id' => Sqid::decodeOrNumeric(Article::class, $request->input('article_id')),
        ]);
        $data = $request->validate([
            'formula' => ['required', Rule::enum(TakeoffFormula::class)],
            'values' => ['required', 'array', 'max:20'],
            'values.*' => ['nullable', 'string', 'max:500'],
            'factor' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:16'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'boq_item_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('boq_items')],
            'article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('articles')],
            'files' => ['nullable', 'array'],
            'files.photo' => ['nullable', ...FileAttacher::rule()],
        ]);
        if (isset($data['boq_item_id']) && BoqItem::query()->whereKey($data['boq_item_id'])->value('bill_of_quantity_id') !== $takeoff->bill_of_quantity_id) {
            $data['boq_item_id'] = null;
        }
        try {
            $this->takeoffs->saveLine($takeoff, TakeoffService::lineInput($data), $line);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }
        $photo = $request->file('files.photo');
        if ($photo instanceof UploadedFile) {
            app(FileAttacher::class)->store($takeoff, $photo, $request->user()?->id);
        }

        return redirect()->route('takeoffs.show', $takeoff)->with('success', __('takeoff.flash.line_saved'));
    }

    /**
     * Noch offene Übernahmen eines abgeschlossenen Blatts, deren Ziel das Modul
     * der Organisation und das Recht des Nutzers erlauben.
     *
     * @return list<TakeoffTransferKind>
     */
    private function transferKinds(Takeoff $takeoff): array {
        if ($takeoff->status !== TakeoffStatus::Completed || ! Gate::allows('transition', $takeoff)) {
            return [];
        }
        $done = $takeoff->transfers()->get()->map(static fn ($transfer): TakeoffTransferKind => $transfer->kind)->all();
        $organization = $this->currentOrganization();

        return array_values(array_filter(TakeoffTransferKind::cases(), function (TakeoffTransferKind $kind) use ($takeoff, $done, $organization): bool {
            $module = $this->modules->moduleForRoute($kind->targetRoute());
            if (in_array($kind, $done, true) || ($module !== null && ! $this->moduleStatus->isActiveFor($organization, $module))) {
                return false;
            }

            return match ($kind) {
                TakeoffTransferKind::Quote => Gate::allows('create', Quote::class),
                TakeoffTransferKind::Invoice => Gate::allows('create', Invoice::class),
                TakeoffTransferKind::Progress => $takeoff->lines()->whereNotNull('boq_item_id')->exists(),
            };
        }));
    }

    /**
     * Formelvorlagen der Organisation; unbekannte Formeln fallen heraus.
     *
     * @return list<array{label: string, formula: TakeoffFormula, unit: ?string, factor: string}>
     */
    private function presets(): array {
        $presets = [];
        foreach ((array) app(SettingsRegistry::class)->effective('takeoff.presets', $this->currentOrganization())->value as $preset) {
            $formula = is_array($preset) ? TakeoffFormula::tryFrom((string) ($preset['formula'] ?? '')) : null;
            if ($formula === null || trim((string) ($preset['label'] ?? '')) === '') {
                continue;
            }
            $presets[] = [
                'label' => (string) $preset['label'],
                'formula' => $formula,
                'unit' => isset($preset['unit']) ? (string) $preset['unit'] : null,
                'factor' => is_numeric($preset['factor'] ?? null) ? (string) $preset['factor'] : '1',
            ];
        }

        return $presets;
    }

    private function carrierFrom(string $type, string $id): Model {
        $class = self::CARRIERS[$type] ?? null;
        abort_if($class === null, 404);
        $key = Sqid::decodeOrNumeric($class, $id);
        abort_if($key === null, 404);

        return $class::query()->findOrFail($key);
    }

    private function defaultTitle(Model $carrier): string {
        return (string) __('takeoff.default_title', ['carrier' => match (true) {
            $carrier instanceof DiaryEntry => (string) $carrier->title,
            $carrier instanceof Project => (string) $carrier->name,
            $carrier instanceof BillOfQuantity => (string) $carrier->name,
            default => '',
        }]);
    }

    private function carrierUrl(Model $carrier): string {
        return match (true) {
            $carrier instanceof DiaryEntry => route('diary.show', $carrier),
            $carrier instanceof Project => route('projects.show', $carrier),
            $carrier instanceof BillOfQuantity => route('bill-of-quantities.show', $carrier),
            default => route('dashboard'),
        };
    }
}
