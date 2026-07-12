<?php
declare(strict_types=1);

use BailaYa\Support\Phone;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    public function testPrefixesPlusWhenDigitsAlreadyIncludeTheCountryCode(): void
    {
        // The real Independent Dance value: stored bare, country code already present.
        self::assertSame('+525534669220', Phone::toE164('525534669220', 'MX'));
    }

    public function testPrependsTheCountryCallingCodeToANationalNumber(): void
    {
        self::assertSame('+525534669220', Phone::toE164('5534669220', 'MX'));
    }

    public function testKeepsAnExplicitInternationalNumberAndStripsSeparators(): void
    {
        self::assertSame('+525534669220', Phone::toE164('+52 55 3466 9220', 'MX'));
        self::assertSame('+14155550132', Phone::toE164('+1 (415) 555-0132', 'US'));
    }

    public function testDoesNotStripLeadingDigitsThatMerelyResembleTheCallingCode(): void
    {
        // MX calling code is "52"; a national number starting "55" must keep its digits.
        self::assertSame('+525534669220', Phone::toE164('5534669220', 'MX'));
    }

    public function testFallsBackToBareDigitsWhenTheCountryIsUnknown(): void
    {
        self::assertSame('+5534669220', Phone::toE164('5534669220', 'ZZ'));
        self::assertSame('+5534669220', Phone::toE164('5534669220', null));
    }

    public function testReturnsNullWhenThereIsNothingDialable(): void
    {
        self::assertNull(Phone::toE164(null, 'MX'));
        self::assertNull(Phone::toE164('', 'MX'));
        self::assertNull(Phone::toE164('n/a', 'MX'));
    }
}
