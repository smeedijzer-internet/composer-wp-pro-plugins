<?php

namespace Junaidbhura\Composer\WPProPlugins;

/**
 * Missing Environment Variable Exception.
 */
class MissingEnvException extends \Exception
{
    public function __construct($key)
    {
        parent::__construct(sprintf(
            'Environment variable \'%s\' is not set.',
            $key
        ));
    }
}
