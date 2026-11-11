## [2.0.0] - 2026-11-10

Fork renamed to **YACADPUSH: Yet Another Academic Publication Showcase**.

### Added
- Local MySQL database as a data source (`choice = database`), fed by `tools/json_to_db.py`.
- Portuguese (pt_BR) labels, dates and publication type names.

### Changed
- Main file is `yacadpush.php`; slug, text domain and settings option key are `yacadpush`
  (settings saved under the old `academic-publications-showcase` key are not migrated).
- Database results are cached under a key that includes a data version, so reloading the
  database shows up at once instead of after the 1-hour transient expires.

### Fixed
- `type`/`subtype` shortcode filters returned nothing in database mode (`type=publication`
  is now the whole publication family; `subtype` matches `upload_type`).
- Keyword search escaped the value twice and did not escape LIKE wildcards (`%`, `_`).
- A year-only publication date ("2025") was shown as the current month and year.
- "Show more/less" toggle labels now come from the server in the page language.
- `json_to_db.py`: "Unread result found" on the second entry; insert/update counters;
  IEEE PDF link (`stamp.jsp`) never detected; `--dry-run` no longer needs the MySQL driver.

## [1.0.0] - 2026-02-12
