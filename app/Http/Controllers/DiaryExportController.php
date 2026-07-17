<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Services\UI\DateRangeContext;
use App\Support\CsvExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiaryExportController extends Controller {
    public function csv(Request $request): StreamedResponse {
        $query = $this->buildQuery($request);

        $filename = 'tagebuch_' . now()->format('Ymd_His') . '.csv';

        $rows = (function () use ($query): \Generator {
            /** @var DiaryEntry $entry */
            foreach ($query->lazy(500) as $entry) {
                yield [
                    $entry->id,
                    $this->oneLine($entry->statusLabel()),
                    $this->oneLine(optional($entry->user)->name ?? ''),
                    optional($entry->start_at)->format('Y-m-d H:i') ?? '',
                    optional($entry->end_at)->format('Y-m-d H:i') ?? '',
                    $this->oneLine($entry->content ?? ''),
                    $this->oneLine($entry->response ?? ''),
                    $this->oneLine($entry->tags->pluck('name')->implode(', ')),
                    $entry->is_archived ? '1' : '0',
                    optional($entry->created_at)->format('Y-m-d H:i') ?? '',
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            __('ID'),
            __('Status'),
            __('Mitarbeiter'),
            __('Von'),
            __('Bis'),
            __('Inhalt'),
            __('Antwort'),
            __('Tags'),
            __('Archiviert'),
            __('Erstellt'),
        ], $rows);
    }

    public function pdf(Request $request): View {
        $query = $this->buildQuery($request);
        $entries = $query->limit(2000)->get();

        return view('diary.export-pdf', [
            'entries' => $entries,
            'filters' => $request->only('status', 'from', 'to', 'mine', 'archived', 'tag', 'q'),
            'generatedAt' => now(),
        ]);
    }

    /** @return Builder<DiaryEntry> */
    private function buildQuery(Request $request): Builder {
        /** @var Builder<DiaryEntry> $query */
        $query = DiaryEntry::query()
            ->with(['user:id,name', 'tags:id,name'])
            ->orderByDesc('start_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }

        // Hybrid-Filter: explizite Query-Parameter haben Vorrang, sonst globaler
        // Kontext. Modus-bewusste Overlap-Logik identisch zur Auftragsliste.
        $range = app(DateRangeContext::class)->current();
        $from = $request->filled('from') ? (string) $request->from : $range['from']->toDateString();
        $to = $request->filled('to') ? (string) $request->to : $range['to']->toDateString();
        $query->overlappingDateRange($from, $to);

        if ($request->boolean('mine')) {
            $query->where('user_id', Auth::id());
        }
        if (! $request->boolean('archived')) {
            $query->where('is_archived', false);
        }
        // Die Export-Links reichen die Index-Filter weiter — tag ist dort ein
        // Sqid; integer() ergäbe 0 und der Export ignorierte den Filter still.
        $tagId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Tag::class, $request->string('tag')->toString());
        if ($tagId !== null && $tagId > 0) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->search($q);
        }

        return $query;
    }

    // Mehrzeiler flachziehen; Formel-Guard übernimmt CsvExport zentral.
    private function oneLine(string $value): string {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
