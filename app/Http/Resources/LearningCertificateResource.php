<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCertificateResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Zertifikat in der API (Feature 149, MVP-791): Nummer, Gültigkeit und der
 * öffentliche Prüflink — kein Schlüsselmaterial, kein Widerrufsgrund im
 * Klartext für Dritte.
 *
 * @mixin \App\Models\Learning\LearningCertificate
 */
class LearningCertificateResource extends JsonResource {
    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'number' => $this->number,
            'holder_name' => $this->holder_name,
            'course' => $this->whenLoaded('course', fn (): ?array => $this->course !== null ? [
                'id' => $this->course->sqid,
                'code' => $this->course->code,
                'title' => $this->course->title,
            ] : null),
            'issued_on' => $this->issued_on->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'score_percent' => $this->score_percent,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'verify_url' => route('learning.certificates.verify', $this->verification_code),
        ];
    }
}
