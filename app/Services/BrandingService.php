<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BrandingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Http\Controllers\AttachmentController;
use App\Models\Attachment;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

/**
 * Zentrale Quelle für Branding-Informationen einer Organisation
 * (Firmenlogo, Anzeigename, Farben, Kontakt-/Rechtsfelder, PDF-Konfig).
 *
 * Layouts, Login-Seiten und PDF-Templates greifen über diesen Service zu,
 * damit `config('app.name')` und vergleichbare Konstanten nicht mehr
 * hart kodiert in den Views stehen.
 */
class BrandingService
{
    private ?Organization $cached = null;

    private bool $resolved = false;

    public function currentOrganization(): ?Organization
    {
        if ($this->resolved) {
            return $this->cached;
        }
        $this->resolved = true;

        $user = Auth::user();
        if ($user === null) {
            return $this->cached = null;
        }

        $orgId = $user->organization_id ?? null;
        if ($orgId === null) {
            return $this->cached = null;
        }

        return $this->cached = Organization::query()->find($orgId);
    }

    /**
     * Liefert die effektiven Branding-Settings inkl. Defaults aus
     * `config/branding.php`. Ohne Organisation werden ausschließlich die
     * Defaults zurückgegeben.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $org = $this->currentOrganization();
        if ($org === null) {
            /** @var array<string, mixed> $defaults */
            $defaults = (array) config('branding', []);

            return $defaults;
        }

        return $org->brandingSettings();
    }

    /**
     * Anzeigename für UI-Header und PDFs. Fällt auf config('app.name')
     * zurück, wenn die Organisation keinen abweichenden Namen gesetzt hat.
     */
    public function appName(): string
    {
        $stored = $this->settings()['app_name'] ?? null;
        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        $org = $this->currentOrganization();
        if ($org !== null && trim((string) $org->name) !== '') {
            return (string) $org->name;
        }

        return (string) config('app.name', 'WorkDiary');
    }

    public function slogan(): ?string
    {
        $val = $this->settings()['slogan'] ?? null;

        return is_string($val) && trim($val) !== '' ? $val : null;
    }

    /**
     * Signed-URL für das passende Logo. Liefert null, wenn kein Logo
     * gesetzt ist – das Layout zeigt dann den App-Namen als Text.
     */
    public function logoUrl(string $variant = 'light'): ?string
    {
        $att = $this->logoAttachment($variant);

        return $att !== null ? AttachmentController::downloadUrl($att) : null;
    }

    public function logoAttachment(string $variant = 'light'): ?Attachment
    {
        $org = $this->currentOrganization();
        if ($org === null) {
            return null;
        }

        return $variant === 'dark'
            ? ($org->logoDark() ?? $org->logo())
            : ($org->logo() ?? $org->logoDark());
    }

    /**
     * Konfiguration für einen PDF-Typ (z. B. 'timesheet', 'invoice').
     * Liefert immer ein vollständiges Array (Defaults gemerged).
     *
     * @return array{logo:?string, show_contact:bool, show_footer:bool, logo_url:?string, logo_data_uri:?string}
     */
    public function pdfConfig(string $type): array
    {
        $settings = $this->settings();
        /** @var array<string, mixed> $pdfDefaults */
        $pdfDefaults = (array) config('branding.pdf.'.$type, []);
        /** @var array<string, mixed> $pdfStored */
        $pdfStored = (array) data_get($settings, 'pdf.'.$type, []);
        $merged = array_replace([
            'logo' => 'light',
            'show_contact' => true,
            'show_footer' => true,
        ], $pdfDefaults, $pdfStored);

        $logoVariant = $merged['logo'] ?? null;
        $logoUrl = null;
        $logoDataUri = null;
        if (is_string($logoVariant) && $logoVariant !== '') {
            $att = $this->logoAttachment($logoVariant);
            if ($att !== null) {
                $logoUrl = AttachmentController::downloadUrl($att);
                $logoDataUri = $this->attachmentToDataUri($att);
            }
        }

        return [
            'logo' => is_string($logoVariant) ? $logoVariant : null,
            'show_contact' => (bool) ($merged['show_contact'] ?? true),
            'show_footer' => (bool) ($merged['show_footer'] ?? true),
            'logo_url' => $logoUrl,
            'logo_data_uri' => $logoDataUri,
        ];
    }

    /**
     * Kontakt-Stammdaten als flaches Array (für Header/Footer).
     *
     * @return array<string, ?string>
     */
    public function contact(): array
    {
        /** @var array<string, ?string> $contact */
        $contact = (array) ($this->settings()['contact'] ?? []);

        return $contact;
    }

    /**
     * Rechtliche / steuerliche Angaben für PDF-Footer.
     *
     * @return array<string, ?string>
     */
    public function legal(): array
    {
        /** @var array<string, ?string> $legal */
        $legal = (array) ($this->settings()['legal'] ?? []);

        return $legal;
    }

    /**
     * HEX-Wert der Primärfarbe, oder null wenn kein Override gesetzt ist.
     */
    public function primaryColor(): ?string
    {
        $val = data_get($this->settings(), 'colors.primary');

        return $this->normalizeHex(is_string($val) ? $val : null);
    }

    public function accentColor(): ?string
    {
        $val = data_get($this->settings(), 'colors.accent');

        return $this->normalizeHex(is_string($val) ? $val : null);
    }

    private function normalizeHex(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return str_starts_with($value, '#') ? strtolower($value) : '#'.strtolower($value);
    }

    /**
     * Wandelt einen Attachment-Eintrag (Bilddatei auf einer lokalen Disk)
     * in eine data:-URI um. Wird in PDFs verwendet, weil dompdf signierte
     * URLs nicht zuverlässig auflöst.
     */
    private function attachmentToDataUri(Attachment $att): ?string
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk($att->disk);
            if (! $disk->exists($att->path)) {
                return null;
            }
            $contents = $disk->get($att->path);
            if ($contents === null || $contents === '') {
                return null;
            }
            $mime = $att->mime ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
