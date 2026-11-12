## [2.2.0] - 2026-11-12

### Added
- Citation styles for the compact view: `citation="abnt|apa|ieee|vancouver|chicago"` (default
  `simple`, the 2.1.0 line). Each style follows its own author-list rule and punctuation, and
  shows the DOI. Formatting lives in `public/class-aps-citation.php` (`ACADPUSH_Citation`).
- `names="initials"` abbreviates given names in ABNT, Chicago and simple (APA, IEEE and
  Vancouver always abbreviate). In ABNT, a Portuguese or Spanish particle that starts the family
  name moves after the given names, and particles stay in abbreviated names.
- `max_authors="N"` overrides the style's author-list rule.
- `citation_select="yes"`: a style selector next to the year selector (query argument `pub_cite`).

### Changed
- In database mode, `resource_type` now carries `subtype` (the upload type), as Zenodo does.
- The compact line reads names without a comma as "Given Family" and prints them inverted.
- The year selector and the new style selector share one form helper and the `dyzc-toolbar` wrapper.

## [2.1.0] - 2026-11-11

### Added
- Compact view: `view="compact"` shows one reference line per publication (authors, title, venue,
  volume/issue/pages, year), grouped under year headings. Clicking a line opens the full card in a
  dialog with the abstract already expanded; an icon next to the title goes straight to the DOI.
  Without JavaScript the title is a plain DOI link.
- Year filter: `year="2024"` or `year="2015-2020"` limits the list; `year_filter="yes"` shows a
  year selector (query argument `pub_year`) that keeps working across pagination. Database mode
  lists the years present with counts; Zenodo modes list the years from the oldest deposited record.

### Changed
- Cards and the compact view show the conference (Zenodo `meeting`, or `conference_title` and
  `conference_place` in database mode) when there is no journal.
- Authors are separated by semicolons, since names are stored as "Family, Given".
- Zenodo search terms (ORCID, keyword, years) are combined in parentheses.

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
