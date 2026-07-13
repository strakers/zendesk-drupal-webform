# strakez/zendesk-webform

Add a webform handler to create Zendesk tickets from Drupal webform submissions.

## Installation

Then, for Drupal 10/11, run the following command in your terminal to require this package:

```bash
composer require strakez/zendesk-webform
```

For older Drupal versions, earlier releases retain support:

```bash
composer require strakez/zendesk-webform:^2.0  # Drupal 9
composer require strakez/zendesk-webform:^1.1  # Drupal 8
```

## Setup

### 1) Get a Zendesk API Key

Please see the following link for instructions on [retrieving your Zendesk API Key](https://support.zendesk.com/hc/en-us/articles/226022787-Generating-a-new-API-token-).

### 2) Activate the Module

- Activate the Zendesk Webform module from your site's Extend page.

### 4) Configure the Zendesk Connection Settings

- Navigate to the configuration page (found under **_Configuration -> System -> Zendesk Integration Form_**), and fill out the required fields. (Note: your API key will be used here.)

### 3) Add a Zendesk Handler to a Webform

- Navigate to the desired webform's **_Settings -> Email/Handlers_** page, and click **Add Handler**.
- Specify settings for the Zendesk ticket to be created.

### 4) Test

It is recommend to submit a test submission to confirm your settings. If the ticket is created in Zendesk as desired,
congrats! You've successfully setup up the handler integration.

## Additional Notes

### Store Zendesk Ticket ID

This module can help to keep track of the Zendesk Ticket ID directly on each submission. You'll need to create a hidden field when building the form, and then set it as the Zendesk Ticket ID Field in the handler configuration form.

### Auto-delete Webform Submissions

Alternatively, you can configure this module to automatically delete the webform submission. This would usually be used in situations where security is a factor, or if there is no need to retain the submission records. Please note the following:

- Ticket deletion occurs _only_ after successful Zendesk ticket creation. If there are any errors during Zendesk ticket creation, the webform submission will not be deleted.
- The deletion of webform submissions is permanent and cannot be undone.

## Local Development

This repo includes a [ddev](https://ddev.com) environment built on the [ddev/ddev-drupal-contrib](https://github.com/ddev/ddev-drupal-contrib) add-on, which scaffolds a throwaway Drupal site around this module so you can develop and test it in isolation.

```bash
ddev start
ddev poser              # composer install: scaffolds Drupal core + this module's dependencies
ddev symlink-project    # symlinks this repo into web/modules/custom/zendesk_webform (runs automatically on `ddev start` after the first `ddev poser`)
ddev drush site-install standard -y
ddev drush en webform zendesk_webform -y
```

Your test site is then available at the URL printed by `ddev describe`.

Other useful commands provided by the add-on:

- `ddev phpunit` — run PHPUnit tests
- `ddev phpcs` / `ddev phpcbf` — check/fix Drupal coding standards
- `ddev phpstan` — static analysis
- `ddev core-version ^11` — switch the scaffolded site to a different core version

See the [add-on README](https://github.com/ddev/ddev-drupal-contrib) for more details.
