<?php

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_numbers_starting_with_08_to_628(): void
    {
        $this->assertSame('6281234567890', PhoneNormalizer::normalize('081234567890'));
    }

    public function test_normalizes_numbers_starting_with_plus_62(): void
    {
        $this->assertSame('6281234567890', PhoneNormalizer::normalize('+6281234567890'));
    }

    public function test_leaves_numbers_starting_with_62_as_is(): void
    {
        $this->assertSame('6281234567890', PhoneNormalizer::normalize('6281234567890'));
    }

    public function test_strips_separators_like_dashes_and_spaces(): void
    {
        $this->assertSame('6281234567890', PhoneNormalizer::normalize('0812-3456 7890'));
    }

    public function test_returns_empty_string_for_empty_or_null_input(): void
    {
        $this->assertSame('', PhoneNormalizer::normalize(''));
        $this->assertSame('', PhoneNormalizer::normalize(null));
    }
}
