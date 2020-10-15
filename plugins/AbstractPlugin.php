<?php
/**
 * Abstract WordPress Plugin.
 *
 * @package Junaidbhura\Composer\WPProPlugins\Plugins
 */

namespace Junaidbhura\Composer\WPProPlugins\Plugins;

use Junaidbhura\Composer\WPProPlugins\MissingEnvException;

/**
 * AbstractPlugin class.
 */
abstract class AbstractPlugin {

    /**
     * Get an environment value by the given key.
     *
     * @param string $key
     *
     * @throws \Junaidbhura\Composer\WPProPlugins\MissingEnvException
     *
     * @return mixed
     */
    public function get( $key )
    {
        $value = getenv( $key );

        if ( empty( $value ) || ! is_string( $value ) ) {
            throw new MissingEnvException( $key );
        }

        return $value;
    }

}
