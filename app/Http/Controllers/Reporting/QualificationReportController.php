<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{Qualification, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Qualifikationsmatrix: Mitarbeiter × Qualifikationen mit Ablaufdaten.
 */
class QualificationReportController extends Controller {
    use RendersReportPdf;
    use ResolvesReportScope;
    use WritesReportCsv;

    private const EXPIRY_WARN_DAYS = 30;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $isAdmin = $this->viewerIsAdmin();

        $today = Carbon::today();
        $warnDate = $today->copy()->addDays(self::EXPIRY_WARN_DAYS);

        /** @var Collection<int, Qualification> $qualifications */
        $qualifications = Qualification::query()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'is_active']);

        $usersQuery = User::inCurrentOrganization()
            ->whereHas('qualifications')
            ->orderBy('name');
        if (! $isAdmin) {
            $usersQuery->where('id', $userId);
        }
        /** @var Collection<int, User> $users */
        $users = $usersQuery->with(['qualifications:id,name'])->get(['id', 'name']);

        /** @var array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>> $matrix */
        $matrix = [];
        $expiring = 0;
        $expired = 0;
        $totalAssignments = 0;
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $matrix[$uid] = [];
            foreach ($u->qualifications as $q) {
                $totalAssignments++;
                $pivot = $q->getRelationValue('pivot');
                $validUntil = $pivot !== null ? $pivot->getAttribute('valid_until') : null;
                $validFrom = $pivot !== null ? $pivot->getAttribute('valid_from') : null;
                $state = 'valid';
                if ($validUntil !== null) {
                    $end = Carbon::parse((string) $validUntil)->startOfDay();
                    if ($end->lt($today)) {
                        $state = 'expired';
                        $expired++;
                    } elseif ($end->lte($warnDate)) {
                        $state = 'expiring';
                        $expiring++;
                    }
                }
                $matrix[$uid][(int) $q->id] = [
                    'valid_from' => $validFrom !== null ? Carbon::parse((string) $validFrom)->toDateString() : null,
                    'valid_until' => $validUntil !== null ? Carbon::parse((string) $validUntil)->toDateString() : null,
                    'state' => $state,
                ];
            }
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($users, $qualifications, $matrix, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($users, $qualifications, $matrix, [
                'total_assignments' => $totalAssignments,
                'expiring' => $expiring,
                'expired' => $expired,
            ], $request);
        }

        return view('reports.qualifications', [
            'users' => $users,
            'qualifications' => $qualifications,
            'matrix' => $matrix,
            'isAdmin' => $isAdmin,
            'totals' => [
                'users' => $users->count(),
                'qualifications' => $qualifications->count(),
                'assignments' => $totalAssignments,
                'expiring' => $expiring,
                'expired' => $expired,
            ],
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     */
    private function exportCsv($users, $qualifications, array $matrix, Request $request): Response {
        $filename = 'qualifikationen_' . Carbon::today()->toDateString() . '.csv';
        $rows = [];
        $header = ['Mitarbeiter'];
        foreach ($qualifications as $q) {
            $header[] = $q->name;
        }
        $rows[] = $header;
        foreach ($users as $u) {
            $line = [$u->name];
            foreach ($qualifications as $q) {
                $cell = $matrix[(int) $u->id][(int) $q->id] ?? null;
                if ($cell === null) {
                    $line[] = '';
                } else {
                    $val = $cell['valid_until'] ?? 'gültig';
                    if ($cell['state'] === 'expired') {
                        $val = 'ABGELAUFEN ' . $val;
                    } elseif ($cell['state'] === 'expiring') {
                        $val = 'LÄUFT AB ' . $val;
                    }
                    $line[] = $val;
                }
            }
            $rows[] = $line;
        }

        return $this->csvWithMetadata($rows, $filename, 'qualifications', [
            'date' => Carbon::today()->toDateString(),
        ], $request);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     * @param  array{total_assignments:int, expiring:int, expired:int}  $totals
     */
    private function exportPdf($users, $qualifications, array $matrix, array $totals, Request $request): SymfonyResponse {
        $filename = 'qualifikationen_' . Carbon::today()->toDateString() . '.pdf';
        return $this->pdfDownload('reports.pdf.qualifications', [
            'users' => $users,
            'qualifications' => $qualifications,
            'matrix' => $matrix,
            'totals' => $totals,
        ], $filename, 'landscape', $request, 'qualifications', ['date' => Carbon::today()->toDateString()]);
    }
}
