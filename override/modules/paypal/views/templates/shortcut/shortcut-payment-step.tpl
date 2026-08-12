{**
 * theme_hampter override for PayPal shortcut on payment step.
 *
 * Problem: the PayPal SDK cannot render its button while
 * [paypal-button-container] is hidden with display:none (which the module
 * does until the PayPal radio is checked). We keep the container technically
 * visible so the SDK can render, then toggle visibility with visibility:hidden
 * so no layout jump occurs when PayPal is not selected.
 *
 * Scripts (SDK + shortcut.js + init) are loaded via hookHeader by the
 * PayPalOverride class so they execute even though this markup is injected
 * through innerHTML as additionalInformation.
 *}

<!-- Start shortcut. Module Paypal -->
  <style>
    [data-container-express-checkout] {
      margin: 10px 0;
      width: 100%;
    }

    @media (max-width: 480px) {
      [paypal-mark-container] {
        display: none !important;
      }
    }

    /* Keep container visible for SDK render, hide with visibility only */
    [paypal-button-container] {
      visibility: visible !important;
      min-height: 35px;
    }

    [paypal-button-container].is-hidden {
      visibility: hidden;
      height: 0;
      min-height: 0;
      overflow: hidden;
      margin: 0;
    }
  </style>

  <div data-container-express-checkout data-paypal-source-page="payment-step">
    <form data-paypal-payment-form-cart class="paypal_payment_form" action="{$action_url|escape:'htmlall':'UTF-8'}" method="post" data-ajax="false">
      <input type="hidden" name="express_checkout" value="{$PayPal_payment_type|escape:'htmlall':'UTF-8'}"/>
      <input type="hidden" name="current_shop_url" data-paypal-url-page value="" />
      <input type="hidden" id="source_page" name="source_page" value="cart">
      <input type="hidden" name="isAddAddress" value="1">
    </form>
    <div paypal-button-container></div>

    <div style="display: none" class="alert alert-danger" paypal-ec-wrong-button-message>
      <div>{l s='Please click on the \'Pay with PayPal\' button' mod='paypal'}</div>
    </div>
  </div>
  <div class="clearfix"></div>
<!-- End shortcut. Module Paypal -->
