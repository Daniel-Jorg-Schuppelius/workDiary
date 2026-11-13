<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VendorChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Wishes;

use App\Mail\AppWishVendorMail;
use App\Models\Platform\User;
use App\Models\Wishes\AppWish;
use App\Settings\SettingsRegistry;
use CommonToolkit\Helper\Data\{CryptoHelper, StringHelper};
use Illuminate\Support\Facades\{DB, Log, Mail};
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Herstellerkanal für selbst betriebene Installationen (Feature 206,
 * MVP-1677). Eigener Opt-in `wishes.vendor_channel` auf Systemebene, Vorgabe
 * aus (Regel aus Feature 036: aus einer Installation verlässt ohne Zustimmung
 * des Betreibers nichts). Der Plattform-Admin entscheidet je Wunsch, sieht
 * vorher genau die Mail, die hinausgeht, und sendet sie selbst; die Antwort
 * des Herstellers trägt er von Hand ein.
 *
 * Hinaus gehen nur: Kennung, Art, Text (bereinigter Text der Weitergabe bzw.
 * die Fassung des Plattform-Admins), Seite, App-Version, Stimmenzahl und der
 * Plattform-Admin als Absender (Antwortadresse). Nie die Organisation, die
 * verfassende Person oder Abstimmende. Die Herstelleradresse steht nur in der
 * Einstellung `wishes.vendor_address`, leer gilt `support.module_contact`.
 */
final class VendorChannel {
    public const string SETTING_ENABLED = 'wishes.vendor_channel';

    public const string SETTING_ADDRESS = 'wishes.vendor_address';

    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly AppWishService $wishes,
    ) {}

    /** Opt-in des Betreibers (Systemebene); ohne ihn verlässt kein Wunsch die Installation. */
    public function enabled(): bool {
        return (bool) $this->settings->effective(self::SETTING_ENABLED)->value;
    }

    /** Herstelleradresse aus der Einstellung, sonst die Vorgabe `support.module_contact`. */
    public function address(): string {
        $configured = StringHelper::trimToNull($this->settings->effective(self::SETTING_ADDRESS)->value);

        return $configured ?? (string) config('support.module_contact');
    }

    /** Text für den Hersteller: die Fassung des Plattform-Admins, sonst der bereinigte Text der Weitergabe. */
    public function text(AppWish $wish): string {
        return (string) ($wish->vendor_body ?? $wish->forwarded_body ?? '');
    }

    /** Fassung für den Hersteller vor dem Senden anpassen (danach eingefroren). */
    public function saveText(AppWish $wish, string $text): AppWish {
        return DB::transaction(function () use ($wish, $text): AppWish {
            $locked = $this->wishes->forwardedQuery()->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($locked->isSentToVendor()) {
                throw ValidationException::withMessages(['vendor_body' => __('wishes.validation.vendor_already_sent')]);
            }
            $locked->update(['vendor_body' => $text]);

            return $locked;
        });
    }

    /** Die Mail, wie sie hinausginge — Grundlage für Vorschau und Versand. */
    public function mail(AppWish $wish, User $sender): AppWishVendorMail {
        $reference = 'W-' . $wish->sqid;
        $lines = [
            (string) __('wishes.vendor.mail.intro'),
            '',
            (string) __('wishes.vendor.mail.reference') . ': ' . $reference,
            (string) __('wishes.vendor.mail.kind') . ': ' . $wish->kind->label(),
            (string) __('wishes.vendor.mail.page') . ': ' . ($wish->route_name ?? '—'),
            (string) __('wishes.vendor.mail.app_version') . ': ' . ($wish->app_version ?? (string) config('app.version')),
            (string) __('wishes.vendor.mail.votes') . ': ' . $this->wishes->bundleVotes($wish),
            '',
            (string) __('wishes.vendor.mail.text') . ':',
            $this->text($wish),
            '',
            (string) __('wishes.vendor.mail.sender', ['name' => (string) $sender->name, 'email' => (string) $sender->email]),
        ];

        return new AppWishVendorMail(
            (string) __('wishes.vendor.mail.subject', ['reference' => $reference, 'kind' => $wish->kind->label(), 'summary' => StringHelper::truncate(StringHelper::trimToNull($this->text($wish)) ?? '', 60)]),
            implode("\n", $lines),
            (string) $sender->email,
            (string) $sender->name,
        );
    }

    /**
     * Vorschau genau dessen, was hinausgeht: Empfänger, Antwortadresse, Betreff,
     * gerenderter Text und ein Fingerabdruck, den der Versand erneut prüft.
     *
     * @return array{to: string, reply_to: string, subject: string, body: string, hash: string}
     */
    public function preview(AppWish $wish, User $sender): array {
        $mail = $this->mail($wish, $sender);
        $body = (string) $mail->render();
        $to = $this->address();

        return [
            'to' => $to,
            'reply_to' => $mail->replyToName . ' <' . $mail->replyToAddress . '>',
            'subject' => $mail->mailSubject,
            'body' => $body,
            'hash' => (string) CryptoHelper::hash($to . "\n" . $mail->replyToAddress . "\n" . $mail->mailSubject . "\n" . $body),
        ];
    }

    /**
     * An den Hersteller senden — nur mit eingeschaltetem Kanal, einmal je
     * Wunsch und nur, wenn die Vorschau, die der Admin gesehen hat, noch
     * stimmt (sonst ändert sich etwa die Stimmenzahl unbemerkt).
     *
     * @throws ValidationException
     */
    public function send(AppWish $wish, User $sender, string $previewHash): AppWish {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['vendor' => __('wishes.validation.vendor_disabled')]);
        }

        return DB::transaction(function () use ($wish, $sender, $previewHash): AppWish {
            $locked = $this->wishes->forwardedQuery()->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($locked->isSentToVendor()) {
                throw ValidationException::withMessages(['vendor' => __('wishes.validation.vendor_already_sent')]);
            }
            if ($locked->platform_bundle_id !== null) {
                // Gesendet wird der führende Wunsch eines Bündels mit den Stimmen aller.
                throw ValidationException::withMessages(['vendor' => __('wishes.validation.answer_at_lead')]);
            }
            $preview = $this->preview($locked, $sender);
            if (! hash_equals($preview['hash'], $previewHash)) {
                throw ValidationException::withMessages(['vendor' => __('wishes.validation.vendor_preview_stale')]);
            }
            try {
                Mail::to($preview['to'])->send($this->mail($locked, $sender));
            } catch (Throwable $e) {
                Log::warning('wishes.vendor_mail_failed', ['wish' => $locked->id, 'error' => $e->getMessage()]);

                throw ValidationException::withMessages(['vendor' => __('wishes.validation.vendor_send_failed')]);
            }
            $locked->update([
                'vendor_body' => $this->text($locked),
                'vendor_sent_at' => now(),
                'vendor_sent_by' => (int) $sender->id,
            ]);
            $locked->audit('app_wish.vendor_sent', ['to' => $preview['to']]);

            return $locked;
        });
    }

    /** Antwort des Herstellers von Hand eintragen; sie gilt für das Bündel und erreicht die Organisationen. */
    public function recordAnswer(AppWish $wish, string $answer): AppWish {
        $answer = StringHelper::trimToNull($answer);
        [$lead, $members] = DB::transaction(function () use ($wish, $answer): array {
            $lead = $this->wishes->forwardedQuery()->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if (! $lead->isSentToVendor()) {
                throw ValidationException::withMessages(['vendor_answer' => __('wishes.validation.vendor_not_sent')]);
            }
            $members = [$lead, ...$this->wishes->forwardedQuery()->where('platform_bundle_id', $lead->id)->lockForUpdate()->get()->all()];
            foreach ($members as $member) {
                $member->update(['vendor_answer' => $answer, 'vendor_answered_at' => $answer !== null ? now() : null]);
            }

            return [$lead, $answer !== null ? $members : []];
        });
        foreach ($members as $member) {
            $this->wishes->notifyAnswered($member, 'vendor');
        }

        return $lead;
    }
}
