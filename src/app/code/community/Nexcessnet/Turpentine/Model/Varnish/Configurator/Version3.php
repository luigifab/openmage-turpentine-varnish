<?php
/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Nexcessnet_Turpentine_Model_Varnish_Configurator_Version3
    extends Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {

    const VCL_TEMPLATE_FILE = 'version-3.vcl';
    const VCL_VERSION = '3';


    /**
     * Generate the Varnish 3.0-compatible VCL
     *
     * @param bool $doClean if true, VCL will be cleaned (whitespaces stripped, etc.)
     * @return string
     */
    public function generate($doClean = true) {
        // first, check if a custom template is set
        $customTemplate = $this->_getCustomTemplateFilename();
        if ($customTemplate) {
            $tplFile = $customTemplate;
        } else {
            $tplFile = $this->_getVclTemplateFilename(self::VCL_TEMPLATE_FILE);
        }
        $vcl = $this->_formatTemplate(file_get_contents($tplFile),
            $this->_getTemplateVars());
        return $doClean ? $this->_cleanVcl($vcl) : $vcl;
    }

    protected function _getAdvancedSessionValidation() {
        $validation = '';
        foreach ($this->_getAdvancedSessionValidationTargets() as $target) {
            $validation .= sprintf('hash_data(%s);'.PHP_EOL, $target);
        }
        return $validation;
    }

    /**
     * Build the list of template variables to apply to the VCL template
     *
     * @return array
     */
    protected function _getTemplateVars() {
        $vars = parent::_getTemplateVars();
        $vars['advanced_session_validation'] =
            $this->_getAdvancedSessionValidation();

        //dispatch event to allow other extensions to add custom vcl template variables
        Mage::dispatchEvent('turpentine_get_templatevars_after', array(
            'vars' => &$vars,
            'vcl_version'=> self::VCL_VERSION
        ));

        return $vars;
    }
}