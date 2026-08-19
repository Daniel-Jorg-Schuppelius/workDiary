<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyTicketTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey;

use App\Mail\SurveyInvitationMail;
use App\Models\ServiceTicket;
use App\Models\Survey\Survey;
use Illuminate\Support\Facades\{Log, Mail};
use RuntimeException;

/**
 * Anlass-Trigger „Ticketabschluss" (Feature 090, MVP-661).
 *
 * Fehlertoleranz ist hier Pflicht: Ein gescheiterter Einladungsversuch
 * (Opt-out, Ermüdungsschutz, fehlende Adresse, Mail-Fehler) darf den
 * Ticket-Statuswechsel NIE verhindern — er wird geloggt und übersprungen.
 */
class SurveyTicketTrigger {
    public function __construct(private readonly SurveyService $service) {}

    public function onTicketResolved(ServiceTicket $ticket): void {
        $customer = $ticket->customer;
        $email = trim((string) ($customer?->email));
        if ($customer === null || $email === '') {
            return;
        }

        $surveys = Survey::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $ticket->organization_id)
            ->where('active', true)
            ->where('trigger_on_ticket_close', true)
            ->get();

        foreach ($surveys as $survey) {
            try {
                $issued = $this->service->invite($survey, $email, $customer, 'ticket');
                Mail::to($email)->send(new SurveyInvitationMail($survey, $issued['token']));
            } catch (RuntimeException) {
                // Opt-out/Ermüdungsschutz: bewusst still - der Deckel ist
                // Pflichtbestandteil, kein Fehler.
                continue;
            } catch (\Throwable $e) {
                Log::warning('Umfrage-Einladung nach Ticketabschluss fehlgeschlagen.', [
                    'survey_id' => $survey->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
