<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev;

use Composer\Autoload\ClassLoader;

/**
 * An autoloader that instruments code.
 *
 * Mapping of class name to file is performed by a Composer autoloader.
 */
final class Autoloader
{
    public function __construct(ClassLoader $composerLoader)
    {
        $this->composerLoader = $composerLoader;
        $this->excludePattern = '/^(Composer|PhpParser|' . preg_quote(__NAMESPACE__, '/') . ')\\\\/';
    }

    /**
     * Register the autoloader before all existing autoloaders.
     */
    public function register() : bool
    {
        InstrumentedStream::install();
        spl_autoload_register([$this, 'loadClass'], true, true);

        return true;
    }

    /**
     * Unregister the autoloader.
     */
    public function unregister() : bool
    {
        spl_autoload_unregister([$this, 'loadClass']);

        return true;
    }

    /**
     * Load a class.
     *
     * This loader will not load any classes that are not instrumented.
     */
    public function loadClass(string $className)
    {
        if (preg_match($this->excludePattern, $className)) {
            return;
        }

        $filename = $this->composerLoader->findFile($className);

        if ($filename === false) {
            return;
        }

        (static function () {
            require InstrumentedStream::SCHEME . '://' . func_get_arg(0);
        })($filename);
    }

    /**
     * @var ClassLoader The underlying Composer autoloader.
     */
    private $composerLoader;

    /**
     * @var string A regex pattern matching class names which are not to be
     *             instrumented.
     */
    private $excludePattern;
}
