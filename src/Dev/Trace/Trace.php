<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

use Recoil\Kernel\Strand;
use ReflectionClass;
use Throwable;

final class Trace
{
    public static function update(Strand $strand, Throwable $exception, array $stack)
    {
        $reflector = new ReflectionClass($exception);

        // We can't update the stack trace if the property doesn't exist ...
        if (!$reflector->hasProperty('trace')) {
            return;
        }

        $originalTrace = $exception->getTrace();
        $strandTrace = [];

        // Keep the original trace up until we find the internal generator code ...
        foreach ($originalTrace as $frame) {
            if ($frame['recoil'] ?? false) {
                return;
            } elseif (isset($frame['class']) && $frame['class'] === 'Generator') {
                break;
            }

            $strandTrace[] = $frame;
        }

        $lastIndex = count($strandTrace) - 1;

        // Traverse backwards through the strand's call stack to synthesize
        // stack frames ...
        foreach (array_reverse($stack) as $frame) {
            if (isset($frame->trace)) {
                $strandTrace[$lastIndex]['line'] = $frame->trace->yieldLine;
                $strandTrace[$lastIndex]['file'] = $frame->trace->file;

                $strandTrace[] = [
                    // @todo object, class, type, etc
                    'function' => $frame->trace->function,
                    'args' => $frame->trace->arguments,
                    'recoil' => true,
                ];
            } else {
                $strandTrace[$lastIndex]['file'] = 'Unknown';

                $strandTrace[] = [
                    'function' => '{uninstrumented coroutine}',
                    'recoil' => true,
                ];
            }

            ++$lastIndex;
        }

        $strandTrace[$lastIndex]['file'] = '{strand entry-point}';

        // Replace the exception's trace proprety with the strand stack trace ...
        $property = $reflector->getProperty('trace');
        $property->setAccessible(true);
        $property->setValue($exception, $strandTrace);
    }

    private function __construct()
    {
    }
}
