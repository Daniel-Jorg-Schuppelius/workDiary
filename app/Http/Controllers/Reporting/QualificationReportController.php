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
use App\Models\Qualification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Qualifikationsmatrix: Mitarbeiter × Qualifikationen mit Ablaufdaten.
 */
class QualificationReportController extends Controller
{
    private const EXPIRY_WARN_DAYS = 30;

    public function index(Request $request): View|SymfonyResponse
    {
        $userId = (int) Auth::id();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();

        $today = Carbon::today();
        $warnDate = $today->copy()->addDays(self::EXPIRY_WARN_DAYS);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Qualification> $qualifications */
        $qualifications = Qualification::query()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'is_active']);

        $usersQuery = User::query()
            ->whereHas('qualifications')
            ->orderBy('name');
        if (! $isAdmin) {
            $usersQuery->where('id', $userId);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
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
            return $this->exportCsv($users, $qualifications, $matrix);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($users, $qualifications, $matrix, [
                'total_assignments' => $totalAssignments,
                'expiring' => $expiring,
                'expired' => $expired,
            ]);
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  \Illuminate\Database\Eloquent\Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     */
    private function exportCsv($users, $qualifications, array $matrix): Response
    {
        $filename = 'qualifikationen_'.Carbon::today()->toDateString().'.csv';
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
                        $val = 'ABGELAUFEN '.$val;
                    } elseif ($cell['state'] === 'expiring') {
                        $val = 'LÄUFT AB '.$val;
                    }
                    $line[] = $val;
                }
            }
            $rows[] = $line;
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"'.str_replace('"', '""', $s).'"';
                }

                return $s;
            }, $row))."\r\n";
        }

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $users
     * @param  \Illuminate\Database\Eloquent\Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     * @param  array{total_assignments:int, expiring:int, expired:int}  $totals
     */
    private function exportPdf($users, $qualifications, array $matrix, array $totals): SymfonyResponse
    {
        $filename = 'qualifikationen_'.Carbon::today()->toDateString().'.pdf';
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.qualifications', [
            'users' => $users,
            'qualifications' => $qualifications,
            'matrix' => $matrix,
            'totals' => $totals,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
