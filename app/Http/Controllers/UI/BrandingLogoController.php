<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BrandingLogoController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use App\Models\Attachments\Attachment;
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\{MorphMap, SqidEncoder};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Org-Logo unter der dauerhaft signierten URL aus BrandingService::logoUrl().
 * Die Signatur ist die Berechtigung: Header aller Mitglieder und öffentliche
 * Portale laden das Logo ohne Anhang-Policy und ohne Org-Kontext.
 */
class BrandingLogoController extends Controller {
    /** Ein neues Logo ist ein neuer Anhang mit neuer URL — die alte darf dauerhaft gecacht bleiben. */
    private const CACHE_SECONDS = 31536000;

    public function __invoke(string $logo): BinaryFileResponse {
        $id = app(SqidEncoder::class)->decode(Attachment::class, $logo);
        abort_if($id === null, 404);

        $attachment = Attachment::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereKey($id)
            ->where('attachable_type', MorphMap::alias(Organization::class))
            ->whereIn('meta_type', [Attachment::META_LOGO, Attachment::META_LOGO_DARK])
            ->first();
        abort_if($attachment === null, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return response()->file($disk->path($attachment->path), [
            'Cache-Control' => 'private, max-age=' . self::CACHE_SECONDS . ', immutable',
        ]);
    }
}
