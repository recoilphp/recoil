<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev;

use PhpParser\Error;

/**
 * A PHP stream wrapper that instruments code.
 */
final class InstrumentedStream
{
    const SCHEME = 'recoil-dev';
    const PREFIX = self::SCHEME . '://';

    public static function install(Instrumentor $instrumentor = null)
    {
        if (self::$defaultInstrumentor === null) {
            self::$defaultInstrumentor = $instrumentor ?: new Instrumentor();

            stream_wrapper_register(
                self::SCHEME,
                __CLASS__
            );
        }
    }

    public function __construct(Instrumentor $instrumentor = null)
    {
        $this->instrumentor = $instrumentor
            ?: self::$defaultInstrumentor
            ?: new Instrumentor();
    }

    public static function extractPath(string $path) : string
    {
        return \preg_replace(
            '/^' . \preg_quote(self::PREFIX, '/') . '/',
            '',
            $path
        );
    }

    public function stream_open($path, $mode, $options = 0, &$opened_path = null)
    {
        if ($mode[0] !== 'r') {
            return false;
        }

        $path = self::extractPath($path);
        $source = file_get_contents($path);

        if ($source === false) {
            return false;
        }

        try {
            $source = $this->instrumentor->instrument($source, $path);
        } catch (Error $e) {
            // ignore
        }

        $stream = tmpfile();

        if (fwrite($stream, $source) === false) {
            return false;
        }

        if (fseek($stream, 0) === false) {
            return false;
        }

        $this->stream = $stream;

        return true;
    }

    public function stream_read($count)
    {
        return fread($this->stream, $count);
    }

    public function stream_close()
    {
        fclose($this->stream);
    }

    public function stream_eof()
    {
        return feof($this->stream);
    }

    public function stream_stat()
    {
        return fstat($this->stream);
    }

    public static function url_stat($path, $flags)
    {
        $path = self::extractPath($path);

        return @stat($path);
    }

    private static $defaultInstrumentor;
    private $instrumentor;
    private $stream;
}
