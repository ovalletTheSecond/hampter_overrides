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
            $success = parent::install()
                && $this->registerHook('displayHeader')
                && $this->installModuleOverrides();

            if (!$success) {
                $errors = [];
                if (!empty($this->_errors) && is_array($this->_errors)) {
                    $errors = $this->_errors;
                } elseif (method_exists($this, 'getErrors')) {
                    $errors = $this->getErrors();
                }

                $message = '[hampter_overrides] install failed';
                if (!empty($errors)) {
                    $message .= ': ' . implode(' | ', $errors);
                }

                PrestaShopLogger::addLog(
                    $message,
                    PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                    null,
                    'Module',
                    null,
                    true
                );

                return false;
            }

            PrestaShopLogger::addLog(
                '[hampter_overrides] install success',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
                null,
                'Module',
                null,
                true
            );

            return true;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                '[hampter_overrides] install failed: ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
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
        try {
            $overrideOk = $this->uninstallModuleOverrides();
            $coreOk = parent::uninstall();

            if (!$overrideOk || !$coreOk) {
                $errors = [];
                if (!empty($this->_errors) && is_array($this->_errors)) {
                    $errors = $this->_errors;
                } elseif (method_exists($this, 'getErrors')) {
                    $errors = $this->getErrors();
                }

                $message = '[hampter_overrides] uninstall failed';
                if (!empty($errors)) {
                    $message .= ': ' . implode(' | ', $errors);
                }

                PrestaShopLogger::addLog(
                    $message,
                    PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                    null,
                    'Module',
                    null,
                    true
                );

                return false;
            }

            PrestaShopLogger::addLog(
                '[hampter_overrides] uninstall success',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
                null,
                'Module',
                null,
                true
            );

            return true;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                '[hampter_overrides] uninstall failed: ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                'Module',
                null,
                true
            );

            return false;
        }
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
        $scripts .= '      if (typeof totPaypalSdkButtons === "undefined" || paypalShortcutState.buttonInitialized) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      var buttonContainer = document.getElementById("hampter-paypal-button");' . PHP_EOL;
        $scripts .= '      if (!buttonContainer) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      var selectedPaypal = document.querySelector(\'input[name="payment-option"][data-module-name="paypal"]:checked\');' . PHP_EOL;
        $scripts .= '      if (!selectedPaypal) {' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      if (buttonContainer.childNodes.length === 0) {' . PHP_EOL;
        $scripts .= '        totPaypalSdkButtons.Buttons({' . PHP_EOL;
        $scripts .= '          fundingSource: totPaypalSdkButtons.FUNDING.PAYPAL,' . PHP_EOL;
        $scripts .= '          style: styleSetting,' . PHP_EOL;
        $scripts .= '          createOrder: function () {' . PHP_EOL;
        $scripts .= '            return fetch(sc_init_url, { method: "POST", credentials: "same-origin" })' . PHP_EOL;
        $scripts .= '              .then(function (res) { return res.json(); })' . PHP_EOL;
        $scripts .= '              .then(function (data) { return data.id; });' . PHP_EOL;
        $scripts .= '          },' . PHP_EOL;
        $scripts .= '          onApprove: function (data) {' . PHP_EOL;
        $scripts .= '            window.location.href = scOrderUrl + "&token=" + encodeURIComponent(data.orderID) + "&PayerID=" + encodeURIComponent(data.payerID);' . PHP_EOL;
        $scripts .= '          }' . PHP_EOL;
        $scripts .= '        }).render("#hampter-paypal-button");' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      paypalShortcutState.buttonInitialized = true;' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function renderHampterFallbackButton() {' . PHP_EOL;
        $scripts .= '      var container = document.getElementById("hampter-paypal-button") || document.getElementById("paypal-buttons");' . PHP_EOL;
        $scripts .= '      if (!container) {' . PHP_EOL;
        $scripts .= '        container = document.createElement("div");' . PHP_EOL;
        $scripts .= '        container.id = "paypal-buttons";' . PHP_EOL;
        $scripts .= '        container.style.width = "300px";' . PHP_EOL;
        $scripts .= '        var ref = document.querySelector("[paypal-button-container]") || document.querySelector("[data-container-express-checkout]");' . PHP_EOL;
        $scripts .= '        if (ref) { ref.appendChild(container); }' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      if (container.dataset.hampterInitialized) { return; }' . PHP_EOL;
        $scripts .= '      container.innerHTML = "";' . PHP_EOL;
        $scripts .= '      totPaypalSdkButtons.Buttons({' . PHP_EOL;
        $scripts .= '        fundingSource: "paypal",' . PHP_EOL;
        $scripts .= '        style: styleSetting,' . PHP_EOL;
        $scripts .= '        createOrder: function () {' . PHP_EOL;
        $scripts .= '          return fetch(sc_init_url, { method: "POST", credentials: "same-origin" })' . PHP_EOL;
        $scripts .= '            .then(function (res) { return res.json(); })' . PHP_EOL;
        $scripts .= '            .then(function (data) { return data.id; });' . PHP_EOL;
        $scripts .= '        },' . PHP_EOL;
        $scripts .= '        onApprove: function (data) {' . PHP_EOL;
        $scripts .= '          window.location.href = scOrderUrl + "&token=" + encodeURIComponent(data.orderID) + "&PayerID=" + encodeURIComponent(data.payerID);' . PHP_EOL;
        $scripts .= '        },' . PHP_EOL;
        $scripts .= '        onError: function (err) { console.error("[hampter] PayPal button error", err); }' . PHP_EOL;
        $scripts .= '      }).render(container);' . PHP_EOL;
        $scripts .= '      container.dataset.hampterInitialized = "1";' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function waitPaypalIsLoaded() {' . PHP_EOL;
        $scripts .= '      if (typeof totPaypalSdkButtons === "undefined") {' . PHP_EOL;
        $scripts .= '        setTimeout(waitPaypalIsLoaded, 200);' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      if (document.querySelector("[paypal-button-container]") === null) {' . PHP_EOL;
        $scripts .= '        setTimeout(waitPaypalIsLoaded, 200);' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      if (typeof Shortcut !== "undefined" && typeof jQuery !== "undefined") {' . PHP_EOL;
        $scripts .= '        try { Shortcut.init(); Shortcut.initButton(); } catch (e) { renderHampterFallbackButton(); }' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      renderHampterFallbackButton();' . PHP_EOL;
        $scripts .= '      initPaypalShortcut();' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function renderHampterPayPal() {' . PHP_EOL;
        $scripts .= '      if (typeof totPaypalSdkButtons === "undefined") {' . PHP_EOL;
        $scripts .= '        console.error("[hampter] totPaypalSdkButtons not loaded");' . PHP_EOL;
        $scripts .= '        return;' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      var container = document.getElementById("paypal-buttons");' . PHP_EOL;
        $scripts .= '      if (!container) {' . PHP_EOL;
        $scripts .= '        container = document.createElement("div");' . PHP_EOL;
        $scripts .= '        container.id = "paypal-buttons";' . PHP_EOL;
        $scripts .= '        container.style.width = "300px";' . PHP_EOL;
        $scripts .= '        var ref = document.querySelector("[paypal-button-container]") || document.querySelector("[data-container-express-checkout]");' . PHP_EOL;
        $scripts .= '        if (ref) { ref.appendChild(container); }' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
        $scripts .= '      container.innerHTML = "";' . PHP_EOL;
        $scripts .= '      totPaypalSdkButtons.Buttons({' . PHP_EOL;
        $scripts .= '        fundingSource: "paypal",' . PHP_EOL;
        $scripts .= '        style: { label: "pay", height: 35 },' . PHP_EOL;
        $scripts .= '        createOrder: function() {' . PHP_EOL;
        $scripts .= '          return fetch(sc_init_url, { method: "POST", credentials: "same-origin" })' . PHP_EOL;
        $scripts .= '            .then(function(res) { return res.json(); })' . PHP_EOL;
        $scripts .= '            .then(function(data) { return data.id; });' . PHP_EOL;
        $scripts .= '        },' . PHP_EOL;
        $scripts .= '        onApprove: function(data) {' . PHP_EOL;
        $scripts .= '          window.location.href = scOrderUrl + "&token=" + encodeURIComponent(data.orderID) + "&PayerID=" + encodeURIComponent(data.payerID);' . PHP_EOL;
        $scripts .= '        },' . PHP_EOL;
        $scripts .= '        onError: function(err) {' . PHP_EOL;
        $scripts .= '          console.error("[hampter] PayPal button error", err);' . PHP_EOL;
        $scripts .= '        }' . PHP_EOL;
        $scripts .= '      }).render(container);' . PHP_EOL;
        $scripts .= '      console.log("[hampter] PayPal button rendered");' . PHP_EOL;
        $scripts .= '    }' . PHP_EOL;
        $scripts .= '    function hampterInitPaypal() {' . PHP_EOL;
        $scripts .= '      var radios = document.querySelectorAll(\'input[name="payment-option"]\');' . PHP_EOL;
        $scripts .= '      radios.forEach(function (radio) { radio.addEventListener(\'change\', hampterUpdatePaypalButtonVisibility); });' . PHP_EOL;
        $scripts .= '      hampterUpdatePaypalButtonVisibility();' . PHP_EOL;
        $scripts .= '      waitPaypalIsLoaded();' . PHP_EOL;
        $scripts .= '      document.querySelectorAll(\'input[name="payment-option"][data-module-name="paypal"]\').forEach(function (radio) {' . PHP_EOL;
        $scripts .= '        console.log("paypal_radio_found");' . PHP_EOL;
        $scripts .= '        radio.addEventListener(\'change\', function () {' . PHP_EOL;
        $scripts .= '          console.log("paypal_selected");' . PHP_EOL;
        $scripts .= '          if (radio.checked) { renderHampterPayPal(); }' . PHP_EOL;
        $scripts .= '        });' . PHP_EOL;
        $scripts .= '      });' . PHP_EOL;
        $scripts .= '      if (document.querySelector(\'input[name="payment-option"][data-module-name="paypal"]:checked\')) {' . PHP_EOL;
        $scripts .= '        console.log("paypal_preselected");' . PHP_EOL;
        $scripts .= '        renderHampterPayPal();' . PHP_EOL;
        $scripts .= '      }' . PHP_EOL;
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
        $sourceDir = $this->getLocalPath() . 'override';
        $targetDir = _PS_OVERRIDE_DIR_;

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
                if (is_dir($targetPath) && !$this->removeOverrideDirectory($targetPath)) {
                    $success = false;
                }
                continue;
            }

            if (is_file($targetPath) && !unlink($targetPath)) {
                $message = sprintf('[hampter_overrides] unable to remove file %s', $targetPath);
                $this->_errors[] = $message;
                PrestaShopLogger::addLog(
                    $message,
                    PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                    null,
                    'Module',
                    null,
                    true
                );
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove an override directory. If it is not empty, try to clean up
     * leftover PrestaShop index.php protection files before giving up.
     */
    protected function removeOverrideDirectory($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }

        // Try to remove the directory if it is already empty.
        if (@rmdir($dir)) {
            return true;
        }

        // Directory is not empty. Remove any stale index.php files PrestaShop
        // may have created inside module override directories, then retry.
        $entries = @scandir($dir);
        if ($entries === false) {
            $message = sprintf('[hampter_overrides] unable to scan directory %s', $dir);
            $this->_errors[] = $message;
            PrestaShopLogger::addLog(
                $message,
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                'Module',
                null,
                true
            );
            return false;
        }

        $hasRealFiles = false;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                // Nested directory we did not install (e.g. another module).
                // Leave it alone and consider the parent non-empty.
                $hasRealFiles = true;
                continue;
            }

            if ($entry === 'index.php' && $this->isPrestashopIndexFile($path)) {
                @unlink($path);
                continue;
            }

            // Any other file means the directory is still legitimately used.
            $hasRealFiles = true;
        }

        if ($hasRealFiles) {
            $message = sprintf('[hampter_overrides] directory %s is not empty, leaving it in place', $dir);
            PrestaShopLogger::addLog(
                $message,
                PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING,
                null,
                'Module',
                null,
                true
            );
            return true;
        }

        if (!@rmdir($dir)) {
            $message = sprintf('[hampter_overrides] unable to remove directory %s after cleanup', $dir);
            $this->_errors[] = $message;
            PrestaShopLogger::addLog(
                $message,
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                'Module',
                null,
                true
            );
            return false;
        }

        return true;
    }

    /**
     * Check whether a file is one of PrestaShop's automatic index.php
     * protection files (no useful content, just "Silence is golden").
     */
    protected function isPrestashopIndexFile($path)
    {
        if (!is_file($path)) {
            return false;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $short = trim($content);

        return $short === ''
            || stripos($short, 'Silence is golden') !== false
            || stripos($short, 'header("Expires")') !== false
            || stripos($short, 'index.php?controller=404') !== false;
    }
}
