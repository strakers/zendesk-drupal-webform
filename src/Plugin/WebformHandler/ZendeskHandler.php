<?php
/**
 * Created by PhpStorm.
 * User: Steven
 * Date: 2019-05-18
 * Time: 10:05 AM
 */

namespace Drupal\zendesk_webform\Plugin\WebformHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\zendesk_webform\Client\ZendeskClient;


/**
 * Form submission to Zendesk handler.
 *
 * @WebformHandler(
 *   id = "zendesk",
 *   label = @Translation("Zendesk"),
 *   category = @Translation("Zendesk"),
 *   description = @Translation("Sends a form submission to Zendesk to create a support ticket."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class ZendeskHandler extends WebformHandlerBase
{
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'requester' => '',
            'subject' => '',
            'comment' => '[webform_submission:values]', // by default lists all submission values as body
            'tags' => 'drupal webform',
            'priority' => 'normal',
            'status' => 'new',
            'type' => 'question',
            'collaborators' => '',
            'custom_fields' => '',
        ];
    }

    /**
     * @return array
     */
    public function defaultConfigurationNames()
    {
        return array_keys( $this->defaultConfiguration() );
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {


        $webform_fields = $this->getWebform()->getElementsDecoded();
        $options_email = [''];

        foreach($webform_fields as $key => $field){
            if( $this->checkIsGroupingField($field) ){
                foreach($field as $subkey => $subfield){
                    if(!preg_match("/^#/",$subkey) && isset($subfield['#type']) && $this->checkIsEmailField($subfield) ){
                        $options_email[$subkey] = $subfield['#title'];
                    }
                }
            }
            elseif( $this->checkIsEmailField($field) ){
                $options_email[$key] = $field['#title'];
            }
        }

        $form['requester'] = [
            '#type' => 'webform_select_other',
            '#title' => $this->t('Requester email address'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['requester'],
            '#options' => $options_email,
            '#required' => true
        ];

        $form['subject'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Subject'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['subject'],
            '#required' => true
        ];

        $form['type'] = [
            '#type' => 'select',
            '#title' => $this->t('Ticket Type'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['type'],
            '#options' => [
                'question' => 'Question',
                'incident' => 'Incident',
                'problem' => 'Problem',
                'task' => 'Task'
            ],
            '#required' => false
        ];

        $form['priority'] = [
            '#type' => 'select',
            '#title' => $this->t('Ticket Priority'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['priority'],
            '#options' => [
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent'
            ],
            '#required' => false
        ];

        $form['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Ticket Status'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['status'],
            '#options' => [
                'new' => 'New',
                'open' => 'Open',
                'pending' => 'Pending',
                'hold' => 'Hold',
                'solved' => 'Solved',
                'closed' => 'Closed'
            ],
            '#required' => false
        ];

        $form['comment'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Ticket Body'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['comment'],
            '#format' => 'full_html',
            '#required' => true
        ];

        // display link for token variables
        $form['token_link'] = $this->getTokenManager()->buildTreeLink();

        // space separated tags
        $form['tags'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Ticket Tags'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['tags'],
            '#multiple' => true,
            '#required' => false
        ];

        $form['collaborators'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Ticket CCs'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['collaborators'],
            '#multiple' => true,
            '#required' => false
        ];

        $form['custom_fields'] = [
            '#type' => 'webform_codemirror',
            '#mode' => 'yaml',
            '#title' => $this->t('Ticket Custom Fields'),
            '#description' => $this->t(''),
            '#default_value' => $this->configuration['custom_fields'],
            '#required' => false
        ];

        return parent::buildConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        parent::submitForm($form, $form_state, $webform_submission); // TODO: Change the autogenerated stub

        $webform = $webform_submission->getData();
        $config = $this->configuration;

        $request = [
            'subject' => 'test 1 ticket',
            'requester' => [
                'email' => 'scsisland@gmail.com'
            ],
            'comment' => [
                'body' => 'this is a test tickets'
            ],
            'tags' => [],
            'priority' => 'low',
            'status' => 'new',
            'collaborators' => [],
        ];

        try {
            $client = new ZendeskClient();
            $ticket = $client->tickets()->create($request);
        }
        catch( \Exception $e ){

            $message = $e->getMessage();

            // Encode HTML entities to prevent broken markup from breaking the page.
            $message = nl2br(htmlentities($message));

            // Log error message.
            $context = [
                '@exception' => get_class($e),
                '@form' => $this->getWebform()->label(),
                '@state' => '??',
                '@message' => $message,
                'link' => $this->getWebform()->toLink($this->t('Edit'), 'handlers')->toString(),
            ];
            $this->getLogger()->error('@form webform submission to zendesk failed. @exception: @message', $context);
        }
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state); // TODO: Change the autogenerated stub

        $submission_value = $form_state->getValues();
        foreach($this->configuration as $key => $value){
            if(isset($submission_value[$key])){
                $this->configuration[$key] = $submission_value[$key];
            }
        }
    }

    public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE)
    {
        parent::postSave($webform_submission, $update); // TODO: Change the autogenerated stub
    }

    public function getSummary()
    {
        return parent::getSummary(); // TODO: Change the autogenerated stub
    }
}