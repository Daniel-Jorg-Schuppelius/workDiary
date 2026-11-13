{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _markdown_help.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kurzanleitung Markdown im Seiteneditor (Feature 191, MVP-1664). Die
  Beispiele benutzen dieselben Klassen wie KnowledgeMarkdownRenderer, damit
  Tailwind sie erzeugt.
--}}
<details class="collapse collapse-arrow border border-base-300 bg-base-200">
    <summary class="collapse-title text-sm font-medium">{{ __('knowledge_pages.editor.help_title') }}</summary>
    <div class="collapse-content space-y-2 text-xs">
        <p>{{ __('knowledge_pages.editor.help_intro') }}</p>
        <pre class="whitespace-pre-wrap rounded-box bg-base-100 p-2">{{ __('knowledge_pages.editor.help_syntax') }}</pre>
        <div class="alert alert-info my-1 items-start text-sm" role="note"><div class="min-w-0"><p class="font-semibold">{{ __('knowledge_pages.callout.note') }}</p><p>{{ __('knowledge_pages.editor.help_note') }}</p></div></div>
        <div class="alert alert-success my-1 items-start text-sm" role="note"><div class="min-w-0"><p class="font-semibold">{{ __('knowledge_pages.callout.tip') }}</p></div></div>
        <div class="alert alert-warning my-1 items-start text-sm" role="alert"><div class="min-w-0"><p class="font-semibold">{{ __('knowledge_pages.callout.warning') }}</p></div></div>
    </div>
</details>
