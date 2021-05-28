# Block Injector Changelog

## 0.4.0 - 2021-05-28

### Changed

- Fixed inteval logic
- Move injection callbacks from at methods, to inject
- Bring back interval callback

## 0.3.0 - 2021-05-25

### Added

- `InjectableBehavior::EVENT_BEFORE_APPLY_RULES`
- `InjectableBehavior::EVENT_AFTER_APPLY_RULES`

### Removed

- debug setting (use event)
- copy block splitting (use event)

## 0.2.0 - 2021-05-25

### Added

- `atIndex` method for 0-based injection
- `limit`
- `offset`

### Changed

- Fixed interval behavior
- Fixed issues with negative index injection

### Removed

- `intervalCallback` as it didn't really make sense…

## 0.1.0 - 2021-05-25

### Added

- Initial dump from ProPublica repo
