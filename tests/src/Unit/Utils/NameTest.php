<?php

namespace Drupal\Tests\zendesk_webform\Unit\Utils;

use Drupal\Tests\UnitTestCase;
use Drupal\zendesk_webform\Utils\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(Name::class)]
#[Group('zendesk_webform')]
class NameTest extends UnitTestCase
{

    public function testProcessWithPlainString()
    {
        $this->assertSame('Jane', Name::process('Jane'));
    }

    public function testProcessWithFullNameArray()
    {
        $name = [
            'title' => 'Dr.',
            'first' => 'Jane',
            'middle' => 'Q',
            'last' => 'Doe',
            'suffix' => 'Jr.',
            'degree' => 'PhD',
        ];
        $this->assertSame('Dr. Jane Q Doe Jr. PhD', Name::process($name));
    }

    public function testProcessWithPartialNameArray()
    {
        $name = [
            'first' => 'Jane',
            'last' => 'Doe',
        ];
        $this->assertSame('Jane Doe', Name::process($name));
    }

    public function testProcessTrimsWhitespaceOnlyParts()
    {
        $name = [
            'title' => '  ',
            'first' => 'Jane',
            'last' => 'Doe',
        ];
        $this->assertSame('Jane Doe', Name::process($name));
    }
}
