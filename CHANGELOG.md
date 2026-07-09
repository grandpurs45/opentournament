# Changelog

All notable changes to OpenTournament will be documented in this file.

The project follows semantic versioning: `MAJOR.MINOR.PATCH`.

## [Unreleased]

### Added
- Added first-name support to bulk team import.
- Added semicolon support between player first names in bulk team import.
- Added private draft autosave on the match entry page before public score validation.
- Added public rule display to TV and mobile views.
- Opened the TV display from admin links in a new tab or window.
- Compacted the TV display to fit in one viewport without the redundant mobile-access panel.
- Added full round-robin and pools-with-finals tournament formats.
- Added pool or final-round labels to upcoming matches on the TV display.
- Improved match planning by alternating pools instead of scheduling one pool completely before the next.
- Added an admin field-by-field view for match and score tracking.

## [0.2.3] - 2026-07-08

### Added
- Added color-coded status and score-entry badges.
- Documented bulk team import.

### Changed
- Added explicit table headers to public TV match and result tables.

## [0.2.2] - 2026-07-08

### Changed
- Made the Docker host HTTP port configurable with `HTTP_PORT`.
- Made the default Docker `APP_URL` follow `HTTP_PORT`.

## [0.2.1] - 2026-07-08

### Changed
- Added a fixed Docker container name for the default Compose service.
- Ignored local Docker Compose override files.

## [0.2.0] - 2026-07-08

### Added
- Added editable tournament configuration for core settings and scoring values.
- Added bulk participant import.
- Added a V1 hardening test script.
- Added public tournament summary stats to TV and mobile views.

### Changed
- Protected pool and match regeneration behind explicit confirmation when data already exists.
- Documented `APP_URL` usage for mobile QR Codes in Docker.
- Fixed Docker build by installing SQLite development headers only when the PDO SQLite extension needs compilation.
- Removed prefilled values from the tournament creation form.

## [0.1.1] - 2026-07-08

### Changed
- Added automatic refresh to the mobile public view and standardized the TV refresh script.
- Added per-tournament QR Code generation for mobile public access.
- Documented Docker installation from the GitHub repository and update with `git pull`.

## [0.1.0] - 2026-07-08

### Added
- Initial local-first PHP/SQLite application.
- Tournament creation with selectable rule plugins.
- Generic and Molkky plugins.
- Participant management.
- Automatic pool generation.
- Round-robin match generation.
- Score validation and winner calculation.
- Automatic standings.
- Public TV display and mobile tournament view.
- CSV exports for participants, matches and standings.
- Docker and Docker Compose deployment files.
- Project roadmap and version file.
