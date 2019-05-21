<?php
/**
 * Created by PhpStorm.
 * User: Steven
 * Date: 2019-05-18
 * Time: 10:05 AM
 */

namespace Drupal\zendesk_webform\Plugin\WebformHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
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
 *
 * Took inspiration from the following packages:
 * - incomplete Zendesk webform handler port from Drupal 7: https://git.drupalcode.org/sandbox/hanoii-2910895
 * - package synchronizing Drupal forms and Zendesk forms: https://git.drupalcode.org/project/zendesk_tickets
 *
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
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {


        $logger_factory = $container->get('logger.factory');
        $config_factory = $container->get('config.factory');
        $entity_type_manager = $container->get('entity_type.manager');
        $webform_submission_conditions_validator = $container->get('webform_submission.conditions_validator');
        $webform_token_manager = $container->get('webform.token_manager');

        /**
         * @var LoggerChannelFactoryInterface $logger_factory
         * @var ConfigFactoryInterface $config_factory
         * @var EntityTypeManagerInterface $entity_type_manager
         * @var WebformSubmissionConditionsValidatorInterface $webform_submission_conditions_validator
         * @var WebformTokenManagerInterface $webform_token_manager
         */

        $static = new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $logger_factory,
            $config_factory,
            $entity_type_manager,
            $webform_submission_conditions_validator
        );

        $static->setTokenManager( $webform_token_manager );

        return $static;
    }

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

            // todo: look into attachments handling
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

        // get available email fields to use as requester email address
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

        // build form fields

        $form['requester'] = [
            '#type' => 'webform_select_other',
            '#title' => $this->t('Requester email address'),
            '#description' => $this->t('The user who requested this ticket. Select from available email fields, or specify an email address.'),
            '#default_value' => $this->configuration['requester'],
            '#options' => $options_email,
            '#required' => true
        ];

        $form['subject'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Subject'),
            '#description' => $this->t('The value of the subject field for this ticket'),
            '#default_value' => $this->configuration['subject'],
            '#required' => true
        ];

        $form['type'] = [
            '#type' => 'select',
            '#title' => $this->t('Ticket Type'),
            '#description' => $this->t('The type of this ticket. Possible values: "problem", "incident", "question" or "task".'),
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
            '#description' => $this->t('The urgency with which the ticket should be addressed. Possible values: "urgent", "high", "normal", "low".'),
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
            '#description' => $this->t('The state of the ticket. Possible values: "new", "open", "pending", "hold", "solved", "closed".'),
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
            '#description' => $this->t('The initial comment/message of the ticket.'),
            '#default_value' => $this->configuration['comment'],
            '#format' => 'full_html',
            '#required' => true
        ];

        // space separated tags
        $form['tags'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Ticket Tags'),
            '#description' => $this->t('The list of tags applied to this ticket.'),
            '#default_value' => $this->configuration['tags'],
            '#multiple' => true,
            '#required' => false
        ];

        $form['collaborators'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Ticket CCs'),
            '#description' => $this->t('Users to add as cc\'s when creating a ticket.'),
            '#default_value' => $this->configuration['collaborators'],
            '#multiple' => true,
            '#required' => false
        ];

        $form['custom_fields'] = [
            '#type' => 'webform_codemirror',
            '#mode' => 'yaml',
            '#title' => $this->t('Ticket Custom Fields'),
            '#description' => $this->t('Custom form fields for the ticket'),
            '#default_value' => $this->configuration['custom_fields'],
            '#required' => false
        ];

        // display link for token variables
        $form['token_link'] = $this->getTokenManager()->buildTreeLink();

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

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);

        $submission_value = $form_state->getValues();
        foreach($this->configuration as $key => $value){
            if(isset($submission_value[$key])){
                $this->configuration[$key] = $submission_value[$key];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE)
    {
        // run only for new submissions
        if (! $update) {

            // declare working variables
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

            // clean up tags
            $request['tags'] = $this->cleanTags( $request['tags'] );
            $request['collaborators'] = preg_split("/[^a-z0-9_\-@\.']+/i", $request['collaborators'] );

            // restructure comment array
            if(!isset($request['comment']['body'])){
                $comment = $request['comment'];
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

            // set external_id to connect zendesk ticket with submission ID
            $request['external_id'] = $webform_submission->id();

            // attempt to send request to create zendesk ticket
            try {
                $client = new ZendeskClient();
                $new_ticket = $client->tickets()->create($request);

                // add ticket ID to submission notes.
                if( $new_ticket ) {
                    $webform_submission->setElementData('notes', 'Ticket: ' . $new_ticket->ticket->id . PHP_EOL );
                }
            }
            catch( \Exception $e ){

                // Encode HTML entities to prevent broken markup from breaking the page.
                $message = nl2br(htmlentities($e->getMessage()));

                // Log error message.
                $this->getLogger()->error('@form webform submission to zendesk failed. @exception: @message. Click to edit @link.', [
                    '@exception' => get_class($e),
                    '@form' => $this->getWebform()->label(),
                    '@message' => $message,
                    'link' => $this->getWebform()->toLink($this->t('Edit'), 'handlers')->toString(),
                ]);
            }
        }
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary()
    {
        $markup = [];
        $configNames = array_keys($this->defaultConfiguration());
        $excluded_fields = ['comment','custom_fields'];

        // loop through fields to display an at-a-glance summary of settings
        foreach($configNames as $configName){
            if(! in_array($configName, $excluded_fields) ) {
                $markup[] = '<strong>' . $this->t($configName) . ': </strong>' . ($this->configuration[$configName]);
            }
        }

        return [
            '#theme' => 'markup',
            '#markup' => implode('<br>',$markup),
        ];
    }

    /**
     * Token manager setter
     * @param WebformTokenManagerInterface $token_manager
     */
    public function setTokenManager( WebformTokenManagerInterface $token_manager ){
        $this->token_manager = $token_manager;
    }

    /**
     * Token manager getter
     * @return WebformTokenManagerInterface
     */
    public function getTokenManager(){
        return $this->token_manager;
    }

    // formatting and condition helper functions

    /**
     * @param array $field
     * @return bool
     */
    protected function checkIsEmailField( array $field ){
        return in_array( $field['#type'], [ 'email', 'webform_email_confirm' ] );
    }

    /**
     * @param array $field
     * @return bool
     */
    protected function checkIsGroupingField( array $field ){
        return in_array( $field['#type'], [ 'webform_section' ] );
    }

    /**
     * @param string $text
     * @return string
     */
    protected function cleanTags( $text = '' ){
        return implode(' ',preg_split("/[^a-z0-9_]+/i",strtolower($text)));
    }
}