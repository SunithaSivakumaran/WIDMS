<?php
declare(strict_types=1);

/** Keep leading zeros: a salary number is an identifier, not an integer. */
function widmsSalaryNumber(string $value): ?string
{
    $value = trim($value);
    return preg_match('/\A[0-9]{1,30}\z/', $value) ? $value : null;
}

function widmsSalaryUsername(string $salaryNumber): string
{
    $number = widmsSalaryNumber($salaryNumber);
    if ($number === null) {
        throw new InvalidArgumentException('Enter a valid salary number using 1 to 30 digits.');
    }
    return 'swpcs' . $number;
}
