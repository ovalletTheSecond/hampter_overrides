<?php
/**
 * theme_hampter override — makes marketing/consent fields optional.
 *
 * The checkout templates hide optin/newsletter/customer_privacy/psgdpr
 * behind hidden inputs with value 0. The core formatter (and modules)
 * mark those fields as required, which causes registration to fail
 * silently and redirect back to the form. This override removes those
 * fields from the registration form entirely.
 */

class CustomerFormatter extends CustomerFormatterCore
{
    public function getFormat()
    {
        $format = parent::getFormat();

        // Remove marketing and consent fields from the registration form.
        // The theme hides them anyway; keeping them causes module-level
        // validators to reject submissions when they are unchecked.
        // Module-added fields are keyed as "moduleName_fieldName", so we
        // remove any key containing those names.
        $consentNames = ['optin', 'newsletter', 'customer_privacy', 'psgdpr'];

        foreach ($format as $key => $field) {
            foreach ($consentNames as $name) {
                if (stripos($key, $name) !== false) {
                    unset($format[$key]);
                    break;
                }
            }
        }

        return $format;
    }
}
