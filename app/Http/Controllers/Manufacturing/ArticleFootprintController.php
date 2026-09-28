<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleFootprintController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Article\Article;
use App\Services\Manufacturing\ProductCarbonFootprintService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Reiter „CO₂-Fußabdruck" der Artikel-Detailseite (MVP-960). */
class ArticleFootprintController extends Controller {
    public function show(Article $article, ProductCarbonFootprintService $footprints): View {
        Gate::authorize('view', $article);

        return view('articles.footprint', ['article' => $article, 'result' => $footprints->footprint($article)]);
    }

    public function update(Request $request, Article $article): RedirectResponse {
        Gate::authorize('update', $article);
        $data = $request->validate([
            'pcf_factor_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pcf_process_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pcf_source' => ['nullable', 'string', 'max:200'],
        ]);
        $article->forceFill($data)->save();

        return back()->with('success', __('article.footprint.flash.saved'));
    }
}
