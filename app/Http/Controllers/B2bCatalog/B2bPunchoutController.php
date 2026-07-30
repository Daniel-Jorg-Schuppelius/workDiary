<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bPunchoutController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\B2bCatalog;

use App\Enums\Security\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\B2b\{B2bCatalogAccess, B2bCatalogItem};
use App\Models\Organization;
use App\Services\B2bCatalog\OciCartFormatter;
use App\Services\Security\SecurityEventLogger;
use App\Services\SqidEncoder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Crypt, DB};
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentlicher OCI-4.0-Punchout-Katalog (Feature 099, MVP-457) — workDiary in
 * der LIEFERANTEN-Rolle, Rollenumkehr zu {@see \App\Http\Controllers\OciCartController}.
 *
 * Sessionloser Flow: Das Einkaufssystem POSTet USERNAME/PASSWORD/HOOK_URL an
 * den Einstieg; danach trägt ein verschlüsseltes, zeitbegrenztes Browse-Token
 * (Crypt, manipulationssicher) Zugang + HOOK_URL durch Katalog und
 * Warenkorb-Rückgabe — keine App-Session, kein CSRF, eigener Security-Stack
 * (Rate-Limit + CSP, Muster Karriereportal). Zugangsdaten nur als Hash
 * ({@see B2bCatalogAccess}); Fehlversuche laufen ins Security-Log (fail2ban).
 */
class B2bPunchoutController extends Controller {
    /** Lebensdauer einer Browse-Sitzung (Muster Feature-050-Absprung: 2 h). */
    private const TOKEN_TTL_MINUTES = 120;

    /** OCI-Einstieg: nur POST — Zugangsdaten gehören nie in URLs/Access-Logs. */
    public function punchout(Request $request): Response|RedirectResponse {
        $organization = $this->organization($request);

        $hookUrl = trim((string) $request->input('HOOK_URL', ''));
        if (! preg_match('#^https://#i', $hookUrl) || filter_var($hookUrl, FILTER_VALIDATE_URL) === false) {
            return $this->errorView(__('b2b_catalog.public.error_hook_url'), 422);
        }

        $username = trim((string) $request->input('USERNAME', ''));
        $password = (string) $request->input('PASSWORD', '');

        $access = B2bCatalogAccess::query()
            ->active()
            ->where('username', $username)
            ->first();

        if (! $access instanceof B2bCatalogAccess
            || ! hash_equals($access->secret_hash, B2bCatalogAccess::hashSecret($password))) {
            app(SecurityEventLogger::class)->log(SecurityEventType::ApiTokenInvalid, ['surface' => 'b2b-punchout']);

            return $this->errorView(__('b2b_catalog.public.error_credentials'), 403);
        }

        // Bewusst ohne Model-Events/Audit — reiner Nutzungszeitstempel.
        DB::table('b2b_catalog_accesses')->where('id', $access->id)->update(['last_used_at' => now()]);

        $token = Crypt::encryptString(json_encode([
            'a' => $access->id,
            'h' => $hookUrl,
            'r' => trim((string) $request->input('RETURNTARGET', '_top')) ?: '_top',
            'e' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return redirect()->route('b2b-punchout.browse', ['org' => $organization->slug, 't' => $token]);
    }

    /** Katalog-Browse: nur freigegebene Artikel des Zugangs, mit Suche. */
    public function browse(Request $request): Response {
        $organization = $this->organization($request);
        $session = $this->session($request);
        if (! is_array($session)) {
            return $this->errorView(__('b2b_catalog.public.error_session'), 410);
        }

        /** @var B2bCatalogAccess $access */
        $access = $session['access'];
        $q = trim((string) $request->query('q', ''));

        $items = B2bCatalogItem::query()
            ->where('access_id', $access->id)
            ->with('article')
            ->whereHas('article', function ($query) use ($q): void {
                $query->where('status', \App\Enums\Article\ArticleStatus::Active->value);
                if ($q !== '') {
                    $query->where(function ($inner) use ($q): void {
                        $inner->whereLikeEscaped('name', $q)
                            ->orWhereLikeEscaped('number', $q);
                    });
                }
            })
            ->orderBy(
                \App\Models\Article::query()->select('number')
                    ->whereColumn('articles.id', 'b2b_catalog_items.article_id')
            )
            ->paginate(50)
            ->appends(array_filter(['t' => (string) $request->query('t'), 'q' => $q]));

        return response()->view('b2b.catalog.browse', [
            'organization' => $organization,
            'access' => $access,
            'items' => $items,
            'q' => $q,
            'token' => (string) $request->query('t'),
        ]);
    }

    /** Warenkorb-Rückgabe: selbstabsendendes NEW_ITEM-*-Formular an die HOOK_URL. */
    public function transfer(Request $request, OciCartFormatter $formatter): Response {
        $organization = $this->organization($request);
        $session = $this->session($request);
        if (! is_array($session)) {
            return $this->errorView(__('b2b_catalog.public.error_session'), 410);
        }

        /** @var B2bCatalogAccess $access */
        $access = $session['access'];

        $encoder = app(SqidEncoder::class);
        $quantities = [];
        foreach ((array) $request->input('qty', []) as $sqid => $value) {
            $qty = (float) str_replace(',', '.', (string) $value);
            $id = $encoder->decode(B2bCatalogItem::class, (string) $sqid);
            if ($qty > 0 && $id !== null) {
                $quantities[$id] = $qty;
            }
        }

        if ($quantities === []) {
            return $this->errorView(__('b2b_catalog.public.error_empty_cart'), 422);
        }

        $lines = B2bCatalogItem::query()
            ->where('access_id', $access->id)
            ->whereIn('id', array_keys($quantities))
            ->with('article')
            ->get()
            ->map(fn(B2bCatalogItem $item): array => [
                'item' => $item,
                'quantity' => $quantities[$item->id],
            ])
            ->values()
            ->all();

        if ($lines === []) {
            return $this->errorView(__('b2b_catalog.public.error_empty_cart'), 422);
        }

        $hookUrl = (string) $session['hook_url'];
        $request->attributes->set('b2b.form_action', $this->origin($hookUrl));

        return response()->view('b2b.catalog.transfer', [
            'organization' => $organization,
            'hookUrl' => $hookUrl,
            'returnTarget' => (string) $session['return_target'],
            'fields' => $formatter->fields($lines),
        ]);
    }

    private function organization(Request $request): Organization {
        /** @var Organization $organization */
        $organization = $request->attributes->get('b2b_organization');

        return $organization;
    }

    /**
     * Entschlüsselt das Browse-Token und lädt den (org-gescopten) Zugang.
     *
     * @return array{access: B2bCatalogAccess, hook_url: string, return_target: string}|null
     */
    private function session(Request $request): ?array {
        try {
            $payload = json_decode(Crypt::decryptString((string) $request->input('t', '')), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload) || now()->getTimestamp() > (int) ($payload['e'] ?? 0)) {
            return null;
        }

        $access = B2bCatalogAccess::query()->active()->find((int) ($payload['a'] ?? 0));
        if (! $access instanceof B2bCatalogAccess) {
            return null;
        }

        return [
            'access' => $access,
            'hook_url' => (string) ($payload['h'] ?? ''),
            'return_target' => (string) ($payload['r'] ?? '_top'),
        ];
    }

    private function origin(string $url): string {
        $parts = parse_url($url);
        $origin = strtolower((string) ($parts['scheme'] ?? 'https')) . '://' . (string) ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function errorView(string $message, int $status): Response {
        return response()->view('b2b.catalog.error', ['message' => $message], $status);
    }
}
