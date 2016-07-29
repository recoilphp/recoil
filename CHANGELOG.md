# Changelog

## 0.6.0 (2016-07-29)

- **[BC]** Revert addition of `Api::resume()` and `throw()` (added in 0.5.2)
- **[BC]** Added `Strand::trace()` and `setTrace()` methods
- **[NEW]** `Api::suspend()` now accepts an optional terminator callback, which is invoked if the strand is terminated before it is resumed
- **[NEW]** Added `StrandTrace` interface, a low-level strand observer to be used by debugging tools
- **[FIXED]** `Strand::send()` and `throw()` no longer fail if the strand has already exited

## 0.5.2 (2016-07-15)

- **[NEW]** Added `Api::resume()` and `throw()` to resume one strand from within another

## 0.5.1 (2016-07-12)

- **[NEW]** Added a second callback parameter to `suspend()` API method which is invoked when a suspended strand is terminated

## 0.5.0 (2016-07-11)

**UPGRADE WITH CAUTION**

This is the first release that requires PHP 7. The internals have been rewritten
from the ground up. Some features available in previous releases are no longer
available as substitute functionality has not yet been added.

There are far too many changes to list here individually, however much of the
kernel API remains the same.

- **[BC]** Channels and streams have been removed from the core package
- **[BC]** `Recoil::run()` has been removed (see `ReactKernel::start()`)

Kernel API changes:

- **[BC]** `kernel()` has been removed
- **[BC]** `eventLoop()` is only available when using `ReactKernel`
- **[BC]** `return_()` has been removed, as generators can return values in PHP 7
- **[BC]** `throw_()` has been removed
- **[BC]** `finally_()` has been removed
- **[BC]** `noop()` has been removed
- **[BC]** `stop()` has been removed
- **[BC]** `select()` now operates on PHP streams, rather than strands
- **[NEW]** Added `read()` and `write()`
- **[NEW]** Added `callback()`
- **[NEW]** Added `link()` and `unlink()`
- **[NEW]** Added `adopt()`
- **[NEW]** Added `any()`, `some()` and `first()`

## 0.4.0 (2016-03-06)

This is the final release that will operate with PHP 5. In an effort to work
towards a production ready 1.0 release, future releases will require PHP 7.

- **[BC]** Dropped `Interface` suffix from interfaces
- **[BC]** Renamed `ReadableStream` to `ReadablePhpStream`
- **[BC]** Renamed `WritableStream` to `WritablePhpStream`
- **[BC]** Renamed `CoroutineAdaptor` to `StandardCoroutineAdaptor`
- **[BC]** Renamed `KernelApi` to `StandardKernelApi`
- **[BC]** Renamed `Strand` to `StandardStrand`
- **[BC]** Renamed `StrandFactory` to `StandardStrandFactory`
- **[NEW]** Added support for Guzzle promises
- **[IMPROVED]** The callback given to `Recoil::suspend` is now optional

## 0.3.0 (2015-06-26)

- **[BC]** Removed `StrandInterface::resume()`
- **[NEW]** `return` statement can be used to return a value inside a coroutine (requires PHP 7)
- **[IMPROVED]** Improved method documentation on `Recoil` facade (thanks @rjkip)

## 0.2.1 (2014-10-16)

- **[IMPROVED]** Added support for cancellable promises

## 0.2.0 (2014-09-23)

To faciliate several performance improvements the following backwards compatibility breaking changes have been introduced:

- **[BC]** `CoroutineInterface` no longer implements `EventEmitterInterface` - several unused events were fired every time a coroutine was called
- **[BC]** `Recoil::finalize()` now only works with generator based coroutines - this was previously implemented using the aforementioned events

## 0.1.0 (2014-02-04)

- Initial release
