<?php

namespace Drupal\Tests\zendesk_webform\Unit\Utils;

use Drupal\Tests\UnitTestCase;
use Drupal\zendesk_webform\Utils\Utility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(Utility::class)]
#[Group('zendesk_webform')]
class UtilityTest extends UnitTestCase
{

    #[DataProvider('providerCheckIsNameField')]
    public function testCheckIsNameField($type, $expected)
    {
        $this->assertSame($expected, Utility::checkIsNameField(['#type' => $type]));
    }

    public static function providerCheckIsNameField()
    {
        return [
            'webform_name' => ['webform_name', true],
            'textfield' => ['textfield', true],
            'email' => ['email', false],
            'hidden' => ['hidden', false],
        ];
    }

    #[DataProvider('providerCheckIsEmailField')]
    public function testCheckIsEmailField($type, $expected)
    {
        $this->assertSame($expected, Utility::checkIsEmailField(['#type' => $type]));
    }

    public static function providerCheckIsEmailField()
    {
        return [
            'email' => ['email', true],
            'webform_email_confirm' => ['webform_email_confirm', true],
            'textfield' => ['textfield', false],
            'hidden' => ['hidden', false],
        ];
    }

    #[DataProvider('providerCheckIsHiddenField')]
    public function testCheckIsHiddenField($type, $expected)
    {
        $this->assertSame($expected, Utility::checkIsHiddenField(['#type' => $type]));
    }

    public static function providerCheckIsHiddenField()
    {
        return [
            'hidden' => ['hidden', true],
            'textfield' => ['textfield', false],
        ];
    }

    #[DataProvider('providerCheckIsGroupingField')]
    public function testCheckIsGroupingField($type, $expected)
    {
        $this->assertSame($expected, Utility::checkIsGroupingField(['#type' => $type]));
    }

    public static function providerCheckIsGroupingField()
    {
        return [
            'webform_section' => ['webform_section', true],
            'textfield' => ['textfield', false],
        ];
    }

    #[DataProvider('providerCleanTags')]
    public function testCleanTags($input, $expected)
    {
        $this->assertSame($expected, Utility::cleanTags($input));
    }

    public static function providerCleanTags()
    {
        return [
            'simple space separated' => ['drupal webform', 'drupal webform'],
            'mixed case normalized to lower' => ['Drupal WEBFORM', 'drupal webform'],
            'punctuation collapsed to spaces' => ['drupal, webform; zendesk', 'drupal webform zendesk'],
            'trailing punctuation leaves a trailing space' => ['drupal, webform; zendesk!', 'drupal webform zendesk '],
            'empty string' => ['', ''],
        ];
    }

    #[DataProvider('providerConvertTags')]
    public function testConvertTags($input, $expected)
    {
        $this->assertSame($expected, Utility::convertTags($input));
    }

    public static function providerConvertTags()
    {
        return [
            'mixed case normalized to lower' => ['Drupal WEBFORM', 'drupal webform'],
            'punctuation collapsed to spaces' => ['drupal, webform; zendesk', 'drupal webform zendesk'],
        ];
    }

    public function testConvertTableWithFields()
    {
        $html = Utility::convertTable([12345 => 'Subject', 67890 => 'Priority']);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<td>Subject</td><td>12345</td>', $html);
        $this->assertStringContainsString('<td>Priority</td><td>67890</td>', $html);
    }

    public function testConvertTableWithNoFields()
    {
        $this->assertSame('', Utility::convertTable([]));
    }
}
