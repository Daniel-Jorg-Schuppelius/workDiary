<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    /** Serverseitig akzeptierte MIME-Typen, geprüft über PHP Fileinfo (nicht Client-Header) */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-zip-compressed',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
        'task' => Task::class,
        'customer' => Customer::class,
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('create', Attachment::class);

        $parent = $this->resolveParent($type, $id);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(self::MAX_BYTES / 1024)],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return back()->withErrors(['file' => __('Dateityp nicht erlaubt.')]);
        }

        // Serverseitiger MIME-Check über PHP Fileinfo – unabhängig vom Client-Header
        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($serverMime, self::ALLOWED_MIMES, true)) {
            return back()->withErrors(['file' => __('Dateityp nicht erlaubt.')]);
        }

        $folder = 'attachments/'.now()->format('Y/m');
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs($folder, $filename, 'local');

        /** @var DiaryEntry|Comment|OnCallShift|EmergencyAssignment|Task|Customer $parent */
        $parent->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
            'mime' => $serverMime,
            'size' => $file->getSize(),
        ]);

        return back()->with('success', __('Anhang hochgeladen.'));
    }

    public function download(Request $request, Attachment $attachment): BinaryFileResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', __('Anhang gelöscht.'));
    }

    /**
     * Generate a temporary signed download URL (15 min).
     */
    public static function downloadUrl(Attachment $attachment): string
    {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['attachment' => $attachment->id]);
    }

    private function resolveParent(string $type, int $id): Model
    {
        $class = self::TYPE_MAP[$type] ?? null;
        if ($class === null) {
            abort(404);
        }

        return $class::findOrFail($id);
    }

    /**
     * Bereinigt einen vom Client gelieferten Dateinamen:
     * entfernt Pfad-Traversal-Sequenzen und behält nur druckbare Zeichen.
     */
    private function sanitizeFilename(string $name): string
    {
        // Nur den Dateinamen ohne Verzeichnis-Anteile verwenden
        $name = basename($name);
        // Null-Bytes, Steuerzeichen und bekannte Traversal-Muster entfernen
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', $name) ?? 'file';

        // Auf 255 Zeichen begrenzen
        return mb_substr($name, 0, 255);
    }
}
