<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * Public-facing functionality of the plugin.
 *
 * Primary shortcode: [showcase-publications]
 * This is a NEW plugin (v1.0.0) - no legacy shortcodes supported
 */

class ACADPUSH_Public {
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__).'css/aps-public.css', array(), $this->version . '-' . filemtime(plugin_dir_path(__FILE__) . 'css/aps-public.css'), 'all');

		$options = get_option( $this->plugin_name );

		// Enqueue Google Fonts if needed
		if ( ! empty( $options['font_family'] ) ) {
			$font = $options['font_family'];
			$allowed_gfonts = array( 'Inter', 'Roboto', 'Open Sans', 'Merriweather' );
			if ( in_array( $font, $allowed_gfonts, true ) ) {
				wp_enqueue_style(
					$this->plugin_name . '-google-font',
					'https://fonts.googleapis.com/css2?family=' . rawurlencode( $font ) . ':wght@400;600;700&display=swap',
					array(),
					$this->version
				);
			}
		}

		// Inject dynamic design CSS here, before wp_head fires, so it is actually output.
		// wp_add_inline_style() must be called during wp_enqueue_scripts, not inside a shortcode callback.
		$primary_color = isset( $options['primary_color'] )     ? $options['primary_color']     : '#2271b1';
		$card_bg       = isset( $options['card_bg_color'] )     ? $options['card_bg_color']     : '#ffffff';
		$card_border   = isset( $options['card_border_color'] ) ? $options['card_border_color'] : '#dcdcde';
		$text_color    = isset( $options['text_color'] )        ? $options['text_color']        : '#1d2327';
		$meta_color    = isset( $options['meta_text_color'] )   ? $options['meta_text_color']   : '#646970';
		$font_family   = isset( $options['font_family'] )       ? $options['font_family']       : 'system';
		$font_size     = isset( $options['font_size'] )         ? $options['font_size']         : 'medium';
		$border_radius = isset( $options['border_radius'] )     ? $options['border_radius']     : 'medium';

		$radius_map   = array( 'none' => '0', 'small' => '4px', 'medium' => '8px', 'large' => '12px' );
		$radius_value = isset( $radius_map[ $border_radius ] ) ? $radius_map[ $border_radius ] : '8px';

		$size_map  = array( 'small' => '0.875rem', 'medium' => '1rem', 'large' => '1.125rem' );
		$base_size = isset( $size_map[ $font_size ] ) ? $size_map[ $font_size ] : '1rem';

		$allowed_fonts = array( 'system', 'Inter', 'Roboto', 'Open Sans', 'Merriweather' );
		if ( ! in_array( $font_family, $allowed_fonts, true ) ) {
			$font_family = 'system';
		}

		$safe_font  = esc_attr( $font_family );
		$font_stack = $font_family === 'system'
			? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif'
			: "'" . $safe_font . "', sans-serif";

		$acadpush_inline_css = '.dyzc-publications-grid {
			--dyzc-primary: ' . esc_attr( $primary_color ) . ';
			--dyzc-card-bg: ' . esc_attr( $card_bg ) . ';
			--dyzc-card-border: ' . esc_attr( $card_border ) . ';
			--dyzc-text: ' . esc_attr( $text_color ) . ';
			--dyzc-meta: ' . esc_attr( $meta_color ) . ';
			--dyzc-radius: ' . esc_attr( $radius_value ) . ';
			--dyzc-base-size: ' . esc_attr( $base_size ) . ';
			font-family: ' . esc_attr( $font_stack ) . ';
			font-size: ' . esc_attr( $base_size ) . ';
			/* Elementor fallback protection */
			max-width: 1200px;
			margin: 0 auto;
			padding: 20px;
		}
		.dyzc-publications-grid .dyzc-pub-card {
			margin-bottom: 40px !important;
		}
		.dyzc-publications-grid .dyzc-pub-actions {
			display: flex !important;
			gap: 12px !important;
		}
		.dyzc-publications-grid .dyzc-btn {
			display: inline-flex !important;
			align-items: center;
			padding: 10px 18px !important;
			text-decoration: none !important;
		}';
		wp_add_inline_style( $this->plugin_name, $acadpush_inline_css );
	}

	public function enqueue_scripts() {
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__).'js/aps-public.js', array('jquery'), $this->version . '-' . filemtime(plugin_dir_path(__FILE__) . 'js/aps-public.js'), true);
	}

	public function zp_query_vars( $query_vars ) {
		$query_vars[] = 'zenodo_page';
		$query_vars[] = 'pub_year'; // year chosen in the year selector ("year" is reserved by WordPress)
		return $query_vars;
	}

	public function display_zenodo_data( $attr ) {
		$attr = shortcode_atts( array(
			'keyword' => '',
			'lang' => '',
			'type' => '',      // General type: publication, dataset, software, etc.
			'subtype' => '',   // Specific subtype: article, book, conferencepaper, etc.
			'view' => '',      // cards (default) | compact
			'year' => '',      // fixed year "2024" or range "2015-2020"
			'year_filter' => '' // "yes" shows a year selector above the list
		), $attr, 'showcase-publications' );

		$view = strtolower( trim( $attr['view'] ) ) === 'compact' ? 'compact' : 'cards';

		list( $range_from, $range_to ) = $this->parse_year_range( $attr['year'] );
		$show_year_filter = in_array( strtolower( trim( $attr['year_filter'] ) ), array( '1', 'yes', 'true', 'on' ), true );

		// The selector's choice only applies to shortcodes that show the selector,
		// and only inside the range the shortcode allows.
		$selected_year = 0;
		if ( $show_year_filter ) {
			$requested = (string) get_query_var( 'pub_year', '' );
			if ( preg_match( '/^\d{4}$/', $requested ) ) {
				$requested = (int) $requested;
				if ( ( ! $range_from || $requested >= $range_from ) && ( ! $range_to || $requested <= $range_to ) ) {
					$selected_year = $requested;
				}
			}
		}
		$year_from = $selected_year ? $selected_year : $range_from;
		$year_to   = $selected_year ? $selected_year : $range_to;

		$locale = strtolower( get_locale() );
		$lang   = 'en';
		if ( strpos( $locale, 'cs' ) === 0 ) {
			$lang = 'cs';
		} elseif ( strpos( $locale, 'pt' ) === 0 ) {
			$lang = 'pt';
		}

		$allowed_langs = array( 'cs', 'en', 'pt' );
		if ( ! empty( $attr['lang'] ) && in_array( strtolower( $attr['lang'] ), $allowed_langs, true ) ) {
			$lang = strtolower( $attr['lang'] );
		}

		$shortcode_specific_keyword = ! empty( $attr['keyword'] ) ? sanitize_text_field( $attr['keyword'] ) : '';
		$shortcode_specific_type = ! empty( $attr['type'] ) ? sanitize_text_field( $attr['type'] ) : '';
		$shortcode_specific_subtype = ! empty( $attr['subtype'] ) ? sanitize_text_field( $attr['subtype'] ) : '';

		// Validate type against Zenodo's controlled vocabulary
		$allowed_types = array(
			'publication', 'poster', 'presentation', 'dataset',
			'image', 'video', 'software', 'lesson',
			'physicalobject', 'other'
		);

		if ( ! empty( $shortcode_specific_type ) && ! in_array( $shortcode_specific_type, $allowed_types, true ) ) {
			$shortcode_specific_type = ''; // Invalid type, ignore
		}

		// Validate subtype (only publication subtypes for now)
		$allowed_publication_subtypes = array(
			'annotationcollection', 'book', 'section', 'conferencepaper',
			'datamanagementplan', 'article', 'patent', 'preprint',
			'deliverable', 'milestone', 'proposal', 'report',
			'softwaredocumentation', 'taxonomictreatment', 'technicalnote',
			'thesis', 'workingpaper', 'other'
		);

		// Only validate subtype if it's provided
		if ( ! empty( $shortcode_specific_subtype ) ) {
			if ( ! in_array( $shortcode_specific_subtype, $allowed_publication_subtypes, true ) ) {
				$shortcode_specific_subtype = ''; // Invalid subtype, ignore
			}
		}

		$options = get_option($this->plugin_name);
		$page = max(1, (int)get_query_var('zenodo_page', 1));

		$api_response = $this->zp_retrieve_json($page, $options, $shortcode_specific_keyword, $shortcode_specific_type, $shortcode_specific_subtype, $year_from, $year_to);

		$wrapper_open = '<div class="dyzc-publications-grid dyzc-view-' . esc_attr( $view ) . ' lang-' . esc_attr( $lang ) . '">';

		// The year selector is shown even when the chosen year has no results,
		// so the visitor can pick another one.
		$toolbar = '';
		if ( $show_year_filter ) {
			$years = $this->retrieve_years( $options, $shortcode_specific_keyword, $shortcode_specific_type, $shortcode_specific_subtype, $range_from, $range_to );
			if ( ! is_wp_error( $years ) && ! empty( $years ) ) {
				$toolbar = $this->render_year_filter( $years, $selected_year, $lang );
			}
		}

		if ( is_wp_error( $api_response ) ) {
			return '<div class="dyzc-error"><p>' . esc_html__( 'Unable to load publications. Please try again later.', 'yacadpush' ) . '</p></div>';
		}

		if ( ! $api_response || ! isset( $api_response->hits->hits ) || empty( $api_response->hits->hits ) ) {
			$no_results = '<div class="dyzc-no-results"><p>' . esc_html( $this->get_label( 'no_results', $lang ) ) . '</p></div>';
			return $toolbar ? $wrapper_open . $toolbar . $no_results . '</div>' : $no_results;
		}

		$html = '';

		$total_docs = is_object($api_response->hits->total) ? $api_response->hits->total->value : $api_response->hits->total;
		$per_page = isset($options['number_publications']) ? max(1, (int)$options['number_publications']) : 10;
		$nb_pages = ceil($total_docs / $per_page);

		$html .= $wrapper_open . $toolbar;

		if ( $view === 'compact' ) {
			// Year headings only help when the list spans more than one year.
			$group_by_year = ! ( $year_from && $year_from === $year_to );
			$html .= $this->render_compact_list( $api_response->hits->hits, $lang, $group_by_year );
			$html .= $this->render_pagination( $page, $nb_pages, $lang );
			// One dialog per list; the script fills it with the clicked item's card.
			$html .= '<dialog class="dyzc-card-dialog" aria-label="' . esc_attr( $this->get_label( 'view_publication', $lang ) ) . '">'
				. '<button type="button" class="dyzc-dialog-close" aria-label="' . esc_attr( $this->get_label( 'close', $lang ) ) . '">&times;</button>'
				. '<div class="dyzc-dialog-body"></div></dialog>';
			$html .= '</div>';
			return $html;
		}

		foreach ($api_response->hits->hits as $hit) {
			if (empty($hit->metadata)) continue;
			$html .= $this->render_card( $hit, $lang );
		}

		$html .= $this->render_pagination($page, $nb_pages, $lang);
		$html .= '</div>'; // .dyzc-publications-grid

		return $html;
	}

	/**
	 * One publication as a card. $expanded shows the whole abstract and every
	 * keyword without the "Show more" toggle (used in the compact view's dialog).
	 */
	private function render_card( $hit, $lang, $expanded = false ) {
		$html = '';
		$m    = $hit->metadata;

		$html .= '<article class="dyzc-pub-card">';

		// Title
		$html .= '<h3 class="dyzc-pub-title">'.esc_html($m->title).'</h3>';

		// Meta info (date, type, venue)
		$meta_parts = array();
		if (!empty($m->publication_date)) {
			$meta_parts[] = $this->format_date($m->publication_date, $lang);
		}
		if (!empty($m->resource_type->title)) {
			$meta_parts[] = esc_html($m->resource_type->title);
		}
		$venue = $this->venue_name( $m );
		if ($venue) {
			$meta_parts[] = '<em class="dyzc-pub-venue">' . esc_html($venue) . '</em>';
		}
		if (!empty($meta_parts)) {
			$html .= '<div class="dyzc-pub-meta">' . wp_kses_post( implode(' • ', $meta_parts) ) . '</div>';
		}

		// Authors
		if (!empty($m->creators)) {
			$html .= '<div class="dyzc-pub-authors">' . $this->render_authors( $m->creators ) . '</div>';
		}

		// Abstract (collapsible)
		if (!empty($m->description)) {
			$clean_desc = wp_strip_all_tags($m->description);
			$short_desc = mb_strlen($clean_desc) > 200 ? mb_substr($clean_desc, 0, 200) . '…' : $clean_desc;

			$html .= '<div class="dyzc-pub-abstract">';
			if ( $expanded ) {
				$html .= '<p class="dyzc-abstract-full">' . esc_html($clean_desc) . '</p>';
			} else {
				$html .= '<p class="dyzc-abstract-short">' . esc_html($short_desc) . '</p>';
			}
			if ( ! $expanded && mb_strlen($clean_desc) > 200 ) {
				$html .= '<p class="dyzc-abstract-full" style="display:none;">' . esc_html($clean_desc) . '</p>';
				$html .= '<button class="dyzc-toggle-abstract" data-expanded="false"'
					. ' data-label-more="' . esc_attr( $this->get_label('show_more', $lang) ) . '"'
					. ' data-label-less="' . esc_attr( $this->get_label('show_less', $lang) ) . '">'
					. esc_html( $this->get_label('show_more', $lang) ) . '</button>';
			}
			$html .= '</div>';
		}

		// Badges (tags/keywords)
		if (!empty($m->keywords)) {
			$html .= '<div class="dyzc-pub-badges">';
			$shown_keywords = $expanded ? $m->keywords : array_slice($m->keywords, 0, 5);
			foreach ($shown_keywords as $keyword) {
				$html .= '<span class="dyzc-badge">' . esc_html($keyword) . '</span>';
			}
			$html .= '</div>';
		}

		// Action buttons
		$html .= '<div class="dyzc-pub-actions">';

		// DOI link
		if (!empty($hit->doi) || !empty($hit->links->doi)) {
			$doi_url = !empty($hit->links->doi) ? $hit->links->doi : 'https://doi.org/' . $hit->doi;
			$html .= '<a href="' . esc_url($doi_url) . '" class="dyzc-btn dyzc-btn-primary" target="_blank" rel="noopener">';
			$html .= esc_html( $this->get_label('view_publication', $lang) );
			$html .= '</a>';
		}

		// PDF direto (fonte: banco próprio via ieee_pdf_url, ou Zenodo quando disponível)
		if (!empty($hit->links->pdf)) {
			$html .= '<a href="' . esc_url($hit->links->pdf) . '" class="dyzc-btn dyzc-btn-pdf" target="_blank" rel="noopener">';
			$html .= esc_html( $this->get_label('view_pdf', $lang) );
			$html .= '</a>';
		}

		// Zenodo / repositório link
		if (!empty($hit->links->self_html) || !empty($hit->links->latest_html)) {
			$zenodo_url = !empty($hit->links->self_html) ? $hit->links->self_html : $hit->links->latest_html;
			$html .= '<a href="' . esc_url($zenodo_url) . '" class="dyzc-btn dyzc-btn-secondary" target="_blank" rel="noopener">';
			$html .= esc_html( $this->get_label('view_repository', $lang) );
			$html .= '</a>';
		}

		$html .= '</div>'; // .dyzc-pub-actions
		$html .= '</article>'; // .dyzc-pub-card
		return $html;
	}

	private function zp_retrieve_json($page, $options, $keyword_override, $type_filter = '', $subtype_filter = '', $year_from = 0, $year_to = 0) {
		$choice        = isset($options['choice']) ? $options['choice'] : 'community';
		$extra_keyword = isset($options['extra_keyword']) ? trim($options['extra_keyword']) : '';
		$size          = isset($options['number_publications']) ? max(1, min(100, (int)$options['number_publications'])) : 10;
		$final_keyword = !empty($keyword_override) ? $keyword_override : $extra_keyword;

		// -----------------------------------------------------------------------
		// FONTE: banco de dados próprio
		// -----------------------------------------------------------------------
		if ( $choice === 'database' ) {
			$db = new ACADPUSH_DB();
			if ( ! $db->connect( $options ) ) {
				return new WP_Error( 'db_connect', $db->connect_error );
			}

			// The data version is part of the key, so reloading the database
			// with json_to_db.py invalidates the cache without touching WordPress.
			$transient_key = 'acadpush_db_' . md5( wp_json_encode( array(
				$db->data_version(), $final_keyword, $type_filter, $subtype_filter, $page, $size, $year_from, $year_to,
			) ) );
			$cached = get_transient( $transient_key );
			if ( $cached !== false ) {
				$db->close();
				return $cached;
			}

			$data = $db->query_publications( $page, $size, $final_keyword, $type_filter, $subtype_filter, (int) $year_from, (int) $year_to );
			$db->close();

			if ( is_wp_error( $data ) ) {
				return $data;
			}

			set_transient( $transient_key, $data, HOUR_IN_SECONDS );
			return $data;
		}

		// -----------------------------------------------------------------------
		// FONTE: Zenodo (community ou orcid) — comportamento original
		// -----------------------------------------------------------------------
		$id           = isset($options['id_community_orcid']) ? trim($options['id_community_orcid']) : '';
		$access_token = isset($options['access_token']) ? trim($options['access_token']) : '';

		if (empty($id)) {
			return new WP_Error('no_id', 'No Community ID or ORCID configured.');
		}

		$transient_key = 'acadpush_' . md5($choice . $id . $final_keyword . $type_filter . $subtype_filter . $page . $size . '|' . $year_from . '|' . $year_to);
		$cached = get_transient($transient_key);
		if ($cached !== false) {
			return $cached;
		}

		$url = $this->zenodo_search_url( $choice, $id, $final_keyword, $type_filter, $subtype_filter, $year_from, $year_to, $size, $page, 'newest' );

		$data = $this->zenodo_get( $url, $access_token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		set_transient($transient_key, $data, HOUR_IN_SECONDS);
		return $data;
	}

	/**
	 * Zenodo records search URL for the plugin's filters.
	 */
	private function zenodo_search_url( $choice, $id, $keyword, $type_filter, $subtype_filter, $year_from, $year_to, $size, $page, $sort ) {
		$terms = array();
		if ( $choice === 'orcid' ) {
			$terms[] = 'creators.orcid:"' . $id . '"';
		}
		if ( ! empty( $keyword ) ) {
			$terms[] = $keyword;
		}
		if ( $year_from || $year_to ) {
			$terms[] = 'metadata.publication_date:[' . ( $year_from ? sprintf( '%04d-01-01', $year_from ) : '*' )
				. ' TO ' . ( $year_to ? sprintf( '%04d-12-31', $year_to ) : '*' ) . ']';
		}

		$url = 'https://zenodo.org/api/records/?size=' . (int) $size . '&page=' . (int) $page . '&sort=' . rawurlencode( $sort );
		if ( $choice !== 'orcid' ) {
			$url .= '&communities=' . rawurlencode( $id );
		}
		if ( ! empty( $terms ) ) {
			// Parenthesised so a keyword with OR does not escape the other filters.
			$url .= '&q=' . rawurlencode( '(' . implode( ') AND (', $terms ) . ')' );
		}
		if ( ! empty( $type_filter ) )    { $url .= '&type='    . rawurlencode( $type_filter ); }
		if ( ! empty( $subtype_filter ) ) { $url .= '&subtype=' . rawurlencode( $subtype_filter ); }
		return $url;
	}

	/**
	 * GET a Zenodo API URL and decode the JSON.
	 *
	 * @return object|WP_Error
	 */
	private function zenodo_get( $url, $access_token ) {
		$args = array(
			'timeout'    => 30,
			'user-agent' => 'Academic Publications Showcase/' . $this->version . ' (WordPress Plugin)',
			'headers'    => array('Accept' => 'application/json'),
		);
		if (!empty($access_token)) {
			$args['headers']['Authorization'] = 'Bearer ' . $access_token;
		}

		$response = wp_remote_get($url, $args);
		if (is_wp_error($response)) { return $response; }

		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code !== 200) {
			return new WP_Error('http_error', 'HTTP ' . $status_code);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('json_error', 'Invalid JSON response');
		}

		return $data;
	}

	/**
	 * Publication years available for the year selector, newest first.
	 *
	 * Database mode: the years actually present, with counts.
	 * Zenodo modes: every year from the oldest matching record to the newest
	 * (the Zenodo API has no year aggregation), without counts.
	 *
	 * @return array<int,int|null>|WP_Error Year => count (null when unknown).
	 */
	private function retrieve_years( $options, $keyword_override, $type_filter, $subtype_filter, $range_from, $range_to ) {
		$choice        = isset( $options['choice'] ) ? $options['choice'] : 'community';
		$extra_keyword = isset( $options['extra_keyword'] ) ? trim( $options['extra_keyword'] ) : '';
		$keyword       = ! empty( $keyword_override ) ? $keyword_override : $extra_keyword;

		if ( $choice === 'database' ) {
			$db = new ACADPUSH_DB();
			if ( ! $db->connect( $options ) ) {
				return new WP_Error( 'db_connect', $db->connect_error );
			}
			$key = 'acadpush_dby_' . md5( wp_json_encode( array(
				$db->data_version(), $keyword, $type_filter, $subtype_filter, $range_from, $range_to,
			) ) );
			$cached = get_transient( $key );
			if ( $cached !== false ) {
				$db->close();
				return $cached;
			}
			$years = $db->list_years( $keyword, $type_filter, $subtype_filter, (int) $range_from, (int) $range_to );
			$db->close();
			if ( ! is_wp_error( $years ) ) {
				set_transient( $key, $years, HOUR_IN_SECONDS );
			}
			return $years;
		}

		$id           = isset( $options['id_community_orcid'] ) ? trim( $options['id_community_orcid'] ) : '';
		$access_token = isset( $options['access_token'] ) ? trim( $options['access_token'] ) : '';
		if ( empty( $id ) ) {
			return new WP_Error( 'no_id', 'No Community ID or ORCID configured.' );
		}

		$key    = 'acadpush_zy_' . md5( $choice . $id . $keyword . $type_filter . $subtype_filter . '|' . $range_from . '|' . $range_to );
		$cached = get_transient( $key );
		if ( $cached !== false ) {
			return $cached;
		}

		$bounds = array();
		foreach ( array( 'oldest', 'newest' ) as $sort ) {
			$url  = $this->zenodo_search_url( $choice, $id, $keyword, $type_filter, $subtype_filter, $range_from, $range_to, 1, 1, $sort );
			$data = $this->zenodo_get( $url, $access_token );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$date = isset( $data->hits->hits[0]->metadata->publication_date ) ? (string) $data->hits->hits[0]->metadata->publication_date : '';
			if ( ! preg_match( '/^(\d{4})/', $date, $m ) ) {
				return array();
			}
			$bounds[ $sort ] = (int) $m[1];
		}

		// sort=oldest/newest order by creation date, not publication date, so
		// widen to the shortcode's range when it is given.
		$first = min( $bounds['oldest'], $bounds['newest'] );
		$last  = max( $bounds['oldest'], $bounds['newest'], (int) gmdate( 'Y' ) );
		if ( $range_from ) { $first = $range_from; }
		if ( $range_to )   { $last  = $range_to; }

		$years = array();
		for ( $y = $last; $y >= $first; $y-- ) {
			$years[ $y ] = null;
		}
		set_transient( $key, $years, HOUR_IN_SECONDS );
		return $years;
	}

	/**
	 * Parse the `year` shortcode attribute: "2024" or "2015-2020" (either end may be empty).
	 *
	 * @return array{0:int,1:int} First and last year (0 = open).
	 */
	private function parse_year_range( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})$/', $value, $m ) ) {
			return array( (int) $m[1], (int) $m[1] );
		}
		if ( preg_match( '/^(\d{4})?\s*-\s*(\d{4})?$/', $value, $m ) ) {
			$from = ! empty( $m[1] ) ? (int) $m[1] : 0;
			$to   = ! empty( $m[2] ) ? (int) $m[2] : 0;
			if ( $from && $to && $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			return array( $from, $to );
		}
		return array( 0, 0 );
	}

	/**
	 * Year selector: a GET form that keeps the page's other query arguments.
	 */
	private function render_year_filter( $years, $selected_year, $lang ) {
		static $instance = 0;
		$instance++;
		$select_id = 'dyzc-year-' . $instance;

		$current = remove_query_arg( array( 'pub_year', 'zenodo_page' ) );
		$parts   = wp_parse_url( $current );
		$action  = isset( $parts['path'] ) ? $parts['path'] : '';
		$hidden  = '';
		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query_args );
			foreach ( $query_args as $name => $value ) {
				if ( is_scalar( $value ) ) {
					$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
				}
			}
		}

		$html  = '<form class="dyzc-year-filter" method="get" action="' . esc_url( $action ) . '">';
		$html .= $hidden;
		$html .= '<label for="' . esc_attr( $select_id ) . '">' . esc_html( $this->get_label( 'year', $lang ) ) . '</label> ';
		$html .= '<select id="' . esc_attr( $select_id ) . '" name="pub_year" class="dyzc-year-select">';
		$html .= '<option value="">' . esc_html( $this->get_label( 'all_years', $lang ) ) . '</option>';
		foreach ( $years as $year => $count ) {
			$label = $count === null ? (string) $year : $year . ' (' . (int) $count . ')';
			$html .= '<option value="' . esc_attr( $year ) . '"' . selected( (int) $year, (int) $selected_year, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select> ';
		$html .= '<noscript><button type="submit" class="dyzc-btn dyzc-btn-secondary">' . esc_html( $this->get_label( 'filter', $lang ) ) . '</button></noscript>';
		$html .= '</form>';
		return $html;
	}

	/**
	 * Compact view: one reference line per publication, optionally under year headings.
	 */
	private function render_compact_list( $hits, $lang, $group_by_year ) {
		$html         = '';
		$current_year = null;
		$list_open    = false;

		foreach ( $hits as $hit ) {
			if ( empty( $hit->metadata ) ) continue;
			$m = $hit->metadata;

			$year = '';
			if ( ! empty( $m->publication_date ) && preg_match( '/^(\d{4})/', (string) $m->publication_date, $ym ) ) {
				$year = $ym[1];
			}

			if ( ! $list_open || ( $group_by_year && $year !== $current_year ) ) {
				if ( $list_open ) {
					$html .= '</ul>';
				}
				if ( $group_by_year && $year !== '' ) {
					$html .= '<h3 class="dyzc-year-heading">' . esc_html( $year ) . '</h3>';
				}
				$html        .= '<ul class="dyzc-pub-list">';
				$list_open    = true;
				$current_year = $year;
			}

			$doi_url = '';
			if ( ! empty( $hit->links->doi ) ) {
				$doi_url = $hit->links->doi;
			} elseif ( ! empty( $hit->doi ) ) {
				$doi_url = 'https://doi.org/' . $hit->doi;
			}

			$html .= '<li class="dyzc-pub-item">';
			if ( ! empty( $m->creators ) ) {
				$authors = $this->render_authors( $m->creators );
				// "Silva, A." and "et al." already end with a period.
				$end     = substr( rtrim( wp_strip_all_tags( $authors ) ), -1 ) === '.' ? ' ' : '. ';
				$html   .= '<span class="dyzc-pub-authors">' . $authors . '</span>' . $end;
			}

			// The title opens the full card in a dialog (see aps-public.js); without
			// JavaScript it is a plain link to the DOI.
			$title = esc_html( rtrim( (string) $m->title, '. ' ) );
			$title = '<a href="' . esc_url( $doi_url ? $doi_url : '#' ) . '" class="dyzc-open-card"'
				. ( $doi_url ? ' target="_blank" rel="noopener"' : '' ) . '>' . $title . '</a>';
			$html .= '<span class="dyzc-pub-title">' . $title . '</span>';
			// Icon next to the title: straight to the publication (DOI), new tab.
			if ( $doi_url ) {
				$label = $this->get_label( 'view_publication', $lang );
				$html .= ' <a href="' . esc_url( $doi_url ) . '" class="dyzc-direct-link" target="_blank" rel="noopener"'
					. ' title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '">'
					. '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">'
					. '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
					. ' d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg></a>';
			}
			$html .= '. ';

			$source = array();
			$venue  = $this->venue_name( $m );
			if ( $venue ) {
				$source[] = '<em class="dyzc-pub-venue">' . esc_html( $venue ) . '</em>';
			} elseif ( ! empty( $m->resource_type->title ) ) {
				$source[] = esc_html( $m->resource_type->title );
			}
			foreach ( $this->venue_details( $m, $lang ) as $detail ) {
				$source[] = esc_html( $detail );
			}
			if ( $year !== '' ) {
				$source[] = esc_html( $year );
			}
			if ( ! empty( $source ) ) {
				$html .= '<span class="dyzc-pub-source">' . implode( ', ', $source ) . '</span>.';
			}

			$links = array();
			if ( ! empty( $hit->links->pdf ) ) {
				$links[] = '<a href="' . esc_url( $hit->links->pdf ) . '" target="_blank" rel="noopener">' . esc_html( $this->get_label( 'view_pdf', $lang ) ) . '</a>';
			}
			$repo = ! empty( $hit->links->self_html ) ? $hit->links->self_html : ( ! empty( $hit->links->latest_html ) ? $hit->links->latest_html : '' );
			if ( $repo ) {
				$links[] = '<a href="' . esc_url( $repo ) . '" target="_blank" rel="noopener">' . esc_html( $this->get_label( 'view_repository', $lang ) ) . '</a>';
			}
			if ( ! empty( $links ) ) {
				$html .= ' <span class="dyzc-pub-links">' . implode( ' · ', $links ) . '</span>';
			}
			// Full card for the dialog, abstract already expanded. A <template> is inert:
			// not rendered, not read by screen readers, not indexed as page text.
			$html .= '<template class="dyzc-card-template">' . $this->render_card( $hit, $lang, true ) . '</template>';
			$html .= '</li>';
		}

		if ( $list_open ) {
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Author list (first five, then "et al."), escaped.
	 */
	private function render_authors( $creators, $max_authors = 5 ) {
		$author_names = array();
		foreach ( array_slice( $creators, 0, $max_authors ) as $creator ) {
			$name = isset( $creator->name ) ? esc_html( $creator->name ) : '';
			if ( ! $name ) continue;
			if ( ! empty( $creator->affiliation ) ) {
				$author_names[] = '<span class="dyzc-author" title="' . esc_attr( $creator->affiliation ) . '">' . $name . '</span>';
			} else {
				$author_names[] = '<span class="dyzc-author">' . $name . '</span>';
			}
		}
		$html = implode( '; ', $author_names );
		if ( count( $creators ) > $max_authors ) {
			$html .= ' <span class="dyzc-et-al">et al.</span>';
		}
		return $html;
	}

	/**
	 * Where it was published: the journal, else the conference (Zenodo `meeting`).
	 */
	private function venue_name( $m ) {
		$journal = '';
		if ( isset( $m->journal->title ) && $m->journal->title !== '' ) {
			$journal = $m->journal->title;
		} elseif ( ! empty( $m->journal_title ) ) {
			$journal = $m->journal_title;
		}
		if ( $journal !== '' ) {
			return (string) $journal;
		}
		if ( ! empty( $m->meeting->title ) ) {
			$name = (string) $m->meeting->title;
			if ( ! empty( $m->meeting->place ) && stripos( $name, (string) $m->meeting->place ) === false ) {
				$name .= ' (' . $m->meeting->place . ')';
			}
			return $name;
		}
		return '';
	}

	/**
	 * Volume, issue and pages of a journal article, in the page language.
	 *
	 * @return string[]
	 */
	private function venue_details( $m, $lang ) {
		if ( empty( $m->journal ) || ! is_object( $m->journal ) ) {
			return array();
		}
		$details = array();
		if ( ! empty( $m->journal->volume ) ) {
			$details[] = $this->get_label( 'volume', $lang ) . ' ' . $m->journal->volume;
		}
		if ( ! empty( $m->journal->issue ) ) {
			$details[] = $this->get_label( 'issue', $lang ) . ' ' . $m->journal->issue;
		}
		if ( ! empty( $m->journal->pages ) ) {
			$details[] = $this->get_label( 'pages', $lang ) . ' ' . $m->journal->pages;
		}
		return $details;
	}

	private function render_pagination($page, $nb_pages, $lang) {
		if ($nb_pages <= 1) return '';

		$pagination_args = array(
			'base' => add_query_arg('zenodo_page', '%#%'),
			'current' => $page,
			'total' => $nb_pages,
			'type' => 'list',
			'prev_text' => $this->get_label('previous', $lang),
			'next_text' => $this->get_label('next', $lang),
		);

		return '<nav class="dyzc-pagination">' . wp_kses_post( paginate_links($pagination_args) ) . '</nav>';
	}

	private function format_date($date_string, $lang) {
		$date_string = trim( (string) $date_string );

		// Partial dates are common in bibliographic data. strtotime() reads a bare
		// "2025" as the time 20:25 today, so years and year-months are parsed here.
		if ( preg_match( '/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?/', $date_string, $m ) ) {
			$year      = $m[1];
			$month_num = isset( $m[2] ) ? (int) $m[2] : 0;
		} else {
			$timestamp = strtotime( $date_string );
			if ( ! $timestamp ) return esc_html( $date_string );
			$year      = gmdate( 'Y', $timestamp );
			$month_num = (int) gmdate( 'n', $timestamp );
		}

		if ( $month_num < 1 || $month_num > 12 ) {
			return esc_html( $year );
		}

		$months = array(
			'cs' => array('', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'),
			'en' => array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
			'pt' => array('', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'),
		);
		$names = isset( $months[ $lang ] ) ? $months[ $lang ] : $months['en'];

		if ( $lang === 'pt' ) {
			return esc_html( $names[ $month_num ] . ' de ' . $year );
		}
		return esc_html( $names[ $month_num ] . ', ' . $year );
	}

	private function get_label($key, $lang) {
		$labels = array(
			'cs' => array(
				'no_results'       => 'Žádné publikace nenalezeny.',
				'view_publication' => 'Publikace',
				'view_pdf'         => 'PDF',
				'view_repository'  => 'Repozitář',
				'show_more'        => 'Zobrazit více',
				'show_less'        => 'Zobrazit méně',
				'previous'         => 'Předchozí',
				'next'             => 'Další',
				'year'             => 'Rok',
				'all_years'        => 'Všechny roky',
				'filter'           => 'Filtrovat',
				'close'            => 'Zavřít',
				'volume'           => 'roč.',
				'issue'            => 'č.',
				'pages'            => 's.',
			),
			'en' => array(
				'no_results'       => 'No publications found.',
				'view_publication' => 'Publication',
				'view_pdf'         => 'PDF',
				'view_repository'  => 'Repository',
				'show_more'        => 'Show more',
				'show_less'        => 'Show less',
				'previous'         => 'Previous',
				'next'             => 'Next',
				'year'             => 'Year',
				'all_years'        => 'All years',
				'filter'           => 'Filter',
				'close'            => 'Close',
				'volume'           => 'vol.',
				'issue'            => 'no.',
				'pages'            => 'pp.',
			),
			'pt' => array(
				'no_results'       => 'Nenhuma publicação encontrada.',
				'view_publication' => 'Publicação',
				'view_pdf'         => 'PDF',
				'view_repository'  => 'Repositório',
				'show_more'        => 'Mostrar mais',
				'show_less'        => 'Mostrar menos',
				'previous'         => 'Anterior',
				'next'             => 'Próxima',
				'year'             => 'Ano',
				'all_years'        => 'Todos os anos',
				'filter'           => 'Filtrar',
				'close'            => 'Fechar',
				'volume'           => 'v.',
				'issue'            => 'n.',
				'pages'            => 'p.',
			),
		);

		$lang_labels = isset($labels[$lang]) ? $labels[$lang] : $labels['en'];
		return isset($lang_labels[$key]) ? $lang_labels[$key] : '';
	}
}
