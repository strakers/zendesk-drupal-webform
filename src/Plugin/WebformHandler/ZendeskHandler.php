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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\Core\Serialization\Yaml;


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
     * @var WebformTokenManagerInterface $token_manager
     */
    protected $token_manager;

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

        /*
         * -------------------------------------------------------------------------------------------------------------
         * Request format:
         *
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
        * --------------------------------------------------------------------------------------------------------------
        */

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
        if (! $update) {

            $request = [];
            $fields = $webform_submission->toArray(TRUE);
            $configuration = $this->getTokenManager()->replace($this->configuration, $webform_submission);

            // Allow for either values coming from other fields or static/tokens
            foreach ($this->defaultConfigurationNames() as $field) {
                $request[$field] = $configuration[$field];
                if (!empty($fields['data'][$configuration[$field]])) {
                    $request[$field] = $fields['data'][$configuration[$field]];
                }
            }

            // restructure comment array
            if(!isset($request['comment']['body'])){
                $comment = $request['comment'];
                //unset($request['comment']);
                $request['comment'] = [
                    'body' => $comment
                ];
            }

            // get custom fields
            $custom_fields = Yaml::decode($request['custom_fields']);
            $custom_fields['360017939614'] = 'Webform';
            $request['custom_fields'] = [];
            foreach($custom_fields as $key => $value){
                $request['custom_fields'][] = [
                    'id' => $key,
                    'value' => $value
                ];
            }

            // attempt to send request to create zendesk ticket
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
        return;
    }

    public function getSummary()
    {
        return parent::getSummary(); // TODO: Change the autogenerated stub
    }

    public function setTokenManager( WebformTokenManagerInterface $token_manager ){
        $this->token_manager = $token_manager;
    }
    public function getTokenManager(){
        return $this->token_manager;
    }


    protected function checkIsEmailField( array $field ){
        return in_array( $field['#type'], [ 'email', 'webform_email_confirm' ] );
    }
    protected function checkIsGroupingField( array $field ){
        return in_array( $field['#type'], [ 'webform_section' ] );
    }
    protected function convertTags( $text = '' ){
        return strtolower(implode(' ',preg_split("/[^a-z0-9_]+/i",$text)));
    }
}