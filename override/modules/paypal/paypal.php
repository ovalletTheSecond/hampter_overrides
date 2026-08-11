<?php
/**
 * theme_hampter override for the official PayPal module.
 *
 * The original module returns an empty payment-options list when the customer
 * is not logged in. This override removes that guard so PayPal is also
 * available during guest checkout.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'paypal/config_dev.php';
include_once _PS_MODULE_DIR_ . 'paypal/vendor/autoload.php';

use PaypalAddons\classes\AbstractMethodPaypal;
use PaypalAddons\classes\Constants\PaypalConfigurations;

class PayPalOverride extends PayPal
{
    public function hookPaymentOptions($params)
    {
        if (Module::isEnabled('braintreeofficial') && (int) Configuration::get('BRAINTREEOFFICIAL_ACTIVATE_PAYPAL')) {
            return [];
        }

        // Guest-checkout guard removed below.
        // Everything else mirrors the original PayPal::hookPaymentOptions()
        // so behaviour stays identical.

        $isoCountryDefault = Country::getIsoById((int) Configuration::get('PS_COUNTRY_DEFAULT'));
        $payments_options = [];
        $method = AbstractMethodPaypal::load();
        $bnplAvailabilityManager = $this->getBnplAvailabilityManager();
        $bnplOption = $this->getBnplOption();
        $venmoFunctionality = $this->initVenmoFunctionality();
        $sepaFunctionality = $this->initSepaFunctionality();

        switch ($this->paypal_method) {
            case 'EC':
                if ($method->isConfigured()) {
                    $paymentOptionsEc = $this->renderEcPaymentOptions($params);
                    $payments_options = array_merge($payments_options, $paymentOptionsEc);

                    if (Configuration::get('PAYPAL_API_CARD') && (in_array($isoCountryDefault, $this->countriesApiCartUnavailable) == false)) {
                        $payment_option = new PaymentOption();
                        $action_text = $this->l('Pay with debit or credit card');
                        $payment_option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo_card.png'));
                        $payment_option->setCallToActionText($action_text);
                        $payment_option->setModuleName('paypal_cb');
                        $payment_option->setAction($this->context->link->getModuleLink($this->name, 'ecInit', ['credit_card' => '1'], true));
                        $payment_option->setAdditionalInformation($this->context->smarty->fetch('module:paypal/views/templates/front/payment_infos_card.tpl'));
                        $payments_options[] = $payment_option;
                    }
                }
                break;

            case 'PPP':
                if ($method->isConfigured()) {
                    if ($this->isShortcutEnabled() && isset($this->context->cookie->paypal_pSc)) {
                        $payment_option = new PaymentOption();
                        $action_text = $this->l('Pay with paypal plus shortcut');
                        $payment_option->setCallToActionText($action_text);
                        $payment_option->setModuleName('paypal_plus_schortcut');
                        $payment_option->setAction($this->context->link->getModuleLink($this->name, 'pppValidation', ['short_cut' => '1', 'token' => $this->context->cookie->paypal_pSc], true));
                        $payments_options[] = $payment_option;
                    }

                    $payments_options[] = $this->buildPaypalWallet($params);
                }
                break;

            case 'MB':
                if (in_array($this->context->currency->iso_code, $this->currencyMB)) {
                    if (Configuration::get(PaypalConfigurations::MB_EC_ENABLED)) {
                        $paymentOptionsEc = $this->renderEcPaymentOptions($params);
                        $payments_options = array_merge($payments_options, $paymentOptionsEc);
                    }

                    if ($method->isConfigured() && (int) Configuration::get('PAYPAL_API_CARD') && false === in_array($isoCountryDefault, $this->countriesApiCartUnavailable) && false === $this->isBraintreeEnabled()) {
                        $payment_option = new PaymentOption();
                        $action_text = $this->l('Pay with credit or debit card');
                        $payment_option->setCallToActionText($action_text);
                        $payment_option->setModuleName('paypal_plus_mb');
                        try {
                            $this->context->smarty->assign('path', $this->_path);
                            $payment_option->setAdditionalInformation(
                                $this->context->smarty->fetch(
                                    'module:paypal/views/templates/front/payment_mb.tpl'
                                )
                            );
                        } catch (Exception $e) {
                            return [];
                        }
                        $payments_options[] = $payment_option;
                    }
                }
                break;
        }

        if ($method->isConfigured()) {
            if ($bnplOption->isEnable() && $bnplOption->displayOnPaymentStep() && $bnplAvailabilityManager->isEligibleCountryConfiguration() && $bnplAvailabilityManager->isEligibleContext()) {
                $payments_options[] = $this->buildBnplPaymentOption($params);
            }

            if ($venmoFunctionality->isAvailable() && $venmoFunctionality->isEnabled() && $venmoFunctionality->isEligibleContext($this->context)) {
                $payments_options[] = $this->buildVenmoPaymentOption($params);
            }

            if ($this->initAcdcFunctionality()->isAvailable() && $this->initAcdcFunctionality()->isEnabled() && false === $this->isBraintreeEnabled()) {
                $payments_options[] = $this->buildAcdcPaymentOption($params);
            }

            if ($this->paypal_method == 'PPP') {
                if ($sepaFunctionality->isEnabled() && $sepaFunctionality->isAvailable()) {
                    $payments_options[] = $this->renderSepaOption($params);
                }

                if ($this->getWebhookOption()->isAvailable() && $this->getWebhookOption()->isEnable()) {
                    if ($this->initPuiFunctionality()->isAvailable(false)) {
                        if ($this->initPuiFunctionality()->isEnabled()) {
                            if ($this->initPuiFunctionality()->isEligibleContext($this->context)) {
                                $payments_options[] = $this->renderPuiOption($params);
                            }
                        }
                    }
                }
            }
        }

        if ($method->isSandbox() && false === empty($payments_options)) {
            foreach ($payments_options as $paymentOption) {
                if ($paymentOption instanceof PaymentOption) {
                    $additionalInformantion = $this->displayAlert(
                        $this->l('Sandbox mode: all transactions will be fictitious.'),
                        'info',
                        false
                    );
                    $additionalInformantion .= $paymentOption->getAdditionalInformation();
                    $paymentOption->setAdditionalInformation($additionalInformantion);
                }
            }
        }

        return $payments_options;
    }
}
