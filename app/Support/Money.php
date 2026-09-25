<?php

namespace App\Support;

use NumberFormatter;

class Money
{
    /**
     * Formata em BRL respeitando o idioma ativo: R$ 197,00 em português,
     * R$197.00 em inglês. A moeda continua sendo real — a loja cobra em BRL
     * nos três idiomas, só a apresentação muda.
     */
    public static function format(float|string|null $value, string $currency = 'BRL'): string
    {
        $formatter = new NumberFormatter(self::icuLocale(), NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency((float) $value, $currency);
    }

    /**
     * Valor da parcela arredondado para cima, para que a soma das parcelas
     * nunca fique abaixo do total exibido.
     */
    public static function installment(float $total, int $count): float
    {
        if ($count < 1) {
            return $total;
        }

        return ceil(($total / $count) * 100) / 100;
    }

    private static function icuLocale(): string
    {
        return match (app()->getLocale()) {
            'en' => 'en_US',
            'es' => 'es_419',
            default => 'pt_BR',
        };
    }
}
