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

    public function test_normalize_for_search_menyamakan_huruf_kecil_spasi_tanda_hubung_dan_o_i_l(): void
    {
        $this->assertSame('PJT2670K3M9', RegistrationCodeGenerator::normalizeForSearch(' pjt26 7ok3m9 '));
        $this->assertSame('PJT2670K3M9', RegistrationCodeGenerator::normalizeForSearch('PJT26-70K3M9'));
        $this->assertSame('PJT261K3M9Q', RegistrationCodeGenerator::normalizeForSearch('pjt26-lk3m9q'));
        $this->assertSame('PJT261K3M9Q', RegistrationCodeGenerator::normalizeForSearch('pjt26_ik3m9q'));
        $this->assertSame('', RegistrationCodeGenerator::normalizeForSearch('   '));
    }
}
