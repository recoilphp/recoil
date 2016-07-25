<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use PhpParser\Error;

/**
 * A PHP stream wrapper that instruments code.
 */
final class StreamWrapper
{
    const SCHEME = 'recoil-instrumentation';
    const PREFIX = self::SCHEME . '://';

    /**
     * Install the stream wrapper, if it has not already been installed.
     *
     * @param Instrumentor|null $instrumentor The instrumentor to use to
     *                                        instrument the code (null = default).
     */
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

    /**
     * Create a stream wrapper instance.
     *
     * @param Instrumentor|null $instrumentor The instrumentor to use to
     *                                        instrument the code (null = use
     *                                        the one provided when the stream
     *                                        wrapper was installed).
     */
    public function __construct(Instrumentor $instrumentor = null)
    {
        $this->instrumentor = $instrumentor
            ?: self::$defaultInstrumentor
            ?: new Instrumentor();
    }

    /**
     * Extract the original path from an instrumented stream URI.
     */
    public static function extractPath(string $path) : string
    {
        return \preg_replace(
            '/^' . \preg_quote(self::PREFIX, '/') . '/',
            '',
            $path
        );
    }

    /**
     * Open the stream.
     */
    public function stream_open(
        string $path,
        string $mode,
        int $options = 0,
        string &$openedPath = null
    ) : bool {
        if ($mode[0] !== 'r') {
            return false;
        }

        $path = self::extractPath($path);
        $openedPath = \realpath($path);
        $source = file_get_contents($path);

        if ($source === false) {
            return false;
        }

        try {
            $source = $this->instrumentor->instrument($source);
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

    /**
     * Read from the stream.
     */
    public function stream_read(int $count) : string
    {
        return fread($this->stream, $count);
    }

    /**
     * Close the stream.
     */
    public function stream_close() : bool
    {
        return fclose($this->stream);
    }

    /**
     * Check if the stream has reached EOF.
     */
    public function stream_eof() : bool
    {
        return feof($this->stream);
    }

    /**
     * Perform a stat() operation on the stream.
     *
     * @return array|bool
     */
    public function stream_stat()
    {
        return fstat($this->stream);
    }

    /**
     * Perform a stat() operation on a specific path.
     *
     * @return array|bool
     */
    public static function url_stat(string $path, int $flags)
    {
        $path = self::extractPath($path);

        return @stat($path);
    }

    /**
     * @var Instrumentor|null The default instrumentor for instances fo the
     *                        stream wrapper.
     */
    private static $defaultInstrumentor;

    /**
     * @var Instrumentor The actual instrumentor to use for this instance.
     */
    private $instrumentor;

    /**
     * @var resource|null The underlying stream object, null unless stream_open()
     *                    has been called successfully.
     */
    private $stream;
}
