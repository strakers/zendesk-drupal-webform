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
use Drupal\file\Entity\File;


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
            'assignee_id' => '',
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


        $assignees = [];

        // get available assignees from zendesk
        try {
            // initiate api client
            $client = new ZendeskClient();

            // get list of all users who are either agents or admins
            $response_agents = $client->users()->findAll([ 'role' => 'agent' ]);
            $response_admins = $client->users()->findAll([ 'role' => 'admin' ]);
            $users = array_merge( $response_agents->users, $response_admins->users );

            // store found agents
            foreach($users as $user){
                $assignees[ $user->id ] = $user->name;
            }

            // order agents by name
            asort($assignees);
        }
        catch( \Exception $e ){

            // Encode HTML entities to prevent broken markup from breaking the page.
            $message = nl2br(htmlentities($e->getMessage()));

            // Log error message.
            $this->getLogger()->error('Retrieval of assignees for @form webform Zendesk handler failed. @exception: @message. Click to edit @link.', [
                '@exception' => get_class($e),
                '@form' => $this->getWebform()->label(),
                '@message' => $message,
                'link' => $this->getWebform()->toLink($this->t('Edit'), 'handlers')->toString(),
            ]);
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

        $form['comment'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Ticket Body'),
            '#description' => $this->t('The initial comment/message of the ticket.'),
            '#default_value' => $this->configuration['comment'],
            '#format' => 'full_html',
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

        // space separated tags
        $form['tags'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Ticket Tags'),
            '#description' => $this->t('The list of tags applied to this ticket.'),
            '#default_value' => $this->configuration['tags'],
            '#multiple' => true,
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

        // prep assignees field
        // if found assignees from Zendesk, populate dropdown.
        // otherwise provide field to specify assignee ID
        $form['assignee_id'] = [
            '#title' => $this->t('Ticket Assignee'),
            '#description' => $this->t('The id of the intended assignee'),
            '#default_value' => $this->configuration['assignee_id'],
            '#required' => false
        ];
        if(! empty($assignees) ){
            $form['assignee_id']['#type'] = 'webform_select_other';
            $form['assignee_id']['#options'] = ['' => '-- none --'] + $assignees;
            $form['assignee_id']['#description'] = $this->t('The email address the assignee');
        }
        else {
            $form['assignee_id']['#type'] = 'textfield';
            $form['assignee_id']['#attribute'] = [
                'type' => 'number'
            ];
        }

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
            $submission_fields = $webform_submission->toArray(TRUE);
            $configuration = $this->getTokenManager()->replace($this->configuration, $webform_submission);

            // Allow for either values coming from other fields or static/tokens
            foreach ($this->defaultConfigurationNames() as $field) {
                $request[$field] = $configuration[$field];
                if (!empty($submission_fields['data'][$configuration[$field]])) {
                    $request[$field] = $submission_fields['data'][$configuration[$field]];
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

            // convert custom fields format from [key:data} to [id:key,value:data] for Zendesk field referencing
            $custom_fields = Yaml::decode($request['custom_fields']);
            $request['custom_fields'] = [];
            foreach($custom_fields as $key => $value){
                $request['custom_fields'][] = [
                    'id' => $key,
                    'value' => $value
                ];
            }

            // clean up tags
            $request['tags'] = $this->convertTags( $request['tags'] );
            $request['collaborators'] = preg_split("/[^a-z0-9_\-@\.']+/i", $request['collaborators'] );

            // set external_id to connect zendesk ticket with submission ID
            $request['external_id'] = $webform_submission->id();

            // get list of all webform fields with a file field type
            $file_fields = $this->getWebformFieldsWithFiles();

            // attempt to send request to create zendesk ticket
            try {

                // initiate api client
                $client = new ZendeskClient();

                // Checks for files in submission values and uploads them if found
                foreach($submission_fields['data'] as $key => $submission_field){
                    if( in_array($key, $file_fields) && !empty($submission_field) ){

                        // get file from id for upload
                        $file = File::load($submission_field[0]);

                        // add uploads key to Zendesk comment, if not already present
                        if( $file && !array_key_exists('uploads', $request['comment']) ){
                            $request['comment']['uploads'] = [];
                        }

                        // upload file and get response
                        $attachment = $client->attachments()->upload([
                            'file' => $file->getFileUri(),
                            'type' => $file->getMimeType(),
                            'name' => $file->getFileName(),
                        ]);

                        // add upload token to comment
                        if( $attachment && isset($attachment->upload->token) ){
                            $request['comment']['uploads'][] = $attachment->upload->token;
                        }
                    }
                }

                // create ticket
                $new_ticket = $client->tickets()->create($request);

                // add ticket ID to submission notes.
                // https://www.drupal.org/docs/8/modules/webform/webform-cookbook/how-to-programmatically-create-and-update-a-submission
                // TODO:
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

    /**
     * @return array
     */
    protected function getWebformFieldsWithFiles(){
        return $this->getWebform()->getElementsManagedFiles();
    }

    /**
     * @param string $text
     * @return string
     */
    protected function convertTags( $text = '' ){
        return strtolower(implode(' ',preg_split("/[^a-z0-9_]+/i",$text)));
    }
}