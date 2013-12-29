# Recoil

[![Build Status]](https://travis-ci.org/IcecaveStudios/recoil)
[![Test Coverage]](https://coveralls.io/r/IcecaveStudios/recoil?branch=develop)
[![SemVer]](http://semver.org)

**Recoil** is a generator-based cooperative multitasking kernel for [ReactPHP](https://github.com/reactphp/react).

* Install via [Composer](http://getcomposer.org) package [icecave/recoil](https://packagist.org/packages/icecave/recoil)
* Read the [API documentation](http://IcecaveStudios.github.io/recoil/artifacts/documentation/api/)

## Overview

The goal of **Recoil** is to enable development of asynchronous applications using familiar imperative programming
techniques. This goal is made possible by the addition of [generators](http://de2.php.net/manual/en/language.generators.overview.php)
in PHP 5.5.

**Recoil** uses PHP generators to implement coroutines. Coroutines are functions that can be suspended and resumed while
persisting contextual information such as local variables. By choosing to suspend execution of a coroutine at key points
such as while waiting for I/O, asynchronous applications can be built to resemble traditional synchronous applications.

[Nikita Popov](https://github.com/nikic) has published an [excellent article](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html)
explaining the function and benefits of generator-based coroutines. The article even includes an example implementation
of a coroutine scheduler, though it takes a somewhat different approach to **Recoil**.

## Concepts

### Coroutines

*Coroutines* are units of work that can be suspended and resumed while maintaining execution state. Coroutines can
produce values when suspended, and receive values or exceptions when resumed. Coroutines based on PHP generators are the
basic building blocks of a **Recoil** application.

### Strands

*Strands* provide a thread-like abstraction for coroutine execution in a **Recoil** application. Much like a thread
provided by the operating system each strand has its own call stack and may be suspended, resumed, joined and terminated
without affecting other strands.

Unlike threads, execution of a strand can only suspend or resume when a coroutine specifically requests to do so, hence
the term *cooperative multitasking*.

Strands are sometimes known as [green threads](http://en.wikipedia.org/wiki/Green_threads).

### The Kernel and Kernel API

The *kernel* is responsible for creating and scheduling strands, much like the operating system kernel does for threads.
Internally, the kernel uses a [ReactPHP event-loop](https://github.com/reactphp/event-loop) for scheduling. This allows
applications to execute coroutine based code alongside "conventional" ReactPHP code by sharing an event-loop instance.

Coroutine control flow, the current strand, and the kernel itself can be manipulated using the *kernel API*. The
supported operations are defined in [KernelApiInterface](src/Icecave/Recoil/Kernel/Api/KernelApiInterface.php) (though
custom kernel implementations may provide additional operations). Inside an executing coroutine, the kernel API for
the current kernel is accessed via the [Recoil facade](src/Icecave/Recoil/Recoil.php).

### Streams

*Streams* provide a coroutine based abstraction for [readable](src/Icecave/Recoil/Stream/ReadableStreamInterface.php)
and [writable](src/Icecave/Recoil/Stream/WritableStreamInterface.php) data streams. The interfaces are somewhat similar
to the built-in PHP stream API.

Stream operations are cooperative, that is, when reading or writing to a stream, execution of the coroutine is suspended
until the stream is ready, allowing the kernel to schedule other strands for execution while waiting.

### Channels

*Channels* are stream-like objects that produce and consume PHP values rather than byte streams. Channels are intended
as the primary method for communication between strands.

Like streams there are [readable](src/Icecave/Recoil/Channel/ReadableChannelInterface.php) and [writable](src/Icecave/Recoil/Channel/WritableChannelInterface.php)
variants. Some channel implementations allow for multiple concurrent read and write operations.

Both in-memory and stream-based channels are provided. Stream-based channels use a serialization protocol to encode and
decode PHP values for transmission over a stream and as such can be useful for IPC or network communication.

## Examples

The following examples illustrate basic usage of **Recoil**. Additional examples are available in the [examples folder](examples/).

References to the class `Recoil` refer to the [Recoil facade](src/Icecave/Recoil/Recoil.php).

### Basic execution

The following example shows the simplest way to execute a generator as a coroutine.

```php
Recoil::run(
    function () {
        echo 'Hello, world!' . PHP_EOL;
        yield Recoil::noop();
    }
);
```

`Recoil::run()` is a convenience method that instantiates a kernel and executes the given function in a new strand.
The `yield` keyword must be present in order for PHP to parse the function as a generator. Yielding `Recoil::noop()`
(no-operation) instructs the kernel to continue executing the current coroutine without suspending.

### Calling one coroutine from another

Coroutines can be called simply by yielding. Yielded generators are adapted into [GeneratorCoroutine](src/Icecave/Recoil/Coroutine/GeneratorCoroutine.php)
instances so that they may be executed by **Recoil**. Coroutines are executed on the current strand and as such
execution of the caller is only resumed once the yielded coroutine has completed.

```php
function hello()
{
    echo 'Hello, ';
    yield Coro::noop();
}

function world()
{
    echo 'world!' . PHP_EOL;
    yield Coro::noop();
}

Recoil::run(
    function () {
        yield hello();
        yield world();
    }
);
```

### Returning a value from a coroutine

Because PHP's `return` keyword can not be used to return a value inside a generator, the kernel API provides
`Recoil::return_()` to send a value to the calling coroutine. Just like `return` execution of the coroutine stops when a
value is returned.

```php
function multiply($a, $b)
{
    yield Recoil::return_($a * $b);
    echo 'This code is never reached.';
}

Recoil::run(
    function () {
        $result = (yield multiply(3, 7));
        echo '3 * 7 is ' . $result . PHP_EOL;
    }
);
```

### Throwing and catching exceptions

One of the major advantages made available by coroutines is that errors can be reported using familiar exception
handling techniques. Unlike `return`, the `throw` keyword can be used in the standard way inside PHP generators.

```php
function multiply($a, $b)
{
    if (!is_numeric($a) || !is_numeric($b)) {
        throw new InvalidArgumentException;
    }

    yield Recoil::return_($a * $b);
}

Recoil::run(
    function() {
        try {
            yield multiply(1, 'foo');
        } catch (InvalidArgumentException $e) {
            echo 'Invalid argument!';
        }
    }
);
```

`Recoil::throw_()` is equivalent to a `throw` statement, and is useful when a function would otherwise not be parsed as
a generator.

```php
function onlyThrow()
{
    yield Recoil::throw_(new Exception('Not implemented!'));
}
```

## Cooperating with ReactPHP

**Recoil** includes several features to allow interoperability with ReactPHP.

### Streams

[ReactPHP streams](https://github.com/reactphp/stream) can be adapted into **Recoil** streams using [ReadableReactStream](src/Icecave/Recoil/Stream/ReadableReactStream.php)
and [WritableReactStream](src/Icecave/Recoil/Stream/WritableReactStream.php).

### Promises

[ReactPHP promises](https://github.com/reactphp/promise) can be yielded directly from a coroutine. The promise is
adapted into a [PromiseCoroutine](src/Icecave/Recoil/Coroutine/PromiseCoroutine.php) instance and the calling coroutine
is resumed once the promise has been fulfilled.

If the promise is resolved, the resulting value is returned from the yield statement. If it is rejected, the yield
statement throws an exception describing the error. Promise progress events are not currently supported.

As promises do not (yet?) support cancellation, terminating a coroutine that is waiting on a promise simply causes the
promise resolution to be ignored.

### Using an existing event-loop

In all of the examples above, the `Recoil::run()` convenience function is used to start the kernel. Internally this
function chooses an appropriate event-loop implementation, instantiates the kernel, enqueues the given function for
execution and runs the event-loop.

An existing event-loop can be used by passing it as the second parameter.

```php
$eventLoop = new \React\EventLoop\StreamSelectLoop;

Recoil::run(
    function () {
        echo 'Hello, world!' . PHP_EOL;
        yield Recoil::noop();
    },
    $eventEventLoop
);
```

Note that the event-loop will be started by `Recoil::run()`, hence this function will block until there are no more
pending events.

#### Instantiating the kernel manually

To attach a coroutine kernel to an existing event-loop without assuming ownership the kernel must be instantiated
manually.

```php
$eventLoop = new \React\EventLoop\StreamSelectLoop;

$kernel = new \Icecave\Recoil\Kernel\Kernel($eventLoop);

$coroutine = function () {
    echo 'Hello, world!' . PHP_EOL;
    yield Recoil::noop();
};

$kernel->execute($coroutine());

$eventLoop->run();
```
<!-- references -->
[Build Status]: https://travis-ci.org/IcecaveStudios/recoil.png?branch=develop
[Test Coverage]: https://coveralls.io/repos/IcecaveStudios/recoil/badge.png?branch=develop
[SemVer]: http://calm-shore-6115.herokuapp.com/?label=semver&value=0.0.0&color=red
