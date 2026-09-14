<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiRegistrationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningLtiPlatform, LearningLtiTool};
use App\Services\Learning\LearningLtiPlatformService;
use CommonToolkit\Helper\Data\WebLinkHelper;
use ELearningToolkit\Lti\{Keys, LtiException};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * LTI-1.3-Registrierungen der Organisation (Feature 149): Tools, die WorkDiary
 * startet, und Plattformen, die WorkDiary starten.
 */
final class LearningLtiRegistrationController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.lti.registrations', [
            'tools' => LearningLtiTool::query()->orderBy('name')->get(),
            'platforms' => LearningLtiPlatform::query()->orderBy('name')->get(),
            'issuer' => LearningLtiPlatformService::issuer(),
        ]);
    }

    public function createTool(): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.lti._tool_dialog', ['tool' => null]);
    }

    public function editTool(LearningLtiTool $tool): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.lti._tool_dialog', ['tool' => $tool]);
    }

    public function storeTool(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        // Als Plattform vergibt WorkDiary Client- und Deployment-ID selbst.
        LearningLtiTool::query()->create($this->toolAttributes($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'client_id' => Str::uuid()->toString(),
            'deployment_id' => Str::lower(Str::random(16)),
        ]);

        return $this->saved();
    }

    public function updateTool(Request $request, LearningLtiTool $tool): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $tool->update($this->toolAttributes($request));

        return $this->saved();
    }

    public function destroyTool(LearningLtiTool $tool): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $tool->delete();

        return $this->deleted();
    }

    public function createPlatform(): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.lti._platform_dialog', ['platform' => null]);
    }

    public function editPlatform(LearningLtiPlatform $platform): View {
        Gate::authorize(Permission::LearningManage->value);

        return view('learning.lti._platform_dialog', ['platform' => $platform]);
    }

    public function storePlatform(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $attributes = $this->platformAttributes($request) + ['organization_id' => $this->currentOrganization()->id];

        $this->guardDuplicatePlatform(static fn () => LearningLtiPlatform::query()->create($attributes));

        return $this->saved();
    }

    public function updatePlatform(Request $request, LearningLtiPlatform $platform): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $attributes = $this->platformAttributes($request);

        $this->guardDuplicatePlatform(static fn () => $platform->update($attributes));

        return $this->saved();
    }

    public function destroyPlatform(LearningLtiPlatform $platform): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $platform->delete();

        return $this->deleted();
    }

    /** @return array<string, mixed> */
    private function toolAttributes(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'login_url' => ['required', 'url:https,http', 'max:2000'],
            'launch_url' => ['required', 'url:https,http', 'max:2000'],
            'redirect_uris' => ['required', 'string', 'max:10000'],
            'deep_linking_url' => ['nullable', 'url:https,http', 'max:2000'],
            'jwks_url' => ['nullable', 'url:https,http', 'max:2000'],
            'public_jwks' => ['nullable', 'string', 'max:20000'],
        ]);

        $redirectUris = self::lines((string) $data['redirect_uris']);

        foreach ($redirectUris === [] ? [''] : $redirectUris as $uri) {
            // Redirect-URIs werden exakt verglichen — sie müssen vollständige Adressen sein.
            if (WebLinkHelper::origin($uri) === null || mb_strlen($uri) > 2000) {
                throw ValidationException::withMessages(['redirect_uris' => (string) __('learning.lti_registration.errors.invalid_url')]);
            }
        }

        $jwksUrl = isset($data['jwks_url']) ? (string) $data['jwks_url'] : null;
        $publicJwks = isset($data['public_jwks']) && trim((string) $data['public_jwks']) !== '' ? trim((string) $data['public_jwks']) : null;

        if ($jwksUrl === null && $publicJwks === null) {
            throw ValidationException::withMessages(['jwks_url' => (string) __('learning.lti_registration.errors.keys_required')]);
        }

        if ($publicJwks !== null) {
            try {
                $valid = Keys::keySet($publicJwks)->count() > 0;
            } catch (LtiException) {
                $valid = false;
            }

            if (! $valid) {
                throw ValidationException::withMessages(['public_jwks' => (string) __('learning.lti_registration.errors.invalid_jwks')]);
            }
        }

        return [
            'name' => (string) $data['name'],
            'login_url' => (string) $data['login_url'],
            'launch_url' => (string) $data['launch_url'],
            'redirect_uris' => $redirectUris,
            'deep_linking_url' => isset($data['deep_linking_url']) ? (string) $data['deep_linking_url'] : null,
            'jwks_url' => $jwksUrl,
            'public_jwks' => $publicJwks,
            'share_name' => $request->boolean('share_name'),
            'share_email' => $request->boolean('share_email'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @return array<string, mixed> */
    private function platformAttributes(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'issuer' => ['required', 'url:https,http', 'max:500'],
            'client_id' => ['required', 'string', 'max:255'],
            'deployment_ids' => ['required', 'string', 'max:5000'],
            'authorization_endpoint' => ['required', 'url:https,http', 'max:2000'],
            'jwks_url' => ['required', 'url:https,http', 'max:2000'],
        ]);

        $deploymentIds = self::lines((string) $data['deployment_ids']);

        if ($deploymentIds === []) {
            throw ValidationException::withMessages(['deployment_ids' => (string) __('validation.required', ['attribute' => __('learning.lti_registration.deployment_ids')])]);
        }

        return [
            'name' => (string) $data['name'],
            'issuer' => (string) $data['issuer'],
            'client_id' => (string) $data['client_id'],
            'deployment_ids' => $deploymentIds,
            'authorization_endpoint' => (string) $data['authorization_endpoint'],
            'jwks_url' => (string) $data['jwks_url'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Aussteller und Client-ID sind eindeutig — die Datenbank entscheidet, nicht eine Vorabfrage. */
    private function guardDuplicatePlatform(callable $write): void {
        try {
            $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['client_id' => (string) __('learning.lti_registration.errors.duplicate_platform')]);
        }
    }

    private function saved(): RedirectResponse {
        return redirect()->route('learning.lti-registrations.index')->with('success', __('learning.lti_registration.saved'));
    }

    private function deleted(): RedirectResponse {
        return redirect()->route('learning.lti-registrations.index')->with('success', __('learning.lti_registration.deleted'));
    }

    /** @return list<string> eine Angabe je Zeile, ohne Leerzeilen und Doppelte */
    private static function lines(string $text): array {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ! in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
