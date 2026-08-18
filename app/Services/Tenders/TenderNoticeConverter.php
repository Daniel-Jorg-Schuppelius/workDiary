<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeConverter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Tenders;

use App\Enums\Applications\TenderProcedureType;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\Tenders\TenderNoticeMatch;
use App\Models\User;
use RuntimeException;

/**
 * Übernimmt eine gefundene Bekanntmachung in einen Vergabevorgang (MVP-630).
 *
 * Was die Bekanntmachung hergibt, wird vorbelegt — Titel, Vergabestelle, CPV,
 * Region, Frist und die Quelle. Alles Weitere bleibt leer: Ein Wertpotenzial
 * aus dem Auftragswert abzuleiten wäre geraten, und die Verfahrensart nennt
 * OCDS nur grob.
 */
final class TenderNoticeConverter {
    public function convert(TenderNoticeMatch $match, User $actor): ApplicationOpportunity {
        if ($match->application_opportunity_id !== null) {
            throw new RuntimeException('Diese Bekanntmachung wurde bereits übernommen.');
        }

        $notice = $match->notice;
        if ($notice === null) {
            throw new RuntimeException('Zur Bekanntmachung fehlen die Daten.');
        }

        $tender = ApplicationOpportunity::query()->create([
            'organization_id' => $match->organization_id,
            'title' => mb_substr($notice->title, 0, 200),
            'kind' => 'tender',
            'status' => 'captured',
            // Woher der Vorgang stammt, gehört in die Akte: Bei einer Rückfrage
            // ist die Bekanntmachung der Beleg.
            'source' => mb_substr((string) ($notice->url ?? 'Bekanntmachungsservice'), 0, 200),
            'description' => $notice->summary,
            'awarding_body' => $notice->buyer_name,
            'procedure_no' => mb_substr($notice->notice_id, 0, 60),
            'procedure_type' => $this->procedureType($notice->procedure_method),
            'above_threshold' => false,
            'cpv_codes' => $notice->cpv_codes,
            'nuts_code' => $notice->nuts_code,
            'platform' => 'oeffentlichevergabe.de',
            'external_reference' => mb_substr((string) ($notice->ocid ?? $notice->notice_id), 0, 120),
            'notice_url' => $notice->url,
            'submission_deadline' => $notice->submission_deadline?->toDateString(),
            'responsible_user_id' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $match->forceFill([
            'state' => TenderNoticeMatch::STATE_CONVERTED,
            'application_opportunity_id' => $tender->id,
        ])->save();

        $tender->audit('tender.created', [
            'from_notice_id' => $notice->notice_id,
            'tender_notice_match_id' => $match->id,
        ]);

        return $tender;
    }

    /**
     * OCDS nennt die Verfahrensart nur grob (`open`, `selective`, `limited`,
     * `direct`). Eine deutsche Verfahrensart daraus abzuleiten hieße raten —
     * insbesondere, ob ober- oder unterschwellig vergeben wird. Nur das
     * eindeutige offene Verfahren wird gesetzt, alles andere bleibt offen.
     */
    private function procedureType(?string $method): ?TenderProcedureType {
        return match ($method) {
            'open' => TenderProcedureType::OpenProcedure,
            default => null,
        };
    }
}
