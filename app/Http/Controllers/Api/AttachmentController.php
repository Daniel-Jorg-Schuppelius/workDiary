<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Attachments\AttachmentController as WebAttachmentController;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachments\Attachment;
use App\Models\Travel\Expense;
use App\Services\Attachments\FileAttacher;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller {
    /** Dieselben Träger wie im Web — eine Liste für beide Wege (Konsolidierungs-Audit 2026-10, k3-7). */
    private const TYPE_MAP = WebAttachmentController::TYPE_MAP;

    #[OA\Post(
        path: '/attachments/{type}/{id}',
        summary: 'Anhang hochladen',
        tags: ['Attachments'],
        security: [['bearerAuth' => ['attachments:write']]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, description: 'Trägerobjekt', schema: new OA\Schema(type: 'string', enum: ['diary', 'comment', 'shift', 'assignment', 'task', 'customer', 'supplier', 'organization', 'user', 'asset', 'knowledge', 'service-ticket', 'expense', 'damage', 'recall', 'hazard-assessment', 'safety-instruction', 'takeoff'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Numerische ID des Trägerobjekts', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [
            new OA\Property(property: 'file', type: 'string', format: 'binary'),
        ]))),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Request $request, string $type, int $id): JsonResponse {
        Gate::authorize('create', Attachment::class);
        $class = self::TYPE_MAP[$type] ?? abort(404);
        $parent = $class::findOrFail($id);

        // Anhängen erfordert das Bearbeiten-Recht am Trägerobjekt (nicht nur Sichtbarkeit).
        Gate::authorize('update', $parent);

        // Belege an Auslagen: eigenes Limit und eigene MIME-Liste wie im Web (config/expenses.php).
        $maxKb = $parent instanceof Expense
            ? min(FileAttacher::maxKb(), max(1, (int) config('expenses.max_upload_mb', 10)) * 1024)
            : FileAttacher::maxKb();

        $request->validate(['file' => ['required', 'file', 'max:' . $maxKb]]);
        $file = $request->file('file');
        if ($parent instanceof Expense) {
            $expenseMimes = (array) config('expenses.allowed_mime_types', []);
            if ($expenseMimes !== [] && ! in_array($file->getMimeType() ?? '', $expenseMimes, true)) {
                return response()->json(['message' => __('Dateityp nicht erlaubt.')], 422);
            }
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        if (! in_array($ext, FileAttacher::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['message' => __('Dateityp nicht erlaubt.')], 422);
        }
        $serverMime = $file->getMimeType() ?? '';
        if (! in_array($serverMime, FileAttacher::ALLOWED_MIMES, true)) {
            return response()->json(['message' => __('Dateityp nicht erlaubt.')], 422);
        }
        // Kanonische Ablage über FileAttacher (M46-Rest, Folgepunkt 2026-07-20);
        // bringt dem API-Pfad zugleich den H8-Quota-Guard (ValidationException
        // → 422-JSON), der hier zuvor fehlte.
        $att = app(FileAttacher::class)->store($parent, $file, Auth::id() !== null ? (int) Auth::id() : null);

        return (new AttachmentResource($att->load('uploader:id,name')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/attachments/{attachment}/download',
        summary: 'Anhang herunterladen',
        tags: ['Attachments'],
        security: [['bearerAuth' => ['attachments:read']]],
        parameters: [new OA\Parameter(name: 'attachment', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'Dateiinhalt (binary)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function download(Attachment $attachment): BinaryFileResponse {
        Gate::authorize('view', $attachment);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->download($disk->path($attachment->path), $attachment->original_name);
    }

    #[OA\Delete(
        path: '/attachments/{attachment}',
        summary: 'Anhang löschen',
        tags: ['Attachments'],
        security: [['bearerAuth' => ['attachments:write']]],
        parameters: [new OA\Parameter(name: 'attachment', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Attachment $attachment): JsonResponse {
        Gate::authorize('delete', $attachment);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['status' => 'deleted']);
    }
}
