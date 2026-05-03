<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiaryExportController extends Controller {
    public function csv(Request $request): StreamedResponse {
        $query = $this->buildQuery($request);

        $filename = 'tagebuch_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'wb');
            assert($out !== false);
            // BOM für Excel-UTF8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
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
            ], ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $entry) {
                    /** @var DiaryEntry $entry */
                    fputcsv($out, [
                        $entry->id,
                        $entry->statusLabel(),
                        optional($entry->user)->name ?? '',
                        optional($entry->start_at)->format('Y-m-d H:i') ?? '',
                        optional($entry->end_at)->format('Y-m-d H:i') ?? '',
                        $this->oneLine($entry->content),
                        $this->oneLine($entry->response ?? ''),
                        $entry->tags->pluck('name')->implode(', '),
                        $entry->is_archived ? '1' : '0',
                        optional($entry->created_at)->format('Y-m-d H:i') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\DiaryEntry> */
    private function buildQuery(Request $request): \Illuminate\Database\Eloquent\Builder {
        /** @var \Illuminate\Database\Eloquent\Builder<DiaryEntry> $query */
        $query = DiaryEntry::query()
            ->with(['user:id,name', 'tags:id,name'])
            ->orderByDesc('start_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('start_at', '<=', $request->to);
        }
        if ($request->boolean('mine')) {
            $query->where('user_id', Auth::id());
        }
        if (! $request->boolean('archived')) {
            $query->where('is_archived', false);
        }
        $tagId = $request->integer('tag');
        if ($tagId > 0) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('content', 'like', $like)->orWhere('response', 'like', $like);
            });
        }

        return $query;
    }

    private function oneLine(string $value): string {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
