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

class Nexcessnet_Turpentine_Model_Config_Select_stripWhitespace {
    public function toOptionArray() {
        $helper = Mage::helper('turpentine');
        return array(
            array('value' => 'always', 'label' => $helper->__('Always')),
            array('value' => 'apply', 'label' => $helper->__('Only when applying directly to Varnish')),
            array('value' => 'never', 'label' => $helper->__('Never')),
        );
    }
}