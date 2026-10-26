<?php

namespace App\Support;

/** Escribe un monto en palabras, como se pone al pie de una factura boliviana: "Tres mil 50/100 bolivianos". */
class NumeroLetras
{
    private const UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
        'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];

    private const DECENAS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];

    private const CENTENAS = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];

    public static function moneda(float $monto, string $moneda = 'bolivianos'): string
    {
        $monto = round($monto, 2);
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        return ucfirst(self::enteros($entero)).sprintf(' %02d/100 %s', $centavos, $moneda);
    }

    public static function enteros(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n >= 1_000_000_000) {
            return (string) $n; // fuera de rango razonable para un comprobante
        }

        $millones = intdiv($n, 1_000_000);
        $miles = intdiv($n % 1_000_000, 1000);
        $resto = $n % 1000;

        $texto = [];
        if ($millones) {
            $texto[] = $millones === 1 ? 'un millón' : self::centenas($millones, true).' millones';
        }
        if ($miles) {
            $texto[] = $miles === 1 ? 'mil' : self::centenas($miles, true).' mil';
        }
        if ($resto) {
            $texto[] = self::centenas($resto);
        }

        return implode(' ', $texto);
    }

    /** De 1 a 999. Antes de "mil" y "millones", "uno" se apocopa: "veintiún mil", "treinta y un mil". */
    private static function centenas(int $n, bool $apocopar = false): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $c = intdiv($n, 100);
        $r = $n % 100;
        $partes = [];

        if ($c) {
            $partes[] = self::CENTENAS[$c];
        }
        if ($r) {
            $partes[] = $r < 30 ? self::UNIDADES[$r] : self::DECENAS[intdiv($r, 10)].($r % 10 ? ' y '.self::UNIDADES[$r % 10] : '');
        }

        $texto = implode(' ', $partes);

        return $apocopar ? preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $texto) : $texto;
    }
}
