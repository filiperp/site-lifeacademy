<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Lê os documentos legais de resources/legal/{locale}/{slug}.md.
 *
 * Os textos vinculantes existem só em português — traduzir cláusula de
 * reembolso e LGPD por conta própria seria assumir risco jurídico. Quem
 * navega em inglês ou espanhol vê o texto em português com um aviso de que
 * essa é a versão que vale.
 */
class LegalContent
{
    private const FALLBACK_LOCALE = 'pt_BR';

    /** @return array{body: string, locale: string, translated: bool} */
    public function get(string $slug): array
    {
        $locale = app()->getLocale();
        $path = $this->pathFor($slug, $locale);

        if (! File::exists($path)) {
            $locale = self::FALLBACK_LOCALE;
            $path = $this->pathFor($slug, $locale);
        }

        abort_unless(File::exists($path), 404);

        return [
            'body'       => $this->render(File::get($path)),
            'locale'     => $locale,
            'translated' => $locale === app()->getLocale(),
        ];
    }

    private function pathFor(string $slug, string $locale): string
    {
        return resource_path("legal/{$locale}/".basename($slug).'.md');
    }

    /**
     * Markdown reduzido: títulos, listas e parágrafos. É tudo o que estes
     * documentos usam, e evita trazer um parser inteiro por quatro páginas.
     */
    private function render(string $markdown): string
    {
        $html = '';
        $list = false;

        foreach (preg_split('/\R/', $markdown) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '- ')) {
                $html .= ($list ? '' : '<ul>').'<li>'.$this->inline(substr($line, 2)).'</li>';
                $list = true;

                continue;
            }

            if ($list) {
                $html .= '</ul>';
                $list = false;
            }

            if (preg_match('/^(#{2,6})\s+(.*)$/', $line, $m)) {
                $level = min(strlen($m[1]), 6);
                $html .= "<h{$level}>".$this->inline($m[2])."</h{$level}>";

                continue;
            }

            $html .= '<p>'.$this->inline($line).'</p>';
        }

        return $html.($list ? '</ul>' : '');
    }

    /** Escapa tudo e só então transforma **negrito** e e-mails em links. */
    private function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

        return preg_replace(
            '/([\w.+-]+@[\w-]+\.[\w.]+)/',
            '<a href="mailto:$1">$1</a>',
            $text,
        );
    }
}
