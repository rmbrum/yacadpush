## [2.4.0] - 2026-11-13

### Changed
- ABNT follows NBR 6023:2025 (it was written for the 2018 edition; the name rules did not change).
- ABNT enters names by the last surname: "Furlanetto de Azambuja, José Rodrigo" is now
  "AZAMBUJA, José Rodrigo Furlanetto de" instead of "FURLANETTO DE AZAMBUJA, José Rodrigo". The rest of
  the surname follows the given names, and particles before the entry go to the end. Júnior, Filho,
  Neto and Sobrinho stay with the word before them, and foreign prefixes (Dal, La, Van der...) with
  the entry. The other styles are unchanged.
- ABNT with `names="initials"` leaves particles out: "AZAMBUJA, J. R. F.", "TRENTO, P. O." (2.3.0
  wrote "RAMOS, E. de A."). `particles="keep"` writes them.

### Added
- Design tab, "ABNT: surnames kept whole": surnames ABNT must not split ("Gomez Caicedo", and
  Hispanic names in general), one per line, compared ignoring case and accents. Also available as
  the `yacadpush_abnt_compound_surnames` filter.
- `particles="hide|keep"` shortcode option (ABNT with initials).

### Fixed
- ABNT with neither place nor publisher printed "[S. l.]: [s. n.]"; it is now "[S. l.: s. n.]".

## [2.3.0] - 2026-11-12

### Added
- `per_page="N"` shortcode option: publications per page for that shortcode. The maximum is 1000
  in database mode and 100 in Zenodo modes.
- `group="type"` (compact view): type sub-headings inside each year (Journal articles, Conference
  papers, Book chapters, Books, Preprints, Reports, Theses, Other), localized. In database mode they
  show the year's count, and pages are ordered by year and then type
  (`ACADPUSH_DB::count_by_year_and_type()`, `TYPE_GROUP_ORDER`).
- Database credentials from `YACADPUSH_DB_HOST`, `_PORT`, `_NAME`, `_USER` and `_PASSWORD`
  constants, and from the `yacadpush_db_credentials` filter (per-site values on multisite). The
  admin form is the fallback, and the Local Database tab lists the fields overridden.
- `json_to_db.py --prune`: deletes publications whose DOI is not in the JSON, with their child
  rows. It works with `--dry-run`, and a safety stop applies above `--prune-max` percent (default
  10) unless `--force` is given. It cannot be combined with `--status`.

### Changed
- `json_to_db.py` exits with status 1 when an entry fails, and with status 2 when the prune safety
  stop is hit. It used to exit with 0 every time.

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
