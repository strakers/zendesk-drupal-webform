# strakez/zendesk-webform
Add a webform handler to create Zendesk tickets from Drupal webform submissions.

## Installation
With composer/installers in effect, Drupal packages are installed to their own specified paths. However the default 
configs for Drupal packages don't include custom modules. We'll need to add one:

Add the following to the `extra.installer-paths` object:
```
"web/modules/custom/{$name}": ["type:drupal-custom-module"],
```

Once this is complete, add the following object to the `repositories` array.
_(This step will only be necessary until this package is registered with packagist.)_
```json
{
  "type": "vcs",
  "url": "https://github.com/strakers/zendesk-drupal-webform"
}
```

Finally, use composer to require this package:
```bash
composer require strakez/zendesk-webform
```

## Setup

### 1) Get Zendesk API Key

Please see the following link for instructions on [retrieving your Zendesk API Key](https://support.zendesk.com/hc/en-us/articles/226022787-Generating-a-new-API-token-).

### 2) Activate Module

- Activate the Zendesk Webform module from your site's Extend page.
- Navigate to the configuration page, and fill out the required fields. (Note: your API key will be used here.)

### 3) Add Handler

- Navigate to the desired webform's [Settings > Email/Handlers] page, and click Add Handler.
- Specify settings for the Zendesk ticket to be created

### 4) Test

It is recommend to submit a test submission to confirm your settings. If the ticket is created in Zendesk as desired, 
congrats! You've successfully setup up the handler integration.