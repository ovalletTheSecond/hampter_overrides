<?php
/**
 * Hampter Theme Overrides
 *
 * This module ships the class overrides required by the theme_hampter
 * checkout experience. Installing the module copies the overrides into
 * /override/; uninstalling removes them again.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hampter_Overrides extends Module
{
    public function __construct()
    {
        $this->name = 'hampter_overrides';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'HampterShop';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Hampter Theme Overrides', [], 'Modules.Hampteroverrides.Admin');
        $this->description = $this->trans('Required class overrides for theme_hampter.', [], 'Modules.Hampteroverrides.Admin');
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        try {
            return parent::install()
                && $this->registerHook('displayHeader')
                && $this->installModuleOverrides();
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                '[hampter_overrides] install failed: ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                'Module',
                null,
                true
            );

            PrestaShopLogger::addLog(
                '[hampter_overrides] install OK',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFO,
                null,
                'Module',
                null,
                true
            );
            return false;
        }
    }

    public function uninstall()
    {
        return $this->uninstallModuleOverrides()
            && parent::uninstall();
    }

    /**
     * Load PayPal SDK / shortcut.js on the order page so the PayPal button can
     * render. The PayPal module normally injects these scripts through the
     * shortcut template (additionalInformation), but scripts inserted via
     * innerHTML are not executed by browsers.
     */
    public function hookDisplayHeader($params)
    {
        if (Tools::getValue('controller') != 'order') {
            return '';
        }

        if (!Module::isEnabled('paypal')) {
            return '';
        }

        // Make sure the PayPal override is loaded so we can use its method.
        if (is_file(_PS_OVERRIDE_DIR_ . 'modules/paypal/paypal.php')) {
            require_once _PS_OVERRIDE_DIR_ . 'modules/paypal/paypal.php';
        }

        $paypal = Module::getInstanceByName('paypal');
        if (!$paypal) {
            return '';
        }

        $method = \PaypalAddons\classes\AbstractMethodPaypal::load($paypal->paypal_method);
        if (!$method->isConfigured()) {
            return '';
        }

        // Only load if PayPal is actually registered on paymentOptions.
        $active = false;
        $modules = Hook::getHookModuleExecList('paymentOptions');
        if (!empty($modules)) {
            foreach ($modules as $module) {
                if ($module['module'] == 'paypal') {
                    $active = true;
                    break;
                }
            }
        }
        if (!$active) {
            return '';
        }

        $sdkUrl = $method->getUrlJsSdkLib(['components' => 'buttons,marks']);
        $shortcutUrl = __PS_BASE_URI__ . 'modules/paypal/views/js/shortcut.js?v=' . $paypal->version;
        $scInitUrl = $this->context->link->getModuleLink('paypal', 'ScInit', [], true);
        $scOrderUrl = $method->getReturnUrl();
        $moveButtonAtEnd = (int) Configuration::get('PAYPAL_MOVE_BUTTON_AT_END');
        $styleSetting = json_encode(['label' => 'pay', 'height' => 35]);

        $scripts = '<style>' . PHP_EOL;
        $scripts .= '  #payment-confirmation.paypal-selected { display: none !important; }' . PHP_EOL;
        $scripts .= '</style>' . PHP_EOL;
        $scripts .= '<script>' . PHP_EOL;
        $scripts .= '  var sc_init_url = ' . json_encode($scInitUrl) . ';' . PHP_EOL;
        $scripts .= '  var scOrderUrl = ' . json_encode($scOrderUrl) . ';' . PHP_EOL;
        $scripts .= '  var styleSetting = ' . $styleSetting . ';' . PHP_EOL;
        $scripts .= '  var PAYPAL_MOVE_BUTTON_AT_END = ' . $moveButtonAtEnd . ';' . PHP_EOL;
        $scripts .= '  window.prestashop = window.prestashop || {};' . PHP_EOL;
        $scripts .= '  window.prestashop._events = window.prestashop._events || {};' . PHP_EOL;
        $scripts .= '  if (typeof window.prestashop.on !== "function") {' . PHP_EOL;
        $scripts .= '    window.prestashop.on = function (event, callback) {' . PHP_EOL;
        $scripts .= '      if (!window.prestashop._events[event]) { window.prestashop._events[event] = []; }' . PHP_EOL;
        $scripts .= '      window.prestashop._events[event].push(callback);' . PHP_EOL;
        $scripts .= '    };' . PHP_EOL;
        $scripts .= '  }' . PHP_EOL;
        $scripts .= '  if (typeof window.prestashop.emit !== "function") {' . PHP_EOL;
        $scripts .= '    window.prestashop.emit = function (event, data) {' . PHP_EOL;
        $scripts .= '      if (window.prestashop._events[event]) {' . PHP_EOL;
        $scripts .= '        window.prestashop._events[event].forEach(function (cb) { cb(data); });' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '    };' . PHP_EOL;
        $scripts .= '  }' . PHP_EOL;
        $scripts .= '</script>' . PHP_EOL;
        $scripts .= '<script src="' . $sdkUrl . '" data-namespace="totPaypalSdkButtons"></script>' . PHP_EOL;
        $scripts .= '<script src="' . $shortcutUrl . '"></script>' . PHP_EOL;
        $scripts .= '<script>' . PHP_EOL;
        $scripts .= '  (function () {' . PHP_EOL;
        $scripts .= '    function hampterUpdatePaypalButtonVisibility() {' . PHP_EOL;
        $scripts .= '      var paypalRadios = document.querySelectorAll(\'input[name="payment-option"][data-module-name="paypal"]\');' . PHP_EOL;
        $scripts .= '      var containers = document.querySelectorAll(\'[paypal-button-container]\');' . PHP_EOL;
        $scripts .= '      var confirmButton = document.getElementById(\'payment-confirmation__button\');' . PHP_EOL;
        $scripts .= '      var confirmWrapper = document.getElementById(\'payment-confirmation\');' . PHP_EOL;
        $scripts .= '      var isChecked = false;' . PHP_EOL;
        $scripts .= '      paypalRadios.forEach(function (radio) { if (radio.checked) { isChecked = true; } });' . PHP_EOL;
        $scripts .= '      containers.forEach(function (container) {' . PHP_EOL;
        $scripts .= '        if (isChecked) { container.classList.remove(\'is-hidden\'); } else { container.classList.add(\'is-hidden\'); }' . PHP_EOL;
        $scripts .= '      });' . PHP_EOL;
        $scripts .= '      if (confirmButton) { confirmButton.disabled = isChecked; confirmButton.classList.toggle(\'disabled\', isChecked); }' . PHP_EOL;
        $scripts .= '      if (confirmWrapper) { confirmWrapper.classList.toggle(\'paypal-selected\', isChecked); }' . PHP_EOL;
        $scripts .= '      var wrongButtonMessages = document.querySelectorAll(\'[paypal-ec-wrong-button-message]\');' . PHP_EOL;
        $scripts .= '      wrongButtonMessages.forEach(function (msg) { msg.style.display = isChecked ? \'block\' : \'none\'; });' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    var paypalShortcutState = { buttonInitialized: false };' . PHP_EOL;
        $scripts .= '    function initPaypalShortcut() {' . PHP_EOL;
        $scripts .= '      if (typeof Shortcut === "undefined" || paypalShortcutState.buttonInitialized) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      var buttonContainer = document.querySelector("[paypal-button-container]");' . PHP_EOL;
        $scripts .= '      if (!buttonContainer) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      Shortcut.init();' . PHP_EOL;
        $scripts .= '      if (typeof PAYPAL_MOVE_BUTTON_AT_END != "undefined") { Shortcut.isMoveButtonAtEnd = PAYPAL_MOVE_BUTTON_AT_END; }' . PHP_EOL;
        $scripts .= '      var selectedPaypal = document.querySelector(\'input[name="payment-option"][data-module-name="paypal"]:checked\');' . PHP_EOL;
        $scripts .= '      if (!selectedPaypal) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      Shortcut.initButton();' . PHP_EOL;
        $scripts .= '      paypalShortcutState.buttonInitialized = true;' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function waitPaypalIsLoaded() {' . PHP_EOL;
        $scripts .= '      if (typeof totPaypalSdkButtons === "undefined" || typeof Shortcut === "undefined") {' . PHP_EOL;
        $scripts .= '        setTimeout(waitPaypalIsLoaded, 200);' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      if (document.querySelector("[paypal-button-container]") === null) {' . PHP_EOL;
        $scripts .= '        setTimeout(waitPaypalIsLoaded, 200);' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      initPaypalShortcut();' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function hampterInitPaypal() {' . PHP_EOL;
        $scripts .= '      var radios = document.querySelectorAll(\'input[name="payment-option"]\');' . PHP_EOL;
        $scripts .= '      radios.forEach(function (radio) { radio.addEventListener(\'change\', hampterUpdatePaypalButtonVisibility); });' . PHP_EOL;
        $scripts .= '      hampterUpdatePaypalButtonVisibility();' . PHP_EOL;
        $scripts .= '      waitPaypalIsLoaded();' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    if (document.readyState === "loading") {' . PHP_EOL;
        $scripts .= '      document.addEventListener("DOMContentLoaded", hampterInitPaypal);' . PHP_EOL;
        $scripts .= '    } else {' . PHP_EOL;
        $scripts .= '      hampterInitPaypal();' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '  })();' . PHP_EOL;
        $scripts .= '</script>' . PHP_EOL;

        return $scripts;
    }

    /**
     * Copy module overrides that PrestaShop's built-in installer does not
     * handle automatically (e.g. overrides of other modules).
     */
    protected function installModuleOverrides()
    {
        $sourceDir = $this->getLocalPath() . 'override/modules';
        $targetDir = _PS_OVERRIDE_DIR_ . 'modules';

        if (!is_dir($sourceDir)) {
            return true;
        }

        $success = true;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($file->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                    $this->_errors[] = sprintf('Unable to create directory %s', $targetPath);
                    $success = false;
                }
                continue;
            }

            if (!copy($file->getPathname(), $targetPath)) {
                $this->_errors[] = sprintf('Unable to copy override %s to %s', $file->getPathname(), $targetPath);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove module overrides installed by this module.
     */
    protected function uninstallModuleOverrides()
    {
        $sourceDir = $this->getLocalPath() . 'override/modules';
        $targetDir = _PS_OVERRIDE_DIR_ . 'modules';

        if (!is_dir($sourceDir) || !is_dir($targetDir)) {
            return true;
        }

        $success = true;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($file->isDir()) {
                if (is_dir($targetPath) && !rmdir($targetPath)) {
                    $success = false;
                }
                continue;
            }

            if (is_file($targetPath) && !unlink($targetPath)) {
                $success = false;
            }
        }

        return $success;
    }
}
