<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePagePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\User;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Support\{Carbon, Str};

/**
 * PDF einer Wissensseite und des Notfallhandbuchs (Feature 191, MVP-1668) im
 * Dokumentdesign der Organisation (Feature 076): je Seite Fassung, Freigabe,
 * Verantwortung und Gültigkeit, damit der Ausdruck auch bei Ausfall der App
 * als gelenktes Dokument taugt. Das Notfallhandbuch enthält nur freigegebene
 * Fassungen; eine Einzelseite zeigt Bearbeitenden die aktuelle Fassung und
 * kennzeichnet sie, solange sie nicht freigegeben ist.
 */
final class KnowledgePagePdfRenderer {
    public function __construct(
        private readonly DocumentDesignRenderer $design,
        private readonly KnowledgePageContent $content,
        private readonly KnowledgePageAccess $access,
    ) {}

    public function page(KnowledgePage $page, User $viewer): string {
        return $this->render([$this->entry($page, $this->content->forViewer($page, $viewer))], null, (int) $page->organization_id);
    }

    /** Alle für die Person lesbaren, freigegebenen Seiten mit Notfall-Kennzeichen. */
    public function emergencyHandbook(User $viewer): string {
        $pages = $this->access->scope(KnowledgePage::query(), $viewer)
            ->where('is_emergency', true)
            ->whereNotNull('approved_version')
            ->with(['space:id,title,position', 'section:id,title,position', 'owner:id,name'])
            ->get()
            ->sortBy(static fn (KnowledgePage $page): array => [$page->space->position ?? 0, $page->space->title ?? '', $page->section->position ?? -1, $page->section->title ?? '', $page->position, $page->title])
            ->values();

        $entries = [];
        foreach ($pages as $page) {
            $entries[] = $this->entry($page, $this->content->approved($page));
        }

        return $this->render($entries, (string) __('knowledge_pages.pdf.handbook_title'), (int) $viewer->organization_id);
    }

    public function filename(?KnowledgePage $page): string {
        $base = $page !== null ? Str::limit($page->title, 60, '') : (string) __('knowledge_pages.pdf.handbook_file');

        return Str::slug($base . '-' . Carbon::now()->format('Y-m-d')) . '.pdf';
    }

    /**
     * @param  array{version: int|null, title: string, body: string, steps: list<array{body: string|null, image: \App\Models\Attachments\Attachment|null, markers: list<array<string, mixed>>}>, approved_at: \Illuminate\Support\Carbon|null, approver: string|null, current: bool}  $content
     * @return array<string, mixed>
     */
    private function entry(KnowledgePage $page, array $content): array {
        $page->loadMissing(['space:id,title', 'section:id,title', 'owner:id,name']);
        $steps = [];
        foreach ($content['steps'] as $index => $step) {
            $steps[] = [
                'number' => $index + 1,
                'body' => $step['body'],
                'image' => $this->content->stepImage($step['image'], $step['markers'], true),
            ];
        }

        return [
            'page' => $page,
            'title' => $content['title'],
            'version' => $content['version'],
            'approved_at' => $content['approved_at'],
            'approver' => $content['approver'],
            'draft' => $content['approved_at'] === null,
            'html' => $this->content->html($page, $content['body'], true),
            'steps' => $steps,
        ];
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function render(array $entries, ?string $handbookTitle, int $organizationId): string {
        return $this->design->renderPdf(
            RenderDocumentKind::Report,
            'knowledge-pages.pdf',
            [
                'entries' => $entries,
                'handbookTitle' => $handbookTitle,
                'printedAt' => Carbon::now(),
            ],
            $organizationId,
        );
    }
}
