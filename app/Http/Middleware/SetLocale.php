<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idioma pela sessão, com o Accept-Language do navegador como palpite inicial.
 *
 * O site atual não tem tradução de verdade — usa o widget do Google Translate.
 * Aqui os três idiomas são conteúdo próprio, em lang/{pt_BR,en,es}.
 */
class SetLocale
{
    public const SUPPORTED = ['pt_BR', 'en', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $session = $request->session()->get('locale');

        if (in_array($session, self::SUPPORTED, true)) {
            return $session;
        }

        return $this->fromBrowser($request) ?? config('app.locale');
    }

    private function fromBrowser(Request $request): ?string
    {
        $preferred = $request->getPreferredLanguage(['pt_BR', 'pt', 'en', 'es']);

        return match ($preferred) {
            'pt_BR', 'pt' => 'pt_BR',
            'en'          => 'en',
            'es'          => 'es',
            default       => null,
        };
    }
}
