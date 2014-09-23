# Recoil Changelog

### 0.2.0 (2014-09-23)

To faciliate several performance improvements the following backwards compatibility breaking changes have been introduced:

* **[BC]** `CoroutineInterface` no longer implements `EventEmitterInterface` - several unused events were fired every time a co-routine was called
* **[BC]** `Recoil::finalize()` now only works with generated based co-routines - this was previously implemented using the aforementioned events

### 0.1.0 (2014-02-04)

* Initial release
