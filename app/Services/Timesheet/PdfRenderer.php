<?php

namespace App\Services\Timesheet;

use App\Models\Timesheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class PdfRenderer
{
    public function render(Timesheet $timesheet): string
    {
        $timesheet->loadMissing(['project', 'user', 'entries.task', 'materialUsages.material', 'signatureAttachment']);

        $signaturePng = null;
        if ($timesheet->signatureAttachment) {
            $att = $timesheet->signatureAttachment;
            if (Storage::disk($att->disk)->exists($att->path)) {
                $signaturePng = 'data:image/png;base64,'.base64_encode(
                    Storage::disk($att->disk)->get($att->path) ?? ''
                );
            }
        }

        $html = View::make('pdf.timesheet', [
            'timesheet' => $timesheet,
            'signaturePng' => $signaturePng,
        ])->render();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return (string) $pdf->output();
    }

    public function store(Timesheet $timesheet): string
    {
        $bytes = $this->render($timesheet);
        $path = sprintf('timesheets/pdf/%d.pdf', $timesheet->id);
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
