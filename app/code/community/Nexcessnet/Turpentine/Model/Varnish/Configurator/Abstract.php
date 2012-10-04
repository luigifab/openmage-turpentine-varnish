<?php

/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

abstract class Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract {
    protected $_sockets = null;
    public function __construct( $options=array() ) {

    }

    abstract public function generate();
    abstract protected function _getTemplateVars();

    /**
     * Save the generated config to the file specified in Magento config
     *
     * @param  string $generatedConfig config generated by @generate
     * @return null
     */
    public function save( $generatedConfig ) {
        $filename = $this->_getVclFilename();
        $dir = dirname( $filename );
        if( !is_dir( $dir ) ) {
            if( !mkdir( $dir, true ) ) {
                $err = error_get_last();
                return array( false, $err );
            }
        }
        if( strlen( $generatedConfig ) !==
                file_put_contents($filename, $generatedConfig ) ) {
            $err = error_get_last();
            return array( false, $err );
        }
        return array( true, null );
    }

    /**
     * Get the list of turpentine/varnish_admin_socket models configured in
     * the server list
     *
     * @return array
     */
    public function getSockets() {
        if( is_null( $this->_sockets ) ) {
            $sockets = array();
            $servers = array_filter( array_map( 'trim', explode( PHP_EOL,
                Mage::getStoreConfig( 'turpentine_servers/servers/server_list' ) ) ) );
            $key = str_replace( '\n', "\n",
                Mage::getStoreConfig( 'turpentine_servers/servers/auth_key' ) );
            foreach( $servers as $server ) {
                $parts = explode( ':', $server );
                $socket = Mage::getModel( 'turpentine/varnish_admin_socket',
                    array( 'host' => $parts[0], 'port' => $parts[1] ) );
                if( $key ) {
                    $socket->setAuthSecret( $key );
                }
                $sockets[] = $socket;
            }
            $this->_sockets = $sockets;
        }
        return $this->_sockets;
    }

    /**
     * Get the full path for a given template filename
     *
     * @param  string $baseFilename
     * @return string
     */
    protected function _getVclTemplateFilename( $baseFilename ) {
        $extensionDir = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
        return sprintf( '%s/misc/%s', $extensionDir, $baseFilename );
    }

    /**
     * [_getVclFilename description]
     * @return [type]
     */
    protected function _getVclFilename() {
        return $this->_formatTemplate(
            Mage::getStoreConfig( 'turpentine_servers/servers/config_file' ),
            array( 'root_dir' => Mage::getBaseDir() ) );
    }

    /**
     * Format a template string, replacing {{keys}} with the appropriate values
     * and remove unspecified keys
     *
     * @param  string $template template string to operate on
     * @param  array  $vars     array of key => value replacements
     * @return string
     */
    protected function _formatTemplate( $template, array $vars ) {
        $needles = array_map( create_function( '$k', 'return "{{".$k."}}";' ),
            array_keys( $vars ) );
        $replacements = array_values( $vars );
        // do replacements, then delete unused template vars
        return preg_replace( '~{{[^}]+}}~', '',
            str_replace( $needles, $replacements, $template ) );
    }

    /**
     * Format a VCL subroutine call
     *
     * @param  string $subroutine subroutine name
     * @return string
     */
    protected function _vcl_call( $subroutine ) {
        return sprintf( 'call %s;', $subroutine );
    }

    /**
     * Get the Magento admin frontname
     *
     * This is just the plain string, not in URL format. ex:
     * http://example.com/magento/admin -> admin
     *
     * @return string
     */
    protected function _getAdminFrontname() {
        if( Mage::getStoreConfig( 'admin/url/use_custom_path' ) ) {
            return Mage::getStoreConfig( 'admin/url/custom_path' );
        } else {
            return Mage::getConfig()->getNode(
                'admin/routers/adminhtml/args/frontName' );
        }
    }

    /**
     * Get the hostname for host normalization from Magento's base URL
     *
     * @return string
     */
    protected function _getNormalizeHostTarget() {
        $configHost = trim( Mage::getStoreConfig(
            'turpentine_control/normalization/host_target' ) );
        if( $configHost ) {
            return $configHost;
        } else {
            $baseUrl = parse_url( Mage::getBaseUrl() );
            if( isset( $baseUrl['port'] ) ) {
                return sprintf( '%s:%d', $baseUrl['host'], $baseUrl['port'] );
            } else {
                return $baseUrl['host'];
            }
        }
    }

    /**
     * Get the base url path regex
     *
     * ex: base_url: http://example.com/magento/
     *     path_regex: /magento/(?:(?:index|litespeed)\.php/)?
     *
     * @return string
     */
    public function getBaseUrlPathRegex() {
        return '^' . parse_url(
                Mage::getStoreConfig( 'web/unsecure/base_url' ), PHP_URL_PATH ) .
            '(?:(?:index|litespeed)\\.php/)?';
    }

    /**
     * Format the URL exclusions for insertion in a regex. Admin frontname and
     * API are automatically added.
     *
     * @return string
     */
    protected function _getUrlExcludes() {
        return implode( '|', array_merge( array( $this->_getAdminFrontname(), 'api' ),
            array_map( 'trim', explode( PHP_EOL,
                Mage::getStoreConfig( 'turpentine_control/urls/url_blacklist' ) ) ) ) );
    }

    /**
     * Format the cookie exclusions for insertion in a regex. The no cache
     * cookie is automatically included
     *
     * @return string
     */
    protected function _getCookieExcludes() {
        $cookies = array(
            Mage::helper( 'turpentine' )->getNoCacheCookieName(),
            Mage::helper( 'turpentine' )->getAdminCookieName() );
        $excludedCookies = array_map( 'trim', explode( PHP_EOL, trim(
            Mage::getStoreConfig( 'turpentine_control/excludes/cookies' ) ) ) );
        foreach( $excludedCookies as $cookie ) {
            if( $cookie ) {
                $cookies[] = $cookie;
            }
        }
        return implode( '|', $cookies );
    }

    /**
     * Get the default cache TTL from Magento config
     *
     * @return string
     */
    protected function _getDefaultTtl() {
        return trim( Mage::getStoreConfig( 'turpentine_control/ttls/default_ttl' ) );
    }

    /**
     * Get the default backend configuration string
     *
     * @return string
     */
    protected function _getDefaultBackend() {
        $default_options = array(
            'first_byte_timeout'    => '300s',
            'between_bytes_timeout' => '300s',
        );
        return $this->_vcl_backend( 'default',
            Mage::getStoreConfig( 'turpentine_servers/backend/backend_host' ),
            Mage::getStoreConfig( 'turpentine_servers/backend/backend_port' ),
            $default_options );
    }

    protected function _getAdminBackend() {
        $admin_options = array(
            'first_byte_timeout'    => '21600s',
            'between_bytes_timeout' => '21600s',
        );
        return $this->_vcl_backend( 'admin',
            Mage::getStoreConfig( 'turpentine_servers/backend/backend_host' ),
            Mage::getStoreConfig( 'turpentine_servers/backend/backend_port' ),
            $admin_options );
    }

    /**
     * Get the grace period for vcl_fetch
     *
     * This is curently hardcoded to 15 seconds, will be configurable at some
     * point
     *
     * @return string
     */
    protected function _getGracePeriod() {
        return '15';
    }

    /**
     * Get whether debug headers should be enabled or not
     *
     * @return string
     */
    protected function _getEnableDebugHeaders() {
        return Mage::getStoreConfig( 'turpentine_servers/debug/headers' )
            ? 'true' : 'false';
    }

    /**
     * Format the GET variable excludes for insertion in a regex
     *
     * @return string
     */
    protected function _getGetParamExcludes() {
        return implode( '|', array_map( 'trim', explode( ',',
            Mage::getStoreConfig( 'turpentine_control/params/get_params' ) ) ) );
    }

    /**
     * Get the Force Static Caching option
     *
     * @return string
     */
    protected function _getForceCacheStatic() {
        return Mage::getStoreConfig( 'turpentine_control/static/force_static' )
            ? 'true' : 'false';
    }

    /**
     * Format the list of static cache extensions
     *
     * @return string
     */
    protected function _getStaticExtensions() {
        $exts = implode( '|', array_filter( array_map( 'trim', explode( ',',
            Mage::getStoreConfig( 'turpentine_control/static/exts' ) ) ) ) );
        return $exts;
    }

    /**
     * Get the static caching TTL
     *
     * @return string
     */
    protected function _getStaticTtl() {
        return Mage::getStoreConfig( 'turpentine_control/ttls/static_ttl' );
    }

    /**
     * Format the by-url TTL value list
     *
     * @return string
     */
    protected function _getUrlTtls() {
        $str = array();
        $configTtls = array_filter( array_map( 'trim', explode( PHP_EOL, trim(
            Mage::getStoreConfig( 'turpentine_control/ttls/url_ttls' ) ) ) ) );
        $ttls = array();
        foreach( $configTtls as $line ) {
            $ttls[] = explode( ',', trim( $line ) );
        }
        foreach( $ttls as $ttl ) {
            $str[] = sprintf( 'if (bereq.url ~ "%s%s") { set beresp.ttl = %ds; }',
                $this->getBaseUrlPathRegex(), $ttl[0], $ttl[1] );
        }
        $str = implode( ' else ', $str );
        if( $str ) {
            $str .= sprintf( ' else { set beresp.ttl = %ds; }',
                $this->_getDefaultTtl() );
        } else {
            $str = sprintf( 'set beresp.ttl = %ds;', $this->_getDefaultTtl() );
        }
        return $str;
    }

    /**
     * Get the Enable Caching value
     *
     * @return string
     */
    protected function _getEnableCaching() {
        return Mage::getStoreConfig( 'turpentine_control/general/enable' )
            ? 'true' : 'false';
    }

    protected function _getSetInitialCookie() {
        return Mage::getStoreConfig(
                'turpentine_control/cache_cookie/set_initial_cookie' )
            ? 'true' : 'false';
    }

    /**
     * Remove empty and commented out lines from the generated VCL
     *
     * @param  string $dirtyVcl generated vcl
     * @return string
     */
    protected function _cleanVcl( $dirtyVcl ) {
        return implode( PHP_EOL,
            array_filter(
                explode( PHP_EOL, $dirtyVcl ),
                array( $this, '_cleanVclHelper' )
            )
        );
    }

    /**
     * Helper to filter out blank/commented lines for VCL cleaning
     *
     * @param  string $line
     * @return bool
     */
    protected function _cleanVclHelper( $line ) {
        return trim( $line ) && substr( trim( $line ), 0, 1 ) != '#';
    }

    /**
     * Format a VCL backend declaration
     *
     * @param  string $name name of the backend
     * @param  string $host backend host
     * @param  string $port backend port
     * @return string
     */
    protected function _vcl_backend( $name, $host, $port, $options=array() ) {
        $tpl = <<<EOS
backend {{name}} {
    .host = "{{host}}";
    .port = "{{port}}";

EOS;
        $vars = array(
            'host'  => $host,
            'port'  => $port,
            'name'  => $name,
        );
        $str = $this->_formatTemplate( $tpl, $vars );
        foreach( $options as $key => $value ) {
            $str .= sprintf( '   .%s = %s;', $key, $value ) . PHP_EOL;
        }
        $str .= '}' . PHP_EOL;
        return $str;
    }

    /**
     * Format a VCL ACL declaration
     *
     * @param  string $name  ACL name
     * @param  array  $hosts list of hosts to add to the ACL
     * @return string
     */
    protected function _vcl_acl( $name, array $hosts ) {
        $tpl = <<<EOS
acl {{name}} {
    {{hosts}}
}
EOS;
        $fmtHost = create_function( '$h', 'return sprintf(\'"%s";\',$h);' );
        $vars = array(
            'name'  => $name,
            'hosts' => implode( "\n    ", array_map( $fmtHost, $hosts ) ),
        );
        return $this->_formatTemplate( $tpl, $vars );
    }

    /**
     * Get the User-Agent normalization sub routine
     *
     * @return string
     */
    protected function _vcl_sub_normalize_user_agent() {
        $tpl = <<<EOS
if (req.http.User-Agent ~ "MSIE") {
        set req.http.X-Normalized-User-Agent = "msie";
    } else if (req.http.User-Agent ~ "Firefox") {
        set req.http.X-Normalized-User-Agent = "firefox";
    } else if (req.http.User-Agent ~ "Safari") {
        set req.http.X-Normalized-User-Agent = "safari";
    } else if (req.http.User-Agent ~ "Chrome") {
        set req.http.X-Normalized-User-Agent = "chrome";
    } else if (req.http.User-Agent ~ "Opera Mini/") {
        set req.http.X-Normalized-User-Agent = "opera-mini";
    } else if (req.http.User-Agent ~ "Opera Mobi/") {
        set req.http.X-Normalized-User-Agent = "opera-mobile";
    } else if (req.http.User-Agent ~ "Opera") {
        set req.http.X-Normalized-User-Agent = "opera";
    } else {
        set req.http.X-Normalized-User-Agent = "nomatch";
    }

EOS;
        return $tpl;
    }

    /**
     * Get the Accept-Encoding normalization sub routine
     *
     * @return string
     */
    protected function _vcl_sub_normalize_encoding() {
        $tpl = <<<EOS
if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } else if (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # unkown algorithm
            unset req.http.Accept-Encoding;
        }
    }

EOS;
        return $tpl;
    }

    /**
     * Get the Host normalization sub routine
     *
     * @return string
     */
    protected function _vcl_sub_normalize_host() {
        $tpl = <<<EOS
set req.http.Host = "{{normalize_host_target}}";

EOS;
        return $this->_formatTemplate( $tpl, array(
            'normalize_host_target' => $this->_getNormalizeHostTarget() ) );
    }
}
