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

use App\Models\{Asset, Attachment, Comment, Customer, DiaryEntry, EmergencyAssignment, KnowledgeArticle, OnCallShift, Organization, ServiceTicket, Supplier, Task, User};
use App\Services\Attachments\{FileAttacher, ImageMetaUploader};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate, Storage, URL};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller {
    public function __construct(private readonly ImageMetaUploader $imageUploader) {}

    // Größenlimit: {@see FileAttacher::maxKb()} — eine Wahrheit, org-konfigurierbar (uploads.attachment_kb).
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'log', 'zip', 'docx', 'xlsx'];

    /** Serverseitig akzeptierte MIME-Typen, geprüft über PHP Fileinfo (nicht Client-Header) */
    public const ALLOWED_MIMES = [
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

    /**
     * Route-Typ → Modell. Öffentlich, damit die attachments.store-Route ihre
     * whereIn-Constraint hieraus ableitet (eine Wahrheit — Vollaudit 2026-07, M1:
     * supplier/knowledge/service-ticket liefen zuvor auf 404).
     */
    public const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
        'task' => Task::class,
        'customer' => Customer::class,
        'supplier' => Supplier::class,
        'organization' => Organization::class,
        'user' => User::class,
        'asset' => Asset::class,
        'knowledge' => KnowledgeArticle::class,
        'service-ticket' => ServiceTicket::class,
    ];

    /**
     * Größenlimit (KB, aus config/branding.php) für Bild-Metarollen (Logo/Avatar)
     * mit strikter Validierung + Vorgänger-Ersetzung; null = kein Spezialpfad.
     */
    private function imageMetaLimitKb(string $meta): ?int {
        return match ($meta) {
            Attachment::META_LOGO, Attachment::META_LOGO_DARK => (int) config('branding.limits.logo_kb', 2048),
            Attachment::META_AVATAR => (int) config('branding.limits.avatar_kb', 1024),
            default => null,
        };
    }

    public function store(Request $request, string $type, string $id): RedirectResponse {
        Gate::authorize('create', Attachment::class);

        $parent = $this->resolveParent($type, $id);
        $meta = $request->input('meta_type');
        $meta = is_string($meta) && $meta !== '' ? $meta : null;

        // Branding-/Avatar-Uploads laufen über einen separaten, strengeren Pfad.
        if ($meta !== null && $this->imageMetaLimitKb($meta) !== null) {
            return $this->storeImageMeta($request, $parent, $meta);
        }

        // Anhängen erfordert das Bearbeiten-Recht am Trägerobjekt, nicht nur Sichtbarkeit.
        Gate::authorize('update', $parent);

        $request->validate([
            'file' => ['required', 'file', 'max:' . FileAttacher::maxKb()],
        ]);

        $file = $request->file('file');

        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return back()->withErrors(['file' => __('Dateityp nicht erlaubt.')]);
        }

        // Serverseitiger MIME-Check über PHP Fileinfo – unabhängig vom Client-Header
        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($serverMime, self::ALLOWED_MIMES, true)) {
            return back()->withErrors(['file' => __('Dateityp nicht erlaubt.')]);
        }

        // Kanonische Ablage über FileAttacher (M46-Rest, Folgepunkt 2026-07-20):
        // Ordner/UUID/sanitize/morphMany UND der H8-Quota-Guard (wirft
        // ValidationException → Redirect mit errors['file'], wie zuvor).
        app(FileAttacher::class)->store($parent, $file, Auth::id() !== null ? (int) Auth::id() : null);

        return back()->with('success', __('Anhang hochgeladen.'));
    }

    /**
     * Spezialisierter Upload-Pfad für Branding-Logos und Avatare: erzwingt
     * Bildformate (KEIN SVG), meta-spezifische Größenlimits, ersetzt den
     * Vorgänger gleichen meta_type (inkl. Storage-Cleanup), eigene Autorisierung.
     */
    private function storeImageMeta(Request $request, Model $parent, string $meta): RedirectResponse {
        /** @var Organization|User $parent */
        $this->authorizeImageMeta($parent, $meta);

        $maxKb = $this->imageMetaLimitKb($meta);
        if ($maxKb === null) {
            abort(422);
        }

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        try {
            $this->imageUploader->replace($parent, $meta, $file, $maxKb);
        } catch (ValidationException $e) {
            throw $e;
        }

        return back()->with('success', __('Bild hochgeladen.'));
    }

    /**
     * Löscht einen Branding-/Avatar-Anhang gezielt über `meta_type`.
     * Wird vom <x-file-upload> "Entfernen"-Toggle genutzt, damit das
     * UI nicht die generische Attachment-ID-Route aufrufen muss.
     */
    public function destroyMeta(string $type, string $id, string $meta): RedirectResponse {
        if ($this->imageMetaLimitKb($meta) === null) {
            abort(404);
        }

        $parent = $this->resolveParent($type, $id);
        /** @var Organization|User $parent */
        $this->authorizeImageMeta($parent, $meta);

        $this->imageUploader->delete($parent, $meta);

        return back()->with('success', __('Bild entfernt.'));
    }

    /**
     * Autorisiert Branding-/Avatar-Operationen. Logos einer Organisation
     * dürfen nur deren Admins ändern; Avatare nur der eigene User
     * (bzw. ein Admin).
     */
    private function authorizeImageMeta(Model $parent, string $meta): void {
        if ($parent instanceof Organization && in_array($meta, [Attachment::META_LOGO, Attachment::META_LOGO_DARK], true)) {
            Gate::authorize('manageBranding', $parent);

            return;
        }

        if ($parent instanceof User && $meta === Attachment::META_AVATAR) {
            /** @var User|null $current */
            $current = Auth::user();
            if ($current === null) {
                abort(403);
            }
            if ($current->id !== $parent->id && ! $current->isAdmin()) {
                abort(403);
            }

            return;
        }

        abort(422);
    }

    public function download(Request $request, Attachment $attachment): BinaryFileResponse {
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

    public function destroy(Attachment $attachment): RedirectResponse {
        // Kassenbelege (MVP-414) sind GoBD-append-only und auch für Admins nie
        // löschbar (Vollaudit 2026-07, M37) — der Admin-Bypass der Policy
        // (HasAdminBypass::before) greift hier bewusst nicht.
        abort_if($attachment->attachable instanceof \App\Models\CashEntry, 403);

        Gate::authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', __('Anhang gelöscht.'));
    }

    /**
     * Kundenfreigabe fürs Portal umschalten (Feature 012, Rang 54): wer das
     * Trägerobjekt bearbeiten darf, entscheidet über die Sichtbarkeit.
     */
    public function toggleCustomerVisibility(Attachment $attachment): RedirectResponse {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        Gate::authorize('update', $parent);

        $attachment->update(['customer_visible' => ! $attachment->customer_visible]);

        return back()->with('success', $attachment->customer_visible
            ? __('Anhang für das Kundenportal freigegeben.')
            : __('Anhang auf intern gestellt.'));
    }

    /**
     * Generate a temporary signed download URL (15 min).
     */
    public static function downloadUrl(Attachment $attachment): string {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), ['attachment' => $attachment]);
    }

    private function resolveParent(string $type, string $id): Model {
        $class = self::TYPE_MAP[$type] ?? null;
        if ($class === null) {
            abort(404);
        }

        /** @var Asset|Comment|Customer|DiaryEntry|EmergencyAssignment|OnCallShift|Organization|Task|User $instance */
        $instance = new $class();
        $resolved = $instance->resolveRouteBinding($id);
        if (! $resolved instanceof Model) {
            abort(404);
        }

        return $resolved;
    }
}
