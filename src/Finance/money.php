<?php

declare(strict_types=1);



function money_to_cents(string $value): int
{
    $value = trim($value);

    $value = str_replace(',', '.', $value);

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException(
            'Valor monetário inválido.'
        );
    }

    [$euros, $decimals] = array_pad(
        explode('.', $value, 2),
        2,
        ''
    );

    $decimals = str_pad(
        $decimals,
        2,
        '0'
    );

    return ((int) $euros * 100)
        + (int) substr($decimals, 0, 2);
}


/**
 * Converte um DECIMAL vindo da BD

 */
function decimal_to_cents(string $value): int
{
    $value = trim($value);

    if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException(
            'Valor decimal inválido.'
        );
    }

    $negative = str_starts_with($value, '-');

    if ($negative) {
        $value = substr($value, 1);
    }

    [$euros, $decimals] = array_pad(
        explode('.', $value, 2),
        2,
        ''
    );

    $decimals = str_pad(
        $decimals,
        2,
        '0'
    );

    $cents =
        ((int) $euros * 100)
        + (int) substr($decimals, 0, 2);

    return $negative ? -$cents : $cents;
}


/**
 * Converte cêntimos para DECIMAL.

 */
function cents_to_decimal(int $cents): string
{
    $negative = $cents < 0;

    $cents = abs($cents);

    $euros = intdiv($cents, 100);
    $decimal = $cents % 100;

    $result = sprintf(
        '%d.%02d',
        $euros,
        $decimal
    );

    return $negative
        ? '-' . $result
        : $result;
}


/**
 * Formata cêntimos para apresentação.

 */
function format_money(int $cents): string
{
    $negative = $cents < 0;

    $cents = abs($cents);

    $euros = intdiv($cents, 100);
    $decimal = $cents % 100;

    $formatted = number_format(
        $euros,
        0,
        ',',
        '.'
    );

    $formatted .= ',';

    $formatted .= str_pad(
        (string) $decimal,
        2,
        '0',
        STR_PAD_LEFT
    );

    return ($negative ? '-' : '') . $formatted . ' €';
}