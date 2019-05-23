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
