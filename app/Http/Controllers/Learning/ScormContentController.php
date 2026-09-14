<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningEnrollment, LearningScormPackage};
use App\Services\Learning\{ScormContentToken, ScormPackageFiles};
use App\Support\Learning\ScormContentHost;
use ELearningToolkit\Scorm\LaunchPath;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Auslieferung am eigenen Inhalts-Host (Sicherheitsaudit `files-1`).
 *
 * Ohne Sitzung und ohne gebundene Organisation: Welches Paket gemeint ist, sagt
 * allein der signierte Token. Paket und Einschreibung werden deshalb über die IDs
 * daraus geladen und gegeneinander geprüft. Ein ungültiger Token antwortet mit 404,
 * nicht mit 403 — nichts soll verraten, ob es das Paket gibt.
 */
final class ScormContentController extends Controller {
    public function __construct(private readonly ScormContentToken $tokens) {}

    /** Hülle mit der SCORM-Laufzeit; das Kurspaket bettet sie im eigenen Ursprung ein. */
    public function wrapper(string $token): Response {
        $package = $this->package($token);
        $appOrigin = ScormContentHost::appOrigin();
        abort_if($appOrigin === null, 404);

        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $launchUrl = '/scorm/' . $token . '/inhalt/' . LaunchPath::encode((string) $package->launch_href);

        $response = response()->view('learning.scorm-content.wrapper', [
            'nonce' => $nonce,
            'appOrigin' => $appOrigin,
            'launchUrl' => $launchUrl,
            'is2004' => $package->isScorm2004(),
        ]);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'nonce-{$nonce}'",
            "style-src 'unsafe-inline'",
            "frame-src 'self'",
            "frame-ancestors {$appOrigin}",
            "base-uri 'none'",
            "form-action 'none'",
        ]));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public function asset(string $token, string $path = ''): BinaryFileResponse {
        $package = $this->package($token);
        $file = ScormPackageFiles::absolutePath($package, $path);
        abort_if($file === null, 404);

        $response = response()->file($file);
        $response->headers->set('Content-Security-Policy', ScormPackageFiles::contentSecurityPolicy(ScormContentHost::appOrigin()));

        return $response;
    }

    private function package(string $token): LearningScormPackage {
        $claims = $this->tokens->verify($token);
        abort_if($claims === null, 404);

        // TENANT-BYPASS: sitzungsloser Inhalts-Host, gebunden über den signierten Token.
        $package = LearningScormPackage::query()->withoutGlobalScopes()
            ->whereKey($claims['package'])
            ->where('learning_unit_id', $claims['unit'])
            ->first();
        abort_if($package === null, 404);

        $enrolled = LearningEnrollment::query()->withoutGlobalScopes()
            ->whereKey($claims['enrollment'])
            ->where('organization_id', $package->organization_id)
            ->exists();
        abort_unless($enrolled, 404);

        return $package;
    }
}
