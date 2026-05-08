<?php
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace KTXT\MailManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
    }

    public function testArrayOperations(): void
    {
        $array = ['foo' => 'bar'];
        
        $this->assertArrayHasKey('foo', $array);
        $this->assertEquals('bar', $array['foo']);
    }

    public function testStringOperations(): void
    {
        $string = 'Hello, World!';
        
        $this->assertStringContainsString('World', $string);
        $this->assertEquals(13, strlen($string));
    }
}
