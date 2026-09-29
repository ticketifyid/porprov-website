<?php

namespace Tests\Unit;

use App\Support\RegistrationCodeGenerator;
use PHPUnit\Framework\TestCase;

class RegistrationCodeGeneratorTest extends TestCase
{
    public function test_generates_codes_matching_the_pjt26_format_using_crockford_base32(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $code = RegistrationCodeGenerator::make('PJT26');

            $this->assertMatchesRegularExpression('/^PJT26-[0-9A-HJKMNP-TV-Z]{6}$/', $code);
            $this->assertStringNotContainsString('I', $code);
            $this->assertStringNotContainsString('L', $code);
            $this->assertStringNotContainsString('O', $code);
            $this->assertStringNotContainsString('U', $code);
        }
    }
}
