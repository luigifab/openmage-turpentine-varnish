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

class Nexcessnet_Turpentine_Varnish_ManagementController
    extends Mage_Adminhtml_Controller_Action {

    /**
     * Management index action, displays buttons/forms for config and cache
     * management
     *
     * @return null
     */
    public function indexAction() {
        $this->_title($this->__('System'))
            ->_title(Mage::helper('turpentine')->__('Varnish Management'));
        $this->loadLayout()
            ->_setActiveMenu('system/turpentine')
            ->_addContent($this->getLayout()
                ->createBlock('turpentine/management'))
            ->renderLayout();
    }

    /**
     * Full flush action, flushes all Magento URLs in Varnish cache
     *
     * @return null
     */
    public function flushAllAction() {
        Mage::dispatchEvent('turpentine_varnish_flush_all');
        $result = Mage::getModel('turpentine/varnish_admin')->flushAll();
        foreach ($result as $name => $value) {
            if ($value === true) {
                $this->_getSession()
                    ->addSuccess(Mage::helper('turpentine/data')
                        ->__('Flushed Varnish cache for: ').$name);
            } else {
                $this->_getSession()
                    ->addError(Mage::helper('turpentine/data')
                        ->__('Error flushing Varnish cache on: ').$name);
            }
        }
        $this->_redirect('*/cache');
    }

    /**
     * Partial flush action, flushes Magento URLs matching "pattern" in POST
     * data
     *
     * @return null
     */
    public function flushPartialAction() {
        $postData = $this->getRequest()->getPost();
        if ( ! isset($postData['pattern'])) {
            $this->_getSession()->addError($this->__('Missing URL post data'));
        } else {
            $pattern = $postData['pattern'];
            Mage::dispatchEvent('turpentine_varnish_flush_partial',
                ['pattern' => $pattern]);
            $result = Mage::getModel('turpentine/varnish_admin')
                ->flushUrl($pattern);
            foreach ($result as $name => $value) {
                if ($value === true) {
                    $this->_getSession()
                        ->addSuccess(Mage::helper('turpentine/data')
                            ->__('Flushed matching URLs for: ').$name);
                } else {
                    $this->_getSession()
                        ->addError(Mage::helper('turpentine/data')
                            ->__('Error flushing matching URLs on: ').$name);
                }
            }
        }
        $this->_redirect('*/cache');
    }

    /**
     * Flush objects by content type (ctype in POST)
     *
     * @return null
     */
    public function flushContentTypeAction() {
        $postData = $this->getRequest()->getPost();
        if ( ! isset($postData['ctype'])) {
            $this->_getSession()->addError($this->__('Missing URL post data'));
        } else {
            $ctype = $postData['ctype'];
            Mage::dispatchEvent('turpentine_varnish_flush_content_type',
                ['ctype' => $ctype]);
            $result = Mage::getModel('turpentine/varnish_admin')
                ->flushContentType($ctype);
            foreach ($result as $name => $value) {
                if ($value === true) {
                    $this->_getSession()
                        ->addSuccess(Mage::helper('turpentine/data')
                            ->__('Flushed matching content-types for: ').$name);
                } else {
                    $this->_getSession()
                        ->addError(Mage::helper('turpentine/data')
                            ->__('Error flushing matching content-types on: ').$name);
                }
            }
        }
        $this->_redirect('*/cache');
    }

    /**
     * Load the current VCL in varnish and activate it
     *
     * @return null
     */
    public function applyConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_apply_config');
        $helper = Mage::helper('turpentine');
        $result = Mage::getModel('turpentine/varnish_admin')->applyConfig($helper
            ->shouldStripVclWhitespace('apply')
        );
        foreach ($result as $name => $value) {
            if ($value === true) {
                $this->_getSession()
                    ->addSuccess($helper
                        ->__('VCL successfully applied to '.$name));
            } else {
                $this->_getSession()
                    ->addError($helper
                        ->__(sprintf('Failed to apply the VCL to %s: %s',
                            $name, $value)));
            }
        }
        $this->_redirect('*/cache');
    }

    /**
     * Save the config to the configured file action
     *
     * @return null
     */
    public function saveConfigAction() {
        $cfgr = Mage::getModel('turpentine/varnish_admin')->getConfigurator();
        if (is_null($cfgr)) {
            $this->_getSession()->addError(
                $this->__('Failed to load configurator') );
        } else {
            Mage::dispatchEvent('turpentine_varnish_save_config',
                ['cfgr' => $cfgr]);
            $result = $cfgr->save($cfgr->generate(
                Mage::helper('turpentine')->shouldStripVclWhitespace('save') ));
            if ($result[0]) {
                $this->_getSession()
                    ->addSuccess(Mage::helper('turpentine')
                        ->__('The VCL file has been saved.'));
            } else {
                $this->_getSession()
                    ->addError(Mage::helper('turpentine')
                        ->__('Failed to save the VCL file: '.$result[1]['message']));
            }
        }
        $this->_redirect('*/cache');
    }

    /**
     * Present the generated config for download
     *
     * @return $this
     */
    public function getConfigAction() {
        $cfgr = Mage::getModel('turpentine/varnish_admin')
            ->getConfigurator();
        if (is_null($cfgr)) {
            $this->_getSession()->addError($this->__('Failed to load configurator'));
            $this->_redirect('*/cache');
        } else {
            $vcl = $cfgr->generate(
                Mage::helper('turpentine')->shouldStripVclWhitespace('download') );
            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'text/plain', true)
                ->setHeader('Content-Length', strlen($vcl))
                ->setHeader('Content-Disposition',
                    'attachment; filename=default.vcl')
                ->setBody($vcl);
            return $this;
        }
    }

    /**
     * Activate or deactivate the Varnish bypass
     *
     * @return void
     */
    public function switchNavigationAction() {
        $type = $this->getRequest()->get('type');
        if (is_null($type)) {
            $this->_redirect('noRoute');
            return;
        }

        $cookieName     = Mage::helper('turpentine')->getBypassCookieName();
        $cookieModel    = Mage::getModel('core/cookie');
        $adminSession   = Mage::getSingleton('adminhtml/session');

        switch ($type) {
            case 'default':
                $cookieModel->set(
                    $cookieName,
                    Mage::helper('turpentine/varnish')->getSecretHandshake(),
                    null, // period
                    null, // path
                    null, // domain
                    false, // secure
                    true ); // httponly
                $adminSession->addSuccess(Mage::helper('turpentine/data')
                    ->__('The Varnish bypass cookie has been successfully added.'));
            break;

            case 'varnish':
                $cookieModel->delete($cookieName);
                $adminSession->addSuccess(Mage::helper('turpentine/data')
                    ->__('The Varnish bypass cookie has been successfully removed.'));
            break;

            default:
                $adminSession->addError(Mage::helper('turpentine/data')
                    ->__('The given navigation type is not supported!'));
            break;
        }

        $this->_redirectReferer();
    }

    /**
     * Check if a visitor is allowed access to this controller/action(?)
     *
     * @return boolean
     */
    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/turpentine');
    }
}