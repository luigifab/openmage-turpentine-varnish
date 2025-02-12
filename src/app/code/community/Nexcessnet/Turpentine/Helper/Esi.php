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

class Nexcessnet_Turpentine_Helper_Esi extends Mage_Core_Helper_Abstract {

    public const ESI_DATA_PARAM       = 'data';
    public const ESI_TTL_PARAM        = 'ttl';
    public const ESI_CACHE_TYPE_PARAM = 'access';
    public const ESI_SCOPE_PARAM      = 'scope';
    public const ESI_METHOD_PARAM     = 'method';
    public const ESI_HMAC_PARAM       = 'hmac';
    public const MAGE_CACHE_NAME      = 'turpentine_esi_blocks';

    /**
     * Cache for layout XML
     *
     * @var Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    protected $_layoutXml = [];

    /**
     * Get whether ESI includes are enabled or not
     *
     * @return bool
     */
    public function getEsiEnabled() {
        return Mage::app()->useCache($this->getMageCacheName());
    }

    /**
     * Get if ESI should be used for this request
     *
     * @return bool
     */
    public function shouldResponseUseEsi() {
        return Mage::helper('turpentine/varnish')->shouldResponseUseVarnish() &&
            $this->getEsiEnabled();
    }

    /**
     * Check if ESI includes are enabled and throw an exception if not
     *
     * @return null
     */
    public function ensureEsiEnabled() {
        if ( ! $this->shouldResponseUseEsi()) {
            Mage::throwException('ESI includes are not enabled');
        }
    }

    /**
     * Get the name of the URL param that holds the ESI block hash
     *
     * @return string
     */
    public function getEsiDataParam() {
        return self::ESI_DATA_PARAM;
    }

    /**
     * Get the URL param name for the ESI block cache type
     *
     * @return string
     */
    public function getEsiCacheTypeParam() {
        return self::ESI_CACHE_TYPE_PARAM;
    }

    /**
     * Get the URL param name for the ESI block scope
     *
     * @return string
     */
    public function getEsiScopeParam() {
        return self::ESI_SCOPE_PARAM;
    }

    /**
     * Get the URL param name for the ESI block TTL
     *
     * @return string
     */
    public function getEsiTtlParam() {
        return self::ESI_TTL_PARAM;
    }

    /**
     * Get the URL param name for the ESI inclusion method
     *
     * @return string
     */
    public function getEsiMethodParam() {
        return self::ESI_METHOD_PARAM;
    }

    /**
     * Get the URL param name for the ESI HMAC
     *
     * @return string
     */
    public function getEsiHmacParam() {
        return self::ESI_HMAC_PARAM;
    }

    /**
     * Get referrer param
     *
     * @return string
     */
    public function getEsiReferrerParam() {
        return Mage_Core_Controller_Varien_Action::PARAM_NAME_BASE64_URL;
    }

    /**
     * Get whether ESI debugging is enabled or not
     *
     * @return bool
     */
    public function getEsiDebugEnabled() {
        return Mage::helper('turpentine/varnish')->getVarnishDebugEnabled();
    }

    /**
     * Get whether block name logging is enabled or not
     *
     * @return bool
     */
    public function getEsiBlockLogEnabled() {
        return Mage::getStoreConfigFlag('turpentine_varnish/general/block_debug');
    }

    /**
     * Check if the flash messages are enabled and we're not in the admin section
     *
     * @return bool
     */
    public function shouldFixFlashMessages() {
        return Mage::helper('turpentine/data')->useFlashMessagesFix() &&
            Mage::app()->getStore()->getCode() !== 'admin';
    }

    /**
     * Get URL for redirects and dummy requests
     *
     * @return string
     */
    public function getDummyUrl() {
        return Mage::getUrl('checkout/cart');
    }

    /**
     * Get mock request
     *
     * Used to pretend that the request was for the base URL instead of
     * turpentine/esi/getBlock while rendering ESI blocks. Not perfect, but may
     * be good enough
     *
     * @return Mage_Core_Controller_Request_Http
     */
    public function getDummyRequest($url = null) {
        if ($url === null) {
            $url = $this->getDummyUrl();
        }
        $request = Mage::getModel('turpentine/dummy_request', $url);
        $request->fakeRouterDispatch();
        return $request;
    }

    /**
     * Get the cache type Magento uses
     *
     * @return string
     */
    public function getMageCacheName() {
        return self::MAGE_CACHE_NAME;
    }

    /**
     * Get the list of cache clear events to include with every ESI block
     *
     * @return string[]
     */
    public function getDefaultCacheClearEvents() {
        $events = [
            'customer_login',
            'customer_logout',
        ];
        return $events;
    }

    /**
     * Get the list of events that should cause the ESI cache to be cleared
     * New: Now you can dispathEvent from backend to refresh frontend cache
     *  but only for POST actions, because it's time consuming, depending on number of store view
     *
     * @return string[]
     */
    public function getCacheClearEvents() {

        $allEvents = [];

        if (!empty($_POST) && Mage::app()->getStore()->isAdmin()) {
            $onetime = [];
            $stores  = Mage::getResourceModel('core/store_collection')->addFieldToFilter('is_active', 1)->setLoadDefault(true); // with admin
            foreach ($stores as $storeId => $store) {
                $area    = ($storeId == 0) ? 'adminhtml' : 'frontend';
                $package = Mage::getStoreConfig('design/package/name', $storeId);
                $theme   = Mage::getStoreConfig('design/theme/layout', $storeId) ?? Mage::getStoreConfig('design/theme/default', $storeId);
                if (!in_array($area.$package.$theme, $onetime)) {
                    $onetime[]   = $area.$package.$theme;
                    $frontDesign = ($storeId == 0) ? null : Mage::getModel('core/design_package') // not Mage::getDesign()
                        ->setStore($store)->setArea($area)->setPackageName($package)->setTheme($theme);
                    $cacheKey    = $this->getCacheClearEventsCacheKey($frontDesign);
                    $cacheData   = $cacheKey ? Mage::app()->loadCache($cacheKey) : null;
                    $events      = $cacheData ? @unserialize($cacheData) : null;
                    if (is_null($events) || $events === false) {
                        $events = $this->_loadEsiCacheClearEvents($frontDesign);
                        Mage::app()->saveCache(serialize($events), $cacheKey, ['LAYOUT_GENERAL_CACHE_TAG']);
                        $allEvents = array_merge($allEvents, $events);
                    } else {
                        $allEvents = array_merge($allEvents, $events);
                    }
                }
            }
        } else {
            $cacheKey  = $this->getCacheClearEventsCacheKey();
            $cacheData = $cacheKey ? Mage::app()->loadCache($cacheKey) : null;
            $allEvents = $cacheData ? @unserialize($cacheData) : null;
            if (is_null($allEvents) || $allEvents === false) {
                $allEvents = $this->_loadEsiCacheClearEvents();
                Mage::app()->saveCache(serialize($allEvents), $cacheKey, ['LAYOUT_GENERAL_CACHE_TAG']);
            }
        }

        return array_merge($this->getDefaultCacheClearEvents(), $allEvents);
    }

    /**
     * Get the default private ESI block TTL
     *
     * @return string
     */
    public function getDefaultEsiTtl() {
        $defaultLifeTime = (int) Mage::getStoreConfig('web/cookie/cookie_lifetime');
        if ($defaultLifeTime < 60) {
            $defaultLifeTime = ini_get('session.gc_maxlifetime');
        }
        return $defaultLifeTime;
    }

    /**
     * Get the CORS origin field from the unsecure base URL
     *
     * If this isn't added to AJAX responses they won't load properly
     *
     * @return string
     */
    public function getCorsOrigin($url = null) {
        if (is_null($url)) {
            $baseUrl = Mage::getBaseUrl();
        } else {
            $baseUrl = $url;
        }
        $path = parse_url($baseUrl, PHP_URL_PATH);
        $domain = parse_url($baseUrl, PHP_URL_HOST);
        // there has to be a better way to just strip the path off
        return substr($baseUrl, 0,
            strpos($baseUrl, $path,
                strpos($baseUrl, $domain)));
    }

    /**
     * Get the layout's XML structure
     *
     * This is cached because it's expensive to load for each ESI'd block
     *
     * @param  $frontDesign, from admin to refresh front blocks, it's the frontend design
     * @return Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    public function getLayoutXml($frontDesign = null) {
        $cache = $frontDesign ? $frontDesign->getStore()->getId() : Mage::app()->getStore()->getId();
        if (!isset($this->_layoutXml[$cache])) {
            if ($useCache = Mage::app()->useCache('layout')) {
                $cacheKey  = $this->getFileLayoutUpdatesXmlCacheKey($frontDesign);
                $cacheData = $cacheKey ? Mage::app()->loadCache($cacheKey) : null;
                $this->_layoutXml[$cache] = empty($cacheData) ? false : simplexml_load_string($cacheData);
            }
            // this check is redundant if the layout cache is disabled
            if (empty($this->_layoutXml[$cache])) {
                $this->_layoutXml[$cache] = $this->_loadLayoutXml($frontDesign);
                if ($useCache) {
                    Mage::app()->saveCache($this->_layoutXml[$cache]->asXML(),
                        $cacheKey, ['LAYOUT_GENERAL_CACHE_TAG']);
                }
            }
        }
        return $this->_layoutXml[$cache];
    }

    /**
     * Get the cache key for the cache clear events
     *
     * @param  $frontDesign, from admin to refresh front blocks, it's the frontend design
     * @return string
     */
    public function getCacheClearEventsCacheKey($frontDesign = null) {
        $design = $frontDesign ?? Mage::getDesign();
        return Mage::helper('turpentine/data')
            ->getCacheKeyHash([
                'FILE_LAYOUT_ESI_CACHE_EVENTS',
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme('layout'),
                $frontDesign ? $frontDesign->getStore()->getId() : Mage::app()->getStore()->getId(),
            ]);
    }

    /**
     * Get the cache key for the file layouts xml
     *
     * @param  $frontDesign, from admin to refresh front blocks, it's the frontend design
     * @return string
     */
    public function getFileLayoutUpdatesXmlCacheKey($frontDesign = null) {
        $design = $frontDesign ?? Mage::getDesign();
        return Mage::helper('turpentine/data')
            ->getCacheKeyHash([
                'FILE_LAYOUT_UPDATES_XML',
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme('layout'),
                $frontDesign ? $frontDesign->getStore()->getId() : Mage::app()->getStore()->getId(),
            ]);
    }

    /**
     * Generate an ESI tag to be replaced by the content from the given URL
     *
     * Generated tag looks like:
     *     <esi:include src="$url" />
     *
     * @param  string $url url to pull content from
     * @return string
     */
    public function buildEsiIncludeFragment($url) {
        // https://github.com/PHOENIX-MEDIA/Magento-PageCache-powered-by-Varnish/issues/17#issuecomment-93674983
        return sprintf('<esi:include src=\'%s\' />', $url);
    }

    /**
     * Generate an ESI tag with content that is removed when ESI processed, and
     * visible when not
     *
     * Generated tag looks like:
     *     <esi:remove>$content</esi>
     *
     * @param  string $content content to be removed
     * @return string
     */
    public function buildEsiRemoveFragment($content) {
        return sprintf('<esi:remove>%s</esi>', $content);
    }

    /**
     * Get URL for grabbing form key via ESI
     *
     * @return string
     */
    public function getFormKeyEsiUrl() {
        $urlOptions = [
            $this->getEsiTtlParam()         => $this->getDefaultEsiTtl(),
            $this->getEsiMethodParam()      => 'esi',
            $this->getEsiScopeParam()       => 'global',
            $this->getEsiCacheTypeParam()   => 'private',
        ];
        $esiUrl = Mage::getUrl('turpentine/esi/getFormKey', $urlOptions);
        // setting [web/unsecure/base_url] can be https://... but ESI can never be HTTPS
        $esiUrl = preg_replace('|^https://|i', 'http://', $esiUrl);
        return $esiUrl;
    }

    /**
     * Grab a block node by name from the layout XML.
     *
     * Multiple blocks with the same name may exist in the layout, because some themes
     * use 'unsetChild' to remove a block and create it with the same name somewhere
     * else. For example Ultimo does this.
     *
     * @param Mage_Core_Model_Layout $layout
     * @param string $blockName value of name= attribute in layout XML
     * @return Mage_Core_Model_Layout_Element
     */
    public function getEsiLayoutBlockNode($layout, $blockName) {
        // first try very specific by checking for action setEsiOptions inside block
        $blockNodes = $layout->getNode()->xpath(
            sprintf('//block[@name=\'%s\'][action[@method=\'setEsiOptions\']]',
                $blockName)
        );
        $blockNode = end($blockNodes);
        // fallback: only match name
        if ( ! ($blockNode instanceof Mage_Core_Model_Layout_Element)) {
            $blockNodes = $layout->getNode()->xpath(
                sprintf('//block[@name=\'%s\']', $blockName)
            );
            $blockNode = end($blockNodes);
        }
        return $blockNode;
    }

    /**
     * Load the ESI cache clear events from the layout
     *
     * @param  $frontDesign, from admin to refresh front blocks, it's the frontend design
     * @return array
     */
    protected function _loadEsiCacheClearEvents($frontDesign = null) {
        $layoutXml = $this->getLayoutXml($frontDesign);
        $events = $layoutXml->xpath('//action[@method=\'setEsiOptions\']/params/flush_events/*');
        if ($events) {
            $events = array_unique(array_map(static function ($e) {
                return (string)$e->getName();
            }, $events));
        } else {
            $events = [];
        }
        return $events;
    }

    /**
     * Load the layout's XML structure, bypassing any caching
     *
     * @param  $frontDesign, from admin to refresh front blocks, it's the frontend design
     * @return Mage_Core_Model_Layout_Element
     */
    protected function _loadLayoutXml($frontDesign = null) {
        $design = $frontDesign ?? Mage::getDesign();
        return Mage::getSingleton('core/layout')
            ->getUpdate()
            ->getFileLayoutUpdatesXml(
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme('layout'),
                $frontDesign ? $frontDesign->getStore()->getId() : Mage::app()->getStore()->getId());
    }
}