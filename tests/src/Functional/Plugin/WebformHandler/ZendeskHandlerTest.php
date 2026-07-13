<?php

namespace Drupal\Tests\zendesk_webform\Functional\Plugin\WebformHandler;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Zendesk webform handler's admin UI and submission handling.
 *
 * No real Zendesk credentials are configured, so outbound API calls are
 * expected to fail; these tests confirm that failure is handled gracefully
 * (logged, no fatal error) rather than exercising real ticket delivery.
 */
#[Group('zendesk_webform')]
class ZendeskHandlerTest extends WebformBrowserTestBase
{

    /**
     * @var array
     */
    protected static $modules = ['webform', 'zendesk_webform'];

    /**
     * @var \Drupal\webform\WebformInterface
     */
    protected $webform;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function testHandlerConfigurationForm()
    {
        $this->drupalLogin($this->drupalCreateUser(['administer webform']));

        $this->drupalGet('/admin/structure/webform/manage/' . $this->webform->id() . '/handlers/add/zendesk');
        $assert_session = $this->assertSession();
        $assert_session->statusCodeEquals(200);

        // The requester name/email dropdowns should be populated from the
        // webform's own elements.
        $assert_session->optionExists('settings[people][requester_name][select]', 'Name');
        $assert_session->optionExists('settings[people][requester_email][select]', 'Email');
        $assert_session->optionExists('settings[advanced][ticket_id_field][select]', 'Ticket ID');

        // Save settings spread across the form's sections; this guards
        // against a past regression where section-nested fields silently
        // failed to save.
        $edit = [
            'label' => 'Zendesk',
            'handler_id' => 'zendesk',
            'status' => 1,
            'settings[people][requester_name][select]' => 'name',
            'settings[people][requester_email][select]' => 'email',
            'settings[content][subject]' => 'New support request',
            'settings[content][comment]' => '[webform_submission:values]',
            'settings[content][tags]' => 'drupal webform',
            'settings[ticket][type]' => 'question',
            'settings[ticket][priority]' => 'normal',
            'settings[ticket][status]' => 'new',
            'settings[advanced][ticket_id_field][select]' => 'ticket_id',
        ];
        $this->submitForm($edit, 'Save');
        $assert_session->statusCodeEquals(200);

        $webform = Webform::load($this->webform->id());
        $configuration = $webform->getHandlers()->get('zendesk')->getConfiguration();
        $this->assertSame('name', $configuration['settings']['requester_name']);
        $this->assertSame('email', $configuration['settings']['requester_email']);
        $this->assertSame('New support request', $configuration['settings']['subject']);
        $this->assertSame('ticket_id', $configuration['settings']['ticket_id_field']);
    }

    public function testHandlerSubmission()
    {
        $webform = $this->webform;
        $webform->addWebformHandler(\Drupal::service('plugin.manager.webform.handler')->createInstance('zendesk', [
            'id' => 'zendesk',
            'handler_id' => 'zendesk',
            'label' => 'Zendesk',
            'status' => 1,
            'weight' => 0,
            'settings' => [
                'requester_name' => 'name',
                'requester_email' => 'email',
                'subject' => 'New support request',
                'comment' => '[webform_submission:values]',
                'tags' => 'drupal webform',
                'priority' => 'normal',
                'status' => 'new',
                'assignee_id' => '',
                'type' => 'question',
                'collaborators' => '',
                'custom_fields' => '',
                'ticket_id_field' => 'ticket_id',
                'delete_after_delivery' => 0,
            ],
        ]));
        $webform->save();

        $sid = $this->postSubmission($webform, [
            'name[first]' => 'Jane',
            'name[last]' => 'Doe',
            'email' => 'jane.doe@example.com',
        ]);

        // The submission itself must succeed, and the page must render
        // normally, even though the (unreachable, uncredentialed) Zendesk
        // API call behind it will fail.
        $this->assertSession()->statusCodeEquals(200);
        $this->assertNotNull($sid);
        $submission = $this->loadSubmission($sid);
        $this->assertNotNull($submission);
    }
}
