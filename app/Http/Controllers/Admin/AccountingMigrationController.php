<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Migration\{AccountingMigrationItem, AccountingMigrationRun};
use App\Models\{Organization, User};
use App\Services\AccountingMigration\AccountingMigrationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin-Assistent für den Buchhaltungswechsel (MVP-653, Issue #86):
 * planen → analysieren (Dry-Run) → Konflikte entscheiden → Doppelbetrieb →
 * am Stichtag umschalten → prüfen → abschließen. Jeder Schritt ist
 * auditiert; die Umschaltung bleibt blockiert, solange Konflikte oder
 * unklare Schreibausgänge bestehen.
 */
class AccountingMigrationController extends Controller {
    public function __construct(private readonly AccountingMigrationService $service) {}

    public function index(): View {
        $user = $this->manageUser();
        $organization = $this->organization($user);

        $run = $this->service->openRunFor($organization);

        return view('admin.accounting-migration.index', [
            'run' => $run,
            'areas' => MigrationDataArea::cases(),
            'providers' => MigrationProvider::cases(),
            'history' => AccountingMigrationRun::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', [AccountingMigrationStatus::Completed->value, AccountingMigrationStatus::Cancelled->value])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'items' => $run === null ? collect() : $run->items()
                ->orderByRaw("CASE status WHEN 'conflict' THEN 0 WHEN 'failed' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
                ->orderBy('data_area')
                ->limit(200)
                ->get(),
            'blockers' => $run === null ? [] : $this->service->cutoverBlockers($run),
            'completionBlockers' => $run === null ? [] : $this->service->completionBlockers($run),
        ]);
    }

    /** Lauf planen (Datenbereiche + Stichtag). */
    public function store(Request $request): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);

        $data = $request->validate([
            'areas' => ['required', 'array', 'min:1'],
            'areas.*' => ['string', 'in:customers,suppliers,articles,documents'],
            'cutover_on' => ['nullable', 'date', 'after_or_equal:today'],
            // Richtung ist frei wählbar (MVP-653): Quelle ≠ Ziel.
            'source' => ['required', 'string', 'in:lexoffice,orgamax'],
            'target' => ['required', 'string', 'in:lexoffice,orgamax', 'different:source'],
        ]);

        $areas = array_values(array_filter(array_map(
            static fn (string $value): ?MigrationDataArea => MigrationDataArea::tryFrom($value),
            (array) $data['areas'],
        )));

        try {
            $this->service->plan(
                $organization,
                $areas,
                ! empty($data['cutover_on']) ? CarbonImmutable::parse((string) $data['cutover_on']) : null,
                $user,
                MigrationProvider::from((string) $data['source']),
                MigrationProvider::from((string) $data['target']),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.accounting-migration.index')
            ->with('success', __('Wechsel geplant — jetzt die Analyse (Dry-Run) starten.'));
    }

    /** Analyse/Dry-Run: schreibt in kein Fremdsystem. */
    public function analyze(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        try {
            $this->service->analyze($run, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Analyse abgeschlossen — es wurde nichts in ein Fremdsystem geschrieben.'));
    }

    /** Entscheidung zu einer Migrationsposition. */
    public function decide(Request $request, string $sqid, string $itemSqid): RedirectResponse {
        $user = $this->manageUser();
        $organization = $this->organization($user);
        $run = $this->run($organization, $sqid);

        $itemId = app(\App\Services\SqidEncoder::class)->decode(AccountingMigrationItem::class, $itemSqid);
        $item = $itemId === null ? null : $run->items()->whereKey($itemId)->first();
        abort_unless($item instanceof AccountingMigrationItem, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:matched,skipped,historic,conflict'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->decideItem($item, (string) $data['status'], $user, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Entscheidung gespeichert.'));
    }

    /** Doppelbetrieb starten (beide Verbindungen aktiv). */
    public function startParallel(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        $blockers = $this->service->startParallelRun($run, $user);

        return $blockers === []
            ? back()->with('success', __('Doppelbetrieb gestartet.'))
            : back()->with('error', implode(' ', $blockers));
    }

    /** Umschaltung am Stichtag (setzt die Fakturahoheit auf das Zielsystem). */
    public function cutover(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        $blockers = $this->service->cutover($run, $user);

        return $blockers === []
            ? back()->with('success', __('Umgeschaltet — neue Fakturavorgänge entstehen ab sofort ausschließlich im Zielsystem.'))
            : back()->with('error', implode(' ', $blockers));
    }

    /** Abschluss mit Nachweis. */
    public function complete(string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        $blockers = $this->service->complete($run, $user);

        return $blockers === []
            ? redirect()->route('admin.accounting-migration.index')->with('success', __('Wechsel abgeschlossen.'))
            : back()->with('error', implode(' ', $blockers));
    }

    public function cancel(Request $request, string $sqid): RedirectResponse {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        $reason = trim((string) ($request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? ''));
        $this->service->cancel($run, $user, $reason !== '' ? $reason : null);

        return redirect()->route('admin.accounting-migration.index')->with('success', __('Wechsel abgebrochen.'));
    }

    /** Abschlussprotokoll als CSV (Umfang, Zählwerke, Abweichungen). */
    public function report(string $sqid): \Symfony\Component\HttpFoundation\Response {
        $user = $this->manageUser();
        $run = $this->run($this->organization($user), $sqid);

        $rows = (static function () use ($run): \Generator {
            foreach ($run->items()->orderBy('data_area')->orderBy('id')->cursor() as $item) {
                yield [
                    $item->data_area->label(),
                    $item->status,
                    $item->source_external_id,
                    $item->target_external_id,
                    $item->display_title,
                    $item->note,
                ];
            }
        })();

        return \App\Support\CsvExport::streamFromRows(
            sprintf('buchhaltungswechsel-%s.csv', $run->sqid),
            ['Bereich', 'Status', 'Quelle', 'Ziel', 'Bezeichnung', 'Hinweis'],
            $rows,
        );
    }

    private function run(Organization $organization, string $sqid): AccountingMigrationRun {
        $id = app(\App\Services\SqidEncoder::class)->decode(AccountingMigrationRun::class, $sqid);
        $run = $id === null ? null : AccountingMigrationRun::query()
            ->where('organization_id', $organization->id)
            ->whereKey($id)
            ->first();
        abort_unless($run instanceof AccountingMigrationRun, 404);

        return $run;
    }

    private function manageUser(): User {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user !== null && ($user->isAdmin() || $user->can(Permission::AccountingMigrationManage->value)), 403);

        return $user;
    }

    private function organization(User $user): Organization {
        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 422, (string) __('Kein Organisationskontext.'));

        return $organization;
    }
}
