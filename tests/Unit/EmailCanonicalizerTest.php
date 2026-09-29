<?php

namespace Tests\Unit;

use App\Support\EmailCanonicalizer;
use PHPUnit\Framework\TestCase;

class EmailCanonicalizerTest extends TestCase
{
    public function test_canonicalizes_gmail_by_stripping_dots_and_plus_suffix(): void
    {
        $this->assertSame('budi@gmail.com', EmailCanonicalizer::canonicalize('B.u.di+x@Gmail.com'));
    }

    public function test_treats_googlemail_as_gmail(): void
    {
        $this->assertSame('budi@gmail.com', EmailCanonicalizer::canonicalize('budi@googlemail.com'));
    }

    public function test_does_not_strip_dots_for_non_gmail_domains(): void
    {
        $this->assertSame('b.udi@yahoo.com', EmailCanonicalizer::canonicalize('b.udi@yahoo.com'));
    }

    public function test_does_not_strip_plus_suffix_for_non_gmail_domains(): void
    {
        $this->assertSame('budi+x@yahoo.com', EmailCanonicalizer::canonicalize('budi+x@yahoo.com'));
    }
}
