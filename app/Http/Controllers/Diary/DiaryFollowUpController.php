<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryFollowUpController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Diary;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Classification\EntryType;
use App\Models\Diary\DiaryEntry;
use App\Services\Diary\FollowUpPeriodService;
use App\Support\Period\PeriodKey;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Folgeperiode anlegen (Feature 198, MVP-1637): einzeln am Auftrag oder
 * gesammelt für eine Eintragsart und Periode — erst die Vorschau, dann die
 * Anlage. Die Regeln (Übernahme, Dublettenschutz) stehen im
 * {@see FollowUpPeriodService}.
 */
class DiaryFollowUpController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly FollowUpPeriodService $followUps) {}

    public function store(DiaryEntry $diary): RedirectResponse {
        $this->assertSameOrganization($diary);
        Gate::authorize('createFollowUp', $diary);

        try {
            $entry = $this->followUps->create($diary, $this->authUser());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('diary.show', $entry)->with('success', __('diary.follow_up.flash.created', ['period' => $entry->period_key?->label() ?? '—']));
    }

    /** Sammelanlage: Eintragsart und Periode wählen, Vorschau prüfen. */
    public function index(Request $request): View {
        $this->authorizeBulk();
        [$type, $period] = $this->selection($request);

        return view('diary.follow-up', [
            'entryTypes' => EntryType::query()->active()->ordered()->get(['id', 'label', 'slug', 'requires_period']),
            'selectedType' => $type,
            'period' => $period,
            'periodInput' => (string) $request->query('period_key', ''),
            'rows' => $type !== null && $period !== null ? $this->followUps->preview($type, $period) : null,
            'limit' => FollowUpPeriodService::BULK_LIMIT,
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse {
        $this->authorizeBulk();
        [$type, $period] = $this->selection($request);
        if ($type === null || $period === null) {
            return back()->withErrors(['period_key' => __('diary.follow_up.error.selection')]);
        }

        $result = $this->followUps->createMany($type, $period, $this->authUser());

        return redirect()->route('diary.follow-up.index', ['entry_type' => $type->sqid, 'period_key' => $period->key()])
            ->with('success', __('diary.follow_up.flash.bulk', ['created' => count($result['created']), 'skipped' => $result['skipped'], 'period' => $period->next()->label()]));
    }

    /** Gesammelt nur, wer alle Aufträge sieht und für andere anlegen darf (übernommen wird die Zuständigkeit). */
    private function authorizeBulk(): void {
        $user = $this->authUser();
        abort_unless(
            $user->can(Permission::DiaryViewAny->value)
            && $user->can(Permission::DiaryCreate->value)
            && $user->can(Permission::DiaryCreateForOthers->value),
            403,
        );
    }

    /** @return array{0: EntryType|null, 1: PeriodKey|null} */
    private function selection(Request $request): array {
        $typeId = Sqid::decode(EntryType::class, is_string($request->input('entry_type')) ? $request->input('entry_type') : null);
        $type = $typeId !== null ? EntryType::query()->find($typeId) : null;
        $period = PeriodKey::tryParse(is_string($request->input('period_key')) ? $request->input('period_key') : null);

        return [$type instanceof EntryType ? $type : null, $period];
    }
}
