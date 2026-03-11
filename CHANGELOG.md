# Changelog

## 3.1.0 - 2026-03-11

- Fixed Elementor Pro action registration by registering directly with the Forms module when available.
- Fixed false-negative Google Apps Script submissions by treating the initial Apps Script redirect as a successful delivery.
- Added safer webhook logging with masked webhook URLs in activity logs.
- Added visible submission errors for failed Google Sheets deliveries.
- Added maintenance tools and recent activity UI to the plugin settings page.
- Added bounded debug log reading to avoid loading the entire WordPress debug log in the admin page.
- Added deterministic release packaging with a versioned ZIP in `dist/`.

## 3.0.1

- Added global settings, logging, and Elementor cache clearing support.
- Fixed packaging to use the correct top-level plugin folder.
