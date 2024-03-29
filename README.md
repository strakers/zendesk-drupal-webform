# strakez/zendesk-webform
Add a webform handler to create Zendesk tickets from Drupal webform submissions.

## Installation
With [composer/installers](https://github.com/composer/installers) in effect, Drupal packages are installed to their own specified paths. However the default 
configs for Drupal packages don't include custom modules. We'll need to add one:

If not already present, add the following to the `extra.installer-paths` object in your composer file:
```text
"web/modules/custom/{$name}": ["type:drupal-custom-module"],
```

Then, for Drupal 9, run the following command in your terminal to require this package:
```bash
composer require strakez/zendesk-webform
```

For Drupal 8, version `v1.1.0` retains support:
```bash
composer require strakez/zendesk-webform:^1.1
```


## Setup

### 1) Get a Zendesk API Key

Please see the following link for instructions on [retrieving your Zendesk API Key](https://support.zendesk.com/hc/en-us/articles/226022787-Generating-a-new-API-token-).

### 2) Activate the Module

- Activate the Zendesk Webform module from your site's Extend page.

### 4) Configure the Zendesk Connection Settings
- Navigate to the configuration page (found under ***Configuration -> System -> Zendesk Integration Form***), and fill out the required fields. (Note: your API key will be used here.)

### 3) Add a Zendesk Handler to a Webform

- Navigate to the desired webform's ***Settings -> Email/Handlers*** page, and click **Add Handler**.
- Specify settings for the Zendesk ticket to be created.

### 4) Test

It is recommend to submit a test submission to confirm your settings. If the ticket is created in Zendesk as desired, 
congrats! You've successfully setup up the handler integration.

## Additional Notes

### Store Zendesk Ticket ID

This module can help to keep track of the Zendesk Ticket ID directly on each submission. You'll need to create a hidden field when building the form, and then set it as the Zendesk Ticket ID Field in the handler configuration form.

### Auto-delete Webform Submissions

Alternatively, you can configure this module to automatically delete the webform submission. This would usually be used in situations where security is a factor, or if there is no need to retain the submission records. Please note the following:

- Ticket deletion occurs *only* after successful Zendesk ticket creation. If there are any errors during Zendesk ticket creation, the webform submission will not be deleted.
- The deletion of webform submissions is permanent and cannot be undone.