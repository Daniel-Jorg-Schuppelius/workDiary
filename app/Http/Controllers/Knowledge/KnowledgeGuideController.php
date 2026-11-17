<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGuideController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\{KnowledgeGuideStep, KnowledgePage};
use App\Services\Knowledge\Pages\Guide\{GuideImageRedactor, KnowledgeGuideService};
use App\Services\Knowledge\Pages\KnowledgePageContent;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Schritt-für-Schritt-Anleitungen aus Bildschirmfotos (Feature 191,
 * MVP-1668): Schritte anlegen, Markierungen setzen, Bereiche schwärzen und
 * die Anleitung Schritt für Schritt abspielen.
 */
class KnowledgeGuideController extends Controller {
    private const int MAX_IMAGE_KB = 10240;

    public function __construct(
        private readonly KnowledgeGuideService $guides,
        private readonly KnowledgePageContent $content,
    ) {}

    public function create(KnowledgePage $page): View {
        Gate::authorize('update', $page);
        abort_unless($page->isGuide(), 404);

        return view('knowledge-pages._step_dialog', ['page' => $page]);
    }

    public function store(Request $request, KnowledgePage $page): RedirectResponse {
        Gate::authorize('update', $page);
        abort_unless($page->isGuide(), 404);
        $data = $request->validate([
            'image' => ['nullable', 'file', 'mimetypes:' . implode(',', GuideImageRedactor::SUPPORTED), 'max:' . self::MAX_IMAGE_KB],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);
        $image = $request->file('image');
        $step = $this->guides->addStep($page, $this->authUser(), $image instanceof UploadedFile ? $image : null, $data['body'] ?? null);

        return redirect()->route($step->attachment_id !== null ? 'knowledge.pages.steps.edit' : 'knowledge.pages.show', $step->attachment_id !== null ? [$page, $step] : [$page])
            ->with('success', __('knowledge_pages.flash.step_added'));
    }

    /** Schritt bearbeiten: Text, Markierungen und Schwärzen auf dem Bild. */
    public function edit(KnowledgePage $page, KnowledgeGuideStep $step): View {
        Gate::authorize('update', $page);
        abort_unless((int) $step->knowledge_page_id === (int) $page->id, 404);
        $step->load('image');

        return view('knowledge-pages.step_edit', [
            'page' => $page,
            'step' => $step,
            'imageUrl' => $this->content->stepImage($step->image, [], false),
            'number' => (int) $page->steps()->where('position', '<', $step->position)->count() + 1,
        ]);
    }

    public function update(Request $request, KnowledgePage $page, KnowledgeGuideStep $step): RedirectResponse {
        Gate::authorize('update', $page);
        abort_unless((int) $step->knowledge_page_id === (int) $page->id, 404);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'markers' => ['nullable', 'string', 'max:20000'],
            'redactions' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'file', 'mimetypes:' . implode(',', GuideImageRedactor::SUPPORTED), 'max:' . self::MAX_IMAGE_KB],
        ]);
        $image = $request->file('image');
        $redactions = $this->decodeList((string) ($data['redactions'] ?? ''));
        $this->guides->updateStep(
            $step,
            $this->authUser(),
            $data['body'] ?? null,
            $this->decodeList((string) ($data['markers'] ?? '')),
            $redactions,
            $image instanceof UploadedFile ? $image : null,
        );

        return redirect()->route('knowledge.pages.steps.edit', [$page, $step])
            ->with('success', $redactions !== [] ? __('knowledge_pages.flash.step_redacted') : __('knowledge_pages.flash.step_saved'));
    }

    public function destroy(KnowledgePage $page, KnowledgeGuideStep $step): RedirectResponse {
        Gate::authorize('update', $page);
        abort_unless((int) $step->knowledge_page_id === (int) $page->id, 404);
        $this->guides->removeStep($step, $this->authUser());

        return redirect()->route('knowledge.pages.show', $page)->with('success', __('knowledge_pages.flash.step_removed'));
    }

    public function move(Request $request, KnowledgePage $page, KnowledgeGuideStep $step): RedirectResponse {
        Gate::authorize('update', $page);
        abort_unless((int) $step->knowledge_page_id === (int) $page->id, 404);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->guides->moveStep($step, $this->authUser(), $direction === 'up' ? -1 : 1);

        return back();
    }

    /** „Abspielen“: ein Schritt je Ansicht, vor und zurück. */
    public function play(Request $request, KnowledgePage $page): View {
        Gate::authorize('view', $page);
        abort_unless($page->isGuide(), 404);
        $content = $this->content->forViewer($page, $this->authUser());
        $total = count($content['steps']);
        abort_if($total === 0, 404);
        $index = max(1, min($total, $request->integer('schritt', 1)));
        $step = $content['steps'][$index - 1];

        return view('knowledge-pages.play', [
            'page' => $page,
            'content' => $content,
            'index' => $index,
            'total' => $total,
            'step' => [
                'body' => $step['body'],
                'image' => $this->content->stepImage($step['image'], $step['markers'], false),
                'markers' => $step['markers'],
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function decodeList(string $json): array {
        if (trim($json) === '' || ! JsonHelper::isValid($json)) {
            return [];
        }
        $decoded = JsonHelper::decode($json);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
