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
}
