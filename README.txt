=== YACADPUSH: Yet Another Academic Publication Showcase ===
Contributors: rmbrum, @havacekm
Tags: zenodo, publications, orcid, academic, research
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display publications from Zenodo communities, ORCID author profiles or a local MySQL database with modern card layouts and customizable designs.

== Description ==

YACADPUSH is a fork of Academic Publications Showcase 1.0.0 by Martin Haváček (itself based on Display Your Zenodo Community by DARIAH-EU). It adds a third data source: a local MySQL database fed by a Python pipeline (IEEE Xplore and CrossRef metadata), see https://github.com/rmbrum/yacadpush.

The original plugin allows you to display content from [Zenodo](https://zenodo.org/) communities or author publications using ORCID identifiers on your WordPress site. Perfect for researchers, academic institutions, and research projects who want to integrate a dynamic bibliography of their work with their website.

**Key Features:**

* Display publications from Zenodo communities or ORCID profiles
* Modern card-based layout with responsive design
* Customizable colors, fonts, and styling options
* Support for keywords/tags filtering
* Pagination for large publication lists
* Bilingual support (Czech and English)
* Caching for improved performance

This plugin requires access to the [Zenodo REST API](https://developers.zenodo.org/). When using this plugin, you must accept Zenodo's [Privacy Policy](https://about.zenodo.org/privacy-policy/) and [Terms of Use](https://about.zenodo.org/terms/).

**Based on Original Work:**

This plugin is based on "Display Your Zenodo Community" originally developed by DARIAH-EU. It has been significantly enhanced with modern design, additional features, and improved functionality.

Original plugin: https://wordpress.org/plugins/display-your-zenodo-community/

**Project Funding:**

Developed as part of the project "Research of Excellence on Digital Technologies and Wellbeing"
Project Registration Number: CZ.02.01.01/00/22_008/0004583
Operational Program: The Johannes Amos Comenius
Co-funded by the European Union

== Installation ==

**Via WordPress Admin:**

1. Go to Plugins → Add New
2. Search for "Academic Publications Showcase"
3. Click "Install Now" and then "Activate"

**Manual Installation:**

1. Download the plugin ZIP file
2. Upload the `academic-publications-showcase` directory to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

**Configuration:**

1. Go to Settings → Publications in your WordPress admin
2. In the **API Settings** tab:
   - Choose between "Community" or "ORCID" mode
   - Enter your Community ID (e.g., `digiwell`) or ORCID (e.g., `0000-0002-1276-2702`)
   - Add your Zenodo Access Token (recommended for full functionality)
   - Optionally add keywords to filter results
   - Set the number of publications per page

3. In the **Design** tab (optional):
   - Customize colors (primary, card background, borders, text)
   - Choose font family and size
   - Adjust border radius

4. Add the shortcode `[showcase-publications]` to any page or post where you want to display publications

== Usage ==

**Basic Shortcode:**

`[showcase-publications]`

This displays publications using the settings configured in the admin panel.

**Shortcode with Attributes:**

`[showcase-publications keyword='WP3']`

Filter publications by a specific keyword or tag.

`[showcase-publications type='publication' subtype='article']`

Filter by publication type (journal articles only).

`[showcase-publications type='dataset']`

Show only datasets.

`[showcase-publications type='publication' subtype='conferencepaper' keyword='AI']`

Combine filters (AI-related conference papers only).

`[showcase-publications lang='en']`

Force display in English (default auto-detects from WordPress locale).

`[showcase-publications view='compact' year_filter='yes']`

Compact list: one reference line per publication (authors, title, venue, year), grouped under year
headings, with a year selector above it. Clicking a line opens the full card (whole abstract)
in a dialog; the icon next to the title opens the publication itself. In database mode the selector lists the years present, with
counts; in Zenodo modes it lists every year from the oldest deposited record to the current year.

`[showcase-publications year='2015-2020']`

Only publications from a year (`2024`) or a range of years (`2015-2020`, `2010-`).

`[showcase-publications view='compact' citation='abnt' names='initials' citation_select='yes']`

Citation style of the compact list. `citation` is one of:

- `simple` (default): authors, title, venue, volume/issue/pages, year; five authors, then "et al.";
- `abnt` (ABNT NBR 6023:2025): every author;
- `apa` (APA 7th): up to 20 authors, then the first 19, ". . ." and the last;
- `ieee`: up to six authors, then the first author and "et al.";
- `vancouver` (NLM/ICMJE): up to six authors, then "et al.";
- `chicago` (Chicago author-date, 17th): up to ten authors, then the first seven and "et al.".

`names='initials'` abbreviates given names ("SILVA, J. C."). It applies to `abnt`, `chicago` and `simple`;
`apa`, `ieee` and `vancouver` always use initials, as their rules require. `max_authors='3'` overrides
the style's author rule: three authors, then "et al.". `citation_select='yes'` shows a selector so the
visitor can switch styles (query argument `pub_cite`).

Name handling: names are read as stored ("Family, Given"). Names without a comma are read as
"Given Family". ABNT enters a name by its last surname (NBR 6023:2025, 8.1.1): "Furlanetto de
Azambuja, José Rodrigo" becomes "AZAMBUJA, José Rodrigo Furlanetto de". The rest of the surname
follows the given names. Portuguese and Spanish particles (da, de, dos, e, del, y...) that stood
before the entry go to the end: "de Moura, Rafael" becomes "MOURA, Rafael de". Some words stay with
the entry: Júnior, Filho, Neto and Sobrinho with the word before them ("ROSA JÚNIOR"), and foreign
prefixes ("DAL BEM", "LA ROSA", "VAN DER PLAS"). Surnames that must not be split are listed in
Settings, Design, "ABNT: surnames kept whole", one per line, or passed through the
`yacadpush_abnt_compound_surnames` filter; case and accents are ignored. The norm enters Hispanic
names by both surnames ("GARCÍA MÁRQUEZ"), and the plugin cannot tell a Hispanic name apart, so list
them there. With `names='initials'`, ABNT leaves the particles out ("AZAMBUJA, J. R. F.");
`particles='keep'` writes them ("AZAMBUJA, J. R. F. de"). The other styles keep the surname as
stored and leave particles out of the initials. Titles are printed as stored: there is no conversion
to sentence case or title case, and journal names are not abbreviated.

`[showcase-publications view='compact' group='type' per_page='100' year_filter='yes']`

`per_page` sets the number of publications per page for this shortcode. Without it, the shortcode uses the
API Settings value. The maximum is 1000 in database mode and 100 in Zenodo modes, which is the API limit.
`group='type'` adds sub-headings inside each year of the compact list, in this order: Journal articles,
Conference papers, Book chapters, Books, Preprints, Reports, Theses, then Other publications. The
sub-headings are in the page language. In database mode each one shows its count for the whole year,
and the list is ordered so that a page break never mixes the groups. In Zenodo modes the grouping only
reorders the current page, without counts.

**Database credentials outside wp_options**

The Local Database tab stores its values in the WordPress database. To keep them in `wp-config.php`
instead, define any of these constants; each one overrides its form field:

    define( 'YACADPUSH_DB_HOST', 'localhost' );
    define( 'YACADPUSH_DB_PORT', 3306 );
    define( 'YACADPUSH_DB_NAME', 'publications' );
    define( 'YACADPUSH_DB_USER', 'publications_ro' );
    define( 'YACADPUSH_DB_PASSWORD', '...' );

On a multisite network, `wp-config.php` is shared by every site, so the constants apply to all of them.
For per-site values, use the `yacadpush_db_credentials` filter, for example from a mu-plugin:

    add_filter( 'yacadpush_db_credentials', function ( $creds, $options ) {
        if ( get_current_blog_id() === 42 ) {
            $creds['name'] = 'publications';
            $creds['user'] = 'publications_ro';
            $creds['password'] = '...';
        }
        return $creds;
    }, 10, 2 );

The filter receives the array `host`, `port`, `name`, `user`, `password` after the constants have been
applied, and it runs last. The Local Database tab lists the fields that come from either source. The plugin
only reads the database, so give it a MySQL user with `SELECT` privilege only.

**Available Type Values:**

*General Types (use with `type` parameter):*
- `publication` - Publications (use with subtype for specific type)
- `dataset` - Datasets
- `software` - Software
- `poster` - Posters
- `presentation` - Presentations
- `video` - Video/Audio
- `image` - Images
- `lesson` - Lessons
- `physicalobject` - Physical objects
- `other` - Other

*Publication Subtypes (use with `subtype` when type='publication'):*
- `article` - Journal article
- `book` - Book
- `section` - Book section
- `conferencepaper` - Conference paper
- `thesis` - Thesis
- `report` - Report
- `preprint` - Preprint
- `datamanagementplan` - Data management plan
- `proposal` - Proposal
- `workingpaper` - Working paper
- `technicalnote` - Technical note
- `softwaredocumentation` - Software documentation
- `patent` - Patent
- `deliverable` - Project deliverable
- `milestone` - Project milestone
- `other` - Other

**Multiple Shortcodes:**

You can use multiple shortcodes on different pages with different filters:

`[showcase-publications keyword='project-a']`
`[showcase-publications type='publication' subtype='article']`

== Frequently Asked Questions ==

= Do I need a Zenodo account? =

Not necessarily. If you're displaying a public Zenodo community, you don't need an account. However, if you want to access private/restricted records or use ORCID mode, you'll need a Zenodo Access Token.

= How do I get a Zenodo Access Token? =

1. Log in to [Zenodo.org](https://zenodo.org/)
2. Go to Account Settings → Applications → Personal Access Tokens
3. Create a new token with appropriate permissions
4. Copy the token and paste it in the plugin settings

= Can I display my publications by name instead of ORCID? =

Unfortunately, no. Zenodo API queries do not work reliably with author names since they can't differentiate between authors with similar names. ORCID provides a unique identifier for each researcher.

= How do I find my Zenodo Community ID? =

Your Community ID is the part after `/communities/` in your community URL. For example, if your community URL is `https://zenodo.org/communities/digiwell`, your Community ID is `digiwell`.

= Can I customize the appearance? =

Yes! Go to Settings → Publications → Design tab to customize colors, fonts, font sizes, and border radius. The plugin uses modern CSS custom properties for easy theming.

= Does the plugin cache API results? =

Yes, the plugin caches Zenodo API responses for 1 hour to improve performance and reduce API calls. The cache is automatically cleared after updates.

== Privacy ==

This plugin connects to external services:

* **Zenodo API** (zenodo.org) to retrieve publication data
* **Google Fonts** (fonts.googleapis.com) if custom fonts are selected in settings

No user tracking or analytics are performed by this plugin. However, when accessing Zenodo, you are subject to Zenodo's privacy policy.

== Screenshots ==

1. Modern card-based publication display with collapsible abstracts
2. Inserting the shortcode via the WordPress Gutenberg block editor
3. API Settings tab - Configure Community ID or ORCID
4. Design Settings tab - Customize colors and typography
5. Usage tab - Shortcode examples and documentation

== Changelog ==

= 1.0.0 =
* Initial release as "Academic Publications Showcase"
* Completely rewritten with modern card-based layout
* Added design customization options (colors, fonts, spacing)
* Added font size control (Small, Medium, Large)
* Added border radius control
* Implemented transient caching (1 hour) for API responses
* Full error handling for API requests
* Improved ORCID mode support
* Bilingual support (Czech and English)
* Collapsible abstracts with "Show more/less" functionality
* Removed emoji icons from buttons
* Improved spacing and visual design
* Primary shortcode: `[showcase-publications]`
* GPL v2 or later license
* Based on "Display Your Zenodo Community" by DARIAH-EU

== Upgrade Notice ==

= 1.0.0 =
First release of Academic Publications Showcase. This is a new plugin based on the original "Display Your Zenodo Community" by DARIAH-EU, with significant enhancements and modern design.

== Credits ==

This plugin is based on "Display Your Zenodo Community" originally developed by DARIAH-EU.

Original plugin: https://wordpress.org/plugins/display-your-zenodo-community/
Original author: Yoann Moranville (DARIAH-EU)

Developed by Martin Haváček as part of the project "Research of Excellence on Digital Technologies and Wellbeing"
Project Registration Number: CZ.02.01.01/00/22_008/0004583
Operational Program: The Johannes Amos Comenius
Co-funded by the European Union

**Trademark Notices:**

Zenodo is a service operated by CERN. This plugin is not affiliated with or endorsed by CERN.
ORCID is a registered trademark of ORCID, Inc.
