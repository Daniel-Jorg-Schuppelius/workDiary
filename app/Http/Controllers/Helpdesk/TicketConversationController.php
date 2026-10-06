<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketConversationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\Permission;
use App\Http\Controllers\Attachments\AttachmentController;
use App\Http\Controllers\Concerns\ValidatesUploadedFiles;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\ServiceTicket\TicketConversationService;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Konversation (Feature 065, MVP-152): GETRENNTE Aktionen für Antwort
 * (kundensichtbar, Recht serviceTicket.update) und interne Notiz (Recht
 * helpdesk.ticket.internal_note) — öffentlich vs. intern ist eine
 * Typfrage, technisch unverwechselbar. Optionale Datei-Anhänge folgen der
 * Policy des {@see AttachmentController} (Whitelist/MIME/Größe).
 */
class TicketConversationController extends Controller {
    use ValidatesUploadedFiles;

    public function __construct(private readonly TicketConversationService $conversation) {}

    public function reply(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize(Permission::ServiceTicketUpdate->value);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'to' => ['nullable', 'array', 'max:10'],
            'to.*' => ['email'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);
        $files = $this->validatedUploads($request);

        /** @var User $author */
        $author = Auth::user();

        try {
            $this->conversation->reply($ticket, $author, $data['body'], $data['to'] ?? [], $data['subject'] ?? null, files: $files);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('service-tickets.show', $ticket)
            ->with('success', __('Antwort gespeichert.'));
    }

    public function note(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize(Permission::HelpdeskTicketInternalNote->value);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:10000'],
        ]);
        $files = $this->validatedUploads($request);

        /** @var User $author */
        $author = Auth::user();

        try {
            $this->conversation->note($ticket, $author, $data['body'], $files);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('service-tickets.show', $ticket)
            ->with('success', __('Notiz gespeichert.'));
    }
}
