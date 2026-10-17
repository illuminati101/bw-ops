<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class MoneyAndPhoneTest extends TestCase
{
    public function test_quotes_round_up_to_the_next_five_rand(): void
    {
        $this->assertSame(1205.0, Money::roundQuote(1201.10));
        $this->assertSame(1200.0, Money::roundQuote(1200));
    }

    public function test_rand_amounts_are_formatted_with_spaces(): void
    {
        $this->assertSame('R1 250.00', Money::format(1250));
        $this->assertSame('R12 500', Money::format(12500, false));
        $this->assertSame(125000, Money::toCents(1250));
    }

    public function test_south_african_mobile_numbers_are_normalised(): void
    {
        $this->assertSame('+27821234567', PhoneNumber::normalize('082 123 4567'));
        $this->assertSame('+27821234567', PhoneNumber::normalize('+27 82 123 4567'));
        $this->assertNull(PhoneNumber::normalize('12'));
    }
}
