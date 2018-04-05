# Changelog

## 1.0.1 (2018-04-06)

- **[FIX]** Handle `fwrite()` error conditions indicated by `0` return value ([recoilphp/react#6](https://github.com/recoilphp/react#6))

## 1.0.0 (2017-10-18)

This is the first stable release of `recoil/recoil`. There have been no changes
to the API since the `1.0.0-alpha.2` release.

## 1.0.0-alpha.2 (2016-12-16)

- **[NEW]** Add `select()` API operation

## 1.0.0-alpha.1 (2016-12-16)

**UPGRADE WITH CAUTION**

As of this version, the `recoil/recoil` package only contains the
"reference kernel", which is an implementation of the kernel with no external
dependencies. As such, it can not be used to execute ReactPHP code.

The ReactPHP kernel is still available in the [recoil/react](https://github.com/recoilphp/react)
package.

Libraries and applications should be developed against the interfaces and
classes provided by [recoil/api](https://github.com/recoilphp/api). The intent
is to keep these interfaces as stable as possible across the various kernel
implementations and versions.

- **[BC]** Moved the ReactPHP-based kernel to the [recoil/react](https://github.com/recoilphp/react) package
- **[BC]** Moved the public interfaces to the [recoil/api](https://github.com/recoilphp/api) package
- **[BC]** Moved kernel implementation details to the [recoil/kernel](https://github.com/recoilphp/kernel) package

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
