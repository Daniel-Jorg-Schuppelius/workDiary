<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpMediaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bild-Auslieferung der Hilfeartikel (Feature 039, MVP-754): Repo-Assets aus
 * `resources/help/media` — nur für angemeldete Nutzer, nur Bild-Extensions
 * (bewusst KEIN SVG: Skript-Risiko), realpath-Containment gegen Traversal.
 * Locale-Auflösung passiert beim Reindex (Loader), hier wird nur der
 * fertige Pfad ausgeliefert. CSP `img-src 'self'` bleibt unangetastet.
 */
class HelpMediaController extends Controller {
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /** Repo-Assets ändern sich nur mit dem Deploy — ein Tag Browser-Cache reicht. */
    private const CACHE_SECONDS = 86400;

    public function show(Request $request, string $path): BinaryFileResponse {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, self::ALLOWED_EXTENSIONS, true), 404);

        $root = realpath((string) config('help-center.media_path'));
        abort_if($root === false, 404);

        $file = realpath($root . DIRECTORY_SEPARATOR . $path);
        // Containment: aufgelöster Pfad muss INNERHALB des Media-Roots liegen
        // (fängt ../-Traversal und Symlink-Ausbrüche gleichermaßen).
        abort_if($file === false || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR), 404);

        return response()
            ->file($file, ['Cache-Control' => 'private, max-age=' . self::CACHE_SECONDS])
            ->setEtag(md5($file . '|' . (string) filemtime($file)));
    }
}
