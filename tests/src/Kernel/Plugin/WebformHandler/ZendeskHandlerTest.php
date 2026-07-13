<?php

namespace Drupal\Tests\zendesk_webform\Kernel\Plugin\WebformHandler;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\zendesk_webform\Plugin\WebformHandler\ZendeskHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the data transformations performed by ZendeskHandler when building
 * a Zendesk ticket request, independent of the live Zendesk API call.
 */
#[CoversClass(ZendeskHandler::class)]
#[Group('zendesk_webform')]
class ZendeskHandlerTest extends KernelTestBase
{

    /**
     * @var array
     */
    protected static $modules = ['system', 'user', 'field', 'file', 'webform', 'zendesk_webform'];

    /**
     * @var \Drupal\zendesk_webform\Plugin\WebformHandler\ZendeskHandler
     */
    protected $handler;

    /**
     * @var \Drupal\webform\WebformInterface
     */
    protected $webform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installSchema('webform', ['webform']);
        $this->installConfig('webform');
        $this->installEntitySchema('webform_submission');
        $this->installEntitySchema('user');
        $this->installEntitySchema('file');

        $this->webform = Webform::create([
            'id' => 'zendesk_test_form',
            'title' => 'Zendesk Test Form',
            'elements' => Yaml::encode([
                'name' => ['#type' => 'webform_name', '#title' => 'Name'],
                'email' => ['#type' => 'email', '#title' => 'Email'],
                'ticket_id' => ['#type' => 'hidden', '#title' => 'Ticket ID'],
            ]),
        ]);
        $this->webform->save();

        $this->handler = \Drupal::service('plugin.manager.webform.handler')->createInstance('zendesk');
        $this->handler->setWebform($this->webform);
    }

    /**
     * Invokes the protected buildTicketRequest() method via reflection, since
     * it's intentionally not part of the handler's public API.
     */
    protected function buildTicketRequest(array $configuration, array $submission_fields, $webform_submission): array
    {
        $method = new \ReflectionMethod($this->handler, 'buildTicketRequest');
        $method->setAccessible(true);
        return $method->invoke($this->handler, $configuration, $submission_fields, $webform_submission);
    }

    public function testDefaultConfiguration()
    {
        $defaults = $this->handler->defaultConfiguration();
        $this->assertArrayHasKey('requester_email', $defaults);
        $this->assertArrayHasKey('ticket_id_field', $defaults);
        $this->assertSame('drupal webform', $defaults['tags']);
        $this->assertSame(0, $defaults['delete_after_delivery']);
        $this->assertSame(array_keys($defaults), $this->handler->defaultConfigurationNames());
    }

    public function testBuildTicketRequestCleansTagsAndCollaborators()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['tags'] = 'Drupal, Webform!';
        $configuration['collaborators'] = 'alice@example.com, bob@example.com';
        $configuration['requester_email'] = 'jane.doe@example.com';

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame('drupal webform ', $request['tags']);
        $this->assertSame(['alice@example.com', 'bob@example.com'], $request['collaborators']);
    }

    public function testBuildTicketRequestRestructuresRequesterWithName()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['requester_name'] = 'Jane Doe';
        $configuration['requester_email'] = 'jane.doe@example.com';

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame([
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
        ], $request['requester']);
        $this->assertArrayNotHasKey('requester_name', $request);
        $this->assertArrayNotHasKey('requester_email', $request);
    }

    public function testBuildTicketRequestRestructuresRequesterWithoutName()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['requester_name'] = '';
        $configuration['requester_email'] = 'jane.doe@example.com';

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame('jane.doe@example.com', $request['requester']);
    }

    public function testBuildTicketRequestRestructuresComment()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['comment'] = 'A support request.';

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame(['body' => 'A support request.'], $request['comment']);
    }

    public function testBuildTicketRequestConvertsCustomFieldsYaml()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['custom_fields'] = "12345678: 'foobar'\n87654321: 'baz'";

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame([
            ['id' => 12345678, 'value' => 'foobar'],
            ['id' => 87654321, 'value' => 'baz'],
        ], $request['custom_fields']);
    }

    public function testBuildTicketRequestSetsExternalId()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();

        $request = $this->buildTicketRequest($configuration, ['data' => []], $submission);

        $this->assertSame($submission->id(), $request['external_id']);
    }

    public function testBuildTicketRequestPrefersSubmissionDataOverStaticValue()
    {
        $submission = WebformSubmission::create(['webform_id' => $this->webform->id()]);
        $submission->save();

        $configuration = $this->handler->defaultConfiguration();
        $configuration['subject'] = 'email';

        $request = $this->buildTicketRequest($configuration, ['data' => ['email' => 'jane.doe@example.com']], $submission);

        $this->assertSame('jane.doe@example.com', $request['subject']);
    }
}
