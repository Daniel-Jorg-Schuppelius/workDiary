<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderSubmissionPreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Models\Applications\ApplicationOpportunity;
use App\Models\BoqItem;
use App\Support\{CarbonFmt, Tz};
use Carbon\CarbonImmutable;

/**
 * Preflight vor der Angebotsabgabe (Feature 108, MVP-628).
 *
 * Ein Angebot lässt sich nach der Abgabe nicht mehr reparieren: Nachbessern
 * ist im Vergaberecht die Ausnahme, und ein unvollständiges Angebot wird
 * ausgeschlossen. Deshalb prüft der Assistent **vorher** und in zwei Stufen:
 *
 * - **Sperren** (`blocker`) verhindern die Abgabe. Sie stehen für Zustände,
 *   in denen die Einreichung fachlich falsch wäre — keine Go-Entscheidung,
 *   bereits entschiedene Akte, offene Pflicht-Unterlagen. Sie decken sich mit
 *   den Prüfungen in {@see TenderService::submit()}; der Assistent zeigt sie
 *   nur früher.
 * - **Hinweise** (`warning`) halten niemanden auf. Eine abgelaufene
 *   Abgabefrist etwa **sperrt nicht**: Die Einreichung wird hier
 *   *dokumentiert*, oft am Tag danach, und einen bereits abgegebenen Vorgang
 *   nicht mehr eintragen zu können, machte die Akte falsch — nicht
 *   vollständiger.
 *
 * Die Prüfung ist lesend und ohne Nebenwirkung; sie darf jederzeit laufen.
 */
final class TenderSubmissionPreflight {
    public const SEVERITY_BLOCKER = 'blocker';
    public const SEVERITY_WARNING = 'warning';

    /**
     * @return list<array{severity: string, code: string, message: string}>
     */
    public function check(ApplicationOpportunity $opportunity): array {
        $findings = [];

        if ($opportunity->go_decision !== 'go') {
            $findings[] = $this->blocker('go_missing', __('Vor der Einreichung braucht die Akte eine Go-Entscheidung.'));
        }
        if (! $opportunity->isOpen()) {
            $findings[] = $this->blocker('already_decided', __('Die Akte ist bereits entschieden.'));
        }

        $openRequired = $opportunity->requirements()
            ->where('required', true)
            ->whereNotIn('status', ['done', 'not_applicable'])
            ->count();
        if ($openRequired > 0) {
            $findings[] = $this->blocker('requirements_open', __(':count Pflicht-Unterlagen sind noch offen.', ['count' => $openRequired]));
        }

        $findings = array_merge($findings, $this->deadlineFindings($opportunity));
        $findings = array_merge($findings, $this->procedureFindings($opportunity));
        $findings = array_merge($findings, $this->boqFindings($opportunity));

        return $findings;
    }

    /** @param list<array{severity: string, code: string, message: string}> $findings */
    public function isBlocked(array $findings): bool {
        foreach ($findings as $finding) {
            if ($finding['severity'] === self::SEVERITY_BLOCKER) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fristen sind lokal zu lesen — eine Abgabefrist um 12:00 Uhr ist eine
     * Ortszeit, keine UTC-Angabe.
     *
     * @return list<array{severity: string, code: string, message: string}>
     */
    private function deadlineFindings(ApplicationOpportunity $opportunity): array {
        $findings = [];
        $today = CarbonImmutable::now(Tz::current())->startOfDay();

        if ($opportunity->submission_deadline === null) {
            $findings[] = $this->warning('deadline_missing', __('Keine Abgabefrist erfasst — der Fristenwächter kann nicht warnen.'));
        } elseif ($opportunity->submission_deadline->lt($today)) {
            // Kein Blocker: Die Einreichung wird hier dokumentiert, oft am Tag
            // danach.
            $findings[] = $this->warning('deadline_passed', __('Die Abgabefrist war am :date.', ['date' => CarbonFmt::fdate($opportunity->submission_deadline)]));
        }

        if ($opportunity->binding_until === null) {
            $findings[] = $this->warning('binding_missing', __('Keine Bindefrist erfasst — bis wann das Angebot gilt, bleibt offen.'));
        }

        return $findings;
    }

    /** @return list<array{severity: string, code: string, message: string}> */
    private function procedureFindings(ApplicationOpportunity $opportunity): array {
        $findings = [];

        if ($opportunity->procedure_type === null) {
            // Ohne Verfahrensart ist auch die Schwellenwertlage ungeprüft, und
            // damit das anwendbare Regelwerk.
            $findings[] = $this->warning('procedure_missing', __('Keine Verfahrensart erfasst — das anwendbare Regelwerk ist damit nicht dokumentiert.'));
        }
        if ($opportunity->estimated_value === null || (float) $opportunity->estimated_value <= 0.0) {
            $findings[] = $this->warning('value_missing', __('Kein Angebotswert erfasst — Trefferquote und Auswertung rechnen ohne diesen Vorgang.'));
        }

        return $findings;
    }

    /**
     * Prüfungen am Leistungsverzeichnis, sofern eines an der Akte hängt.
     *
     * Eine Position ohne Preis ist im Angebot keine Lücke, sondern ein
     * Ausschlussgrund — es sei denn, sie ist ausdrücklich als **nicht
     * angeboten** gekennzeichnet. Genau diese Unterscheidung prüft der
     * Preflight; Hinweistexte und Zuschlagspositionen tragen naturgemäß
     * keinen Einheitspreis und bleiben außen vor.
     *
     * @return list<array{severity: string, code: string, message: string}>
     */
    private function boqFindings(ApplicationOpportunity $opportunity): array {
        $boqId = $opportunity->bill_of_quantity_id;
        if ($boqId === null) {
            return [];
        }

        $unpriced = BoqItem::query()
            ->where('bill_of_quantity_id', $boqId)
            ->whereNotIn('type', ['note', 'markup'])
            ->where('not_offered', false)
            ->whereNull('unit_price')
            ->count();

        if ($unpriced === 0) {
            return [];
        }

        return [$this->warning('boq_unpriced', __(':count Positionen im Leistungsverzeichnis haben keinen Einheitspreis und sind nicht als „nicht angeboten" gekennzeichnet.', ['count' => $unpriced]))];
    }

    /** @return array{severity: string, code: string, message: string} */
    private function blocker(string $code, string $message): array {
        return ['severity' => self::SEVERITY_BLOCKER, 'code' => $code, 'message' => $message];
    }

    /** @return array{severity: string, code: string, message: string} */
    private function warning(string $code, string $message): array {
        return ['severity' => self::SEVERITY_WARNING, 'code' => $code, 'message' => $message];
    }
}
