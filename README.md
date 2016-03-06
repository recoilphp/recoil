# Recoil

[![Build Status]](https://travis-ci.org/recoilphp/recoil)
[![Test Coverage]](https://coveralls.io/r/recoilphp/recoil?branch=develop)
[![SemVer]](http://semver.org)

**Recoil** is a generator-based cooperative multitasking kernel for [React](https://github.com/reactphp/react).

* Install via [Composer](http://getcomposer.org) package [recoil/recoil](https://packagist.org/packages/recoil/recoil)
* Read the [API documentation](http://recoilphp.github.io/recoil/artifacts/documentation/api/)

## Overview

The goal of **Recoil** is to enable development of asynchronous applications using familiar imperative programming
techniques. The example below uses the [Recoil Redis client](https://github.com/recoilphp/redis) and the [React DNS component](https://github.com/reactphp/dns)
to resolve several domain names **concurrently** and store the results in a Redis database.

```php
use React\Dns\Resolver\Factory as ResolverFactory;
use Recoil\Recoil;
use Recoil\Redis\Client as RedisClient;

/**
 * Resolve a domain name and store the result in Redis.
 */
function resolveAndStore($redisClient, $dnsResolver, $domainName)
{
    try {
        $ipAddress = (yield $dnsResolver->resolve($domainName));

        yield $redisClient->set($domainName, $ipAddress);

        echo 'Resolved "' . $domainName . '" to ' . $ipAddress . PHP_EOL;
    } catch (Exception $e) {
        echo 'Failed to resolve "' . $domainName . '" - ' . $e->getMessage() . PHP_EOL;
    }
}

Recoil::run(
    function () {
        $dnsResolver = (new ResolverFactory)->create(
            '8.8.8.8',
            (yield Recoil::eventLoop())
        );

        $redisClient = new RedisClient;

        yield $redisClient->connect();

        // Yielding an array of coroutines executes them concurrently.
        yield [
            resolveAndStore($redisClient, $dnsResolver, 'recoil.io'),
            resolveAndStore($redisClient, $dnsResolver, 'reactphp.org'),
            resolveAndStore($redisClient, $dnsResolver, 'icecave.com.au'),
        ];

        yield $redisClient->disconnect();
    }
);
```

Note that there is **no callback-passing**, and that regular PHP **exceptions are used for reporting errors**.

**Recoil** uses [PHP generators](http://de2.php.net/manual/en/language.generators.overview.php) to implement coroutines.
Coroutines are functions that can be suspended and resumed while persisting contextual information such as local
variables. By choosing to suspend execution of a coroutine at key points such as while waiting for I/O, asynchronous
applications can be built to resemble traditional synchronous applications.

[Nikita Popov](https://github.com/nikic) has published an [excellent article](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html)
explaining the usage and benefits of generator-based coroutines. The article even includes an example implementation of
a coroutine scheduler, though it takes a somewhat different approach.

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

Strands are very light-weight and are sometimes known as [green threads](http://en.wikipedia.org/wiki/Green_threads).

### The Kernel and Kernel API

The *kernel* is responsible for creating and scheduling strands, much like the operating system kernel does for threads.
Internally, the kernel uses a [React event-loop](https://github.com/reactphp/event-loop) for scheduling. This allows
applications to execute coroutine based code alongside "conventional" React code by sharing an event-loop instance.

Coroutine control flow, the current strand, and the kernel itself can be manipulated using the *kernel API*. The
supported operations are defined in [KernelApi](src/Kernel/Api/KernelApi.php) (though custom kernel implementations may
provide additional operations). Inside an executing coroutine, the kernel API for the current kernel is accessed via the
[Recoil facade](src/Recoil.php).

### Streams

*Streams* provide a coroutine based abstraction for [readable](src/Stream/ReadableStream.php) and [writable](src/Stream/WritableStream.php)
data streams. The interfaces are somewhat similar to the built-in PHP stream API.

Stream operations are cooperative, that is, when reading or writing to a stream, execution of the coroutine is suspended
until the stream is ready, allowing the kernel to schedule other strands for execution while waiting.

The [stream-file example](examples/stream-file) demonstrates using a readable stream to read a file.

### Channels

*Channels* are stream-like objects that produce and consume PHP values rather than byte streams. Channels are intended
as the primary method for communication between strands.

Like streams there are [readable](src/Channel/ReadableChannel.php) and [writable](src/Channel/WritableChannel.php)
variants. Some channel implementations allow for multiple concurrent read and write operations.

Both in-memory and stream-based channels are provided. Stream-based channels use a serialization protocol to encode and
decode PHP values for transmission over a stream and as such can be useful for IPC or network communication.

The [channel-ipc example](examples/channel-ipc) demonstrates using stream-based channels to communicate with a
sub-process.

## Examples

The following examples illustrate the basic usage of coroutines and the kernel API. Additional examples are available in
the [examples folder](examples/). References to the class `Recoil` refer to the [Recoil facade](src/Recoil.php).

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

`Recoil::run()` is a convenience method that instantiates a kernel and executes the given coroutine in a new strand.
Yielding `Recoil::noop()` (no-operation) allows for the use of the `yield` keyword - which forces PHP to parse the
function as a generator - without changing the behaviour.

### Calling one coroutine from another

Coroutines can be called simply by yielding. Yielded generators are adapted into [GeneratorCoroutine](src/Coroutine/GeneratorCoroutine.php)
instances so that they may be executed by the kernel. Coroutines are executed on the current strand, and as such
execution of the caller is only resumed once the yielded coroutine has completed.

```php
function hello()
{
    echo 'Hello, ';
    yield Recoil::noop();
}

function world()
{
    echo 'world!' . PHP_EOL;
    yield Recoil::noop();
}

Recoil::run(
    function () {
        yield hello();
        yield world();
    }
);
```

### Returning a value from a coroutine

#### PHP 7

To return a value from a coroutine, simply use the `return` keyword as you would in a normal function.

```php
function multiply($a, $b)
{
    yield Recoil::noop();
    return $a * $b;
    echo 'This code is never reached.';
}

Recoil::run(
    function () {
        $result = (yield multiply(3, 7));
        echo '3 * 7 is ' . $result . PHP_EOL;
    }
);
```

#### PHP 5

Because the `return` keyword can not be used to return a value inside a generator before PHP version 7, the kernel API provides
`Recoil::return_()` to send a value to the calling coroutine. Just like `return`, execution of the coroutine stops when a
value is returned.

```php
function multiply($a, $b)
{
    yield Recoil::return_($a * $b);
    echo 'This code is never reached.';
}
```

### Throwing and catching exceptions

One of the major advantages made available by coroutines is that errors can be reported using familiar exception
handling techniques. The `throw` keyword can be used in the standard way inside PHP generators in both PHP version 5 and 7.

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

`Recoil::throw_()` is equivalent to a `throw` statement, except that the presence of `yield` forces PHP to parse the
function as a generator.

```php
function onlyThrow()
{
    yield Recoil::throw_(new Exception('Not implemented!'));
}
```

## Cooperating with React

**Recoil** includes several features to allow interoperability with React and conventional React applications.

### Streams

[React streams](https://github.com/reactphp/stream) can be adapted into **Recoil** streams using [ReadableReactStream](src/Stream/ReadableReactStream.php)
and [WritableReactStream](src/Stream/WritableReactStream.php).

### Promises

[React promises](https://github.com/reactphp/promise) can be yielded directly from a coroutine. The promise is
adapted into a [PromiseCoroutine](src/Coroutine/PromiseCoroutine.php) instance and the calling coroutine is resumed once
the promise has been fulfilled.

If the promise is resolved, the resulting value is returned from the yield statement. If it is rejected, the yield
statement throws an exception describing the error. If a strand is waiting on the resolution of a cancellable promise,
and execution of that strand is terminated the promise is cancelled. **Recoil** does not yet support progress events.

The [promise-dns example](examples/promise-dns) demonstrates using the [React DNS component](https://github.com/reactphp/dns),
a promised-based API, to resolve several domain names concurrently. [This example](examples/promise-dns-react) shows the
same functionality implemented without **Recoil**.

### Callback and Events

Conventional asynchronous code uses callback functions to inform a caller when a result is available or an event occurs.
The kernel API provides `Recoil::callback()` to create a callback that executes a coroutine on its own strand.

```php
use Evenement\EventEmitter;

Recoil::run(
    function () {
        $eventEmitter = new EventEmitter();
        $eventEmitter->on(
            'hello'
            (yield Recoil::callback(
                function ($name) {
                    echo 'Hello, ' . $name . '!' . PHP_EOL;
                    yield Recoil::noop();
                }
            ))
        );
    }
);
```

### Using an existing event-loop

In all of the examples above, the `Recoil::run()` convenience function is used to start the kernel. Internally this
function chooses an appropriate event-loop implementation, instantiates the kernel, enqueues the given function for
execution and runs the event-loop.

An existing event-loop can be used by passing it as the second parameter.

```php
$eventLoop = new React\EventLoop\StreamSelectLoop;

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
$eventLoop = new React\EventLoop\StreamSelectLoop;

$kernel = new Recoil\Kernel\StandardKernel($eventLoop);

$coroutine = function () {
    echo 'Hello, world!' . PHP_EOL;
    yield Recoil::noop();
};

$kernel->execute($coroutine());

$eventLoop->run();
```

## Contact us

* Follow [@IcecaveStudios](https://twitter.com/IcecaveStudios) on Twitter
* Visit the [Icecave Studios website](http://icecave.com.au)
* Join `#icecave` on [irc.freenode.net](http://webchat.freenode.net?channels=icecave)

<!-- references -->
[Build Status]: http://img.shields.io/travis/recoilphp/recoil/develop.svg?style=flat-square
[Test Coverage]: http://img.shields.io/coveralls/recoilphp/recoil/develop.svg?style=flat-square
[SemVer]: http://img.shields.io/:semver-0.4.0-yellow.svg?style=flat-square
