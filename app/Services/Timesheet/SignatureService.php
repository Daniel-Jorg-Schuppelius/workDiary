<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timesheet;

use App\Enums\Timesheet\TimesheetStatus;
use App\Mail\TimesheetSignedMail;
use App\Models\{Attachment, Timesheet, User};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Mail, Storage};
use Illuminate\Support\Str;
use RuntimeException;

class SignatureService {
    public const MAX_BYTES = 1_000_000; // 1 MB für Canvas-PNG

    /**
     * Verarbeitet ein Base64-codiertes PNG aus dem Canvas und persistiert es als Attachment.
     *
     * @param  array{customer_name?: string, customer_role?: string|null, customer_email?: string|null}  $customer
     */
    public function sign(Timesheet $timesheet, string $base64Png, array $customer, ?Request $request = null, ?User $signer = null): Timesheet {
        if ($timesheet->isLocked()) {
            throw new RuntimeException('Timesheet is locked.');
        }

        $binary = $this->decodePng($base64Png);
        if (strlen($binary) > self::MAX_BYTES) {
            throw new RuntimeException('Signature too large.');
        }

        $folder = 'timesheets/signatures/' . now()->format('Y/m');
        $filename = Str::uuid()->toString() . '.png';
        $path = $folder . '/' . $filename;
        Storage::disk('local')->put($path, $binary);

        /** @var Attachment $attachment */
        $attachment = $timesheet->attachments()->create([
            'user_id' => $signer !== null ? $signer->id : Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'signature.png',
            'mime' => 'image/png',
            'size' => strlen($binary),
        ]);

        $hash = CryptoHelper::hash($binary);
        $ip = $request?->ip();

        $timesheet->forceFill([
            'customer_name' => $customer['customer_name'] ?? $timesheet->customer_name,
            'customer_role' => $customer['customer_role'] ?? $timesheet->customer_role,
            'customer_email' => $customer['customer_email'] ?? $timesheet->customer_email,
            'signed_at' => now(),
            'signed_ip' => $ip,
            'signature_attachment_id' => $attachment->id,
            'signature_hash' => $hash,
            'status' => TimesheetStatus::Signed->value,
            'magic_token' => null,
            'magic_expires_at' => null,
        ])->save();

        $timesheet->refresh();

        // MVP-650: signierte Stundenzettel rendern dauerhaft mit dem
        // Designprofil des Signaturzeitpunkts (idempotent).
        if ($timesheet->organization !== null) {
            app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->snapshot(
                $timesheet,
                \App\Enums\DocumentDesign\RenderDocumentKind::Timesheet,
                $timesheet->organization,
                user: $signer,
            );
        }

        if ($timesheet->customer_email) {
            Mail::to($timesheet->customer_email)->send(new TimesheetSignedMail($timesheet));
        }

        return $timesheet;
    }

    public function lock(Timesheet $timesheet, User $admin): Timesheet {
        $timesheet->forceFill([
            'status' => TimesheetStatus::Locked->value,
            'locked_at' => now(),
            'locked_by' => $admin->id,
        ])->save();

        return $timesheet;
    }

    public function unlock(Timesheet $timesheet): Timesheet {
        $timesheet->forceFill([
            'status' => TimesheetStatus::Signed->value,
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        return $timesheet;
    }

    public function generateMagicToken(Timesheet $timesheet, int $minutes = 1440): Timesheet {
        $timesheet->forceFill([
            'magic_token' => Str::random(64),
            'magic_expires_at' => now()->addMinutes($minutes),
        ])->save();

        return $timesheet;
    }

    /** Magic-Link explizit ungültig machen (Feature 012 MVP; Vollaudit 2026-07, M6). */
    public function revokeMagicToken(Timesheet $timesheet): Timesheet {
        $timesheet->forceFill([
            'magic_token' => null,
            'magic_expires_at' => null,
        ])->save();

        return $timesheet;
    }

    private function decodePng(string $payload): string {
        $payload = trim($payload);
        if (str_starts_with($payload, 'data:image/png;base64,')) {
            $payload = substr($payload, strlen('data:image/png;base64,'));
        }
        $binary = base64_decode($payload, true);
        if ($binary === false || strlen($binary) < 8) {
            throw new RuntimeException('Invalid PNG payload.');
        }
        // PNG-Magic prüfen
        if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException('Payload is not a PNG.');
        }

        return $binary;
    }
}
