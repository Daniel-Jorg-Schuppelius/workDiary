<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeStages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Customer\IntakeStatus;
use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\CustomerIntake;
use App\Services\Customer\Dto\IntakeStage;

/**
 * Kundensicht eines Eingangs (MVP-1075): vor der Übernahme aus Eingang und
 * Angebot abgeleitet, danach aus dem tatsächlichen Stand der Fachakte über
 * den Zieladapter — der Eingang führt keinen eigenen Auftragsstatus weiter.
 */
class CustomerIntakeStages {
    public function __construct(
        private readonly CustomerIntakeHandoverService $handover,
        private readonly CustomerIntakeQuoteService $quotes,
    ) {}

    public function for(CustomerIntake $intake): IntakeStage {
        return match ($intake->status) {
            IntakeStatus::Withdrawn => $this->stage('withdrawn', 'neutral'),
            IntakeStatus::Rejected => $this->stage('rejected', 'error'),
            IntakeStatus::HandedOver => $this->target($intake),
            IntakeStatus::AwaitingCustomer => $this->stage('awaiting_customer', 'warning', 'answer_question'),
            default => $this->quote($intake) ?? ($intake->status === IntakeStatus::Submitted
                ? $this->stage('submitted', 'info')
                : $this->stage('in_progress', 'primary')),
        };
    }

    private function quote(CustomerIntake $intake): ?IntakeStage {
        $quote = $intake->quote;
        if ($quote === null || ! $this->quotes->visibleToCustomer($quote)) {
            return null;
        }
        if ($this->quotes->decidable($quote)) {
            return $this->stage('quote_pending', 'warning', 'decide_quote');
        }

        return match ($quote->status) {
            QuoteStatus::Accepted => $this->stage('quote_accepted', 'success'),
            QuoteStatus::PartiallyAccepted => $this->stage('quote_partially_accepted', 'info'),
            QuoteStatus::Rejected => $this->stage('quote_rejected', 'neutral'),
            QuoteStatus::Expired => $this->stage('quote_expired', 'neutral'),
            // Versandt, aber überholt: die neue Fassung folgt.
            default => $this->stage('quote_revised', 'info'),
        };
    }

    private function target(CustomerIntake $intake): IntakeStage {
        $adapter = $this->handover->targetFor($intake->kind);
        $target = $intake->target()->withoutGlobalScopes()->first();

        return $adapter !== null && $target !== null
            ? $adapter->customerStage($target)
            : $this->stage('handed_over', 'success');
    }

    private function stage(string $key, string $tone, ?string $next = null): IntakeStage {
        return new IntakeStage(
            (string) __('customer_intake.stage.' . $key),
            $tone,
            $next !== null ? (string) __('customer_intake.next_step.' . $next) : null,
            $next !== null,
        );
    }
}
