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
		return $query_vars;
	}

	public function display_zenodo_data( $attr ) {
		$attr = shortcode_atts( array(
			'keyword' => '',
			'lang' => '',
			'type' => '',      // General type: publication, dataset, software, etc.
			'subtype' => ''    // Specific subtype: article, book, conferencepaper, etc.
		), $attr, 'showcase-publications' );

		$lang = (strpos(strtolower(get_locale()), 'cs') === 0) ? 'cs' : 'en';

		$allowed_langs = array( 'cs', 'en' );
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

		$api_response = $this->zp_retrieve_json($page, $options, $shortcode_specific_keyword, $shortcode_specific_type, $shortcode_specific_subtype);

		if ( is_wp_error( $api_response ) ) {
			return '<div class="dyzc-error"><p>' . esc_html__( 'Unable to load publications. Please try again later.', 'academic-publications-showcase' ) . '</p></div>';
		}

		if ( ! $api_response || ! isset( $api_response->hits->hits ) || empty( $api_response->hits->hits ) ) {
			return '<div class="dyzc-no-results"><p>' . esc_html( $this->get_label( 'no_results', $lang ) ) . '</p></div>';
		}

		$html = '';

		$total_docs = is_object($api_response->hits->total) ? $api_response->hits->total->value : $api_response->hits->total;
		$per_page = isset($options['number_publications']) ? max(1, (int)$options['number_publications']) : 10;
		$nb_pages = ceil($total_docs / $per_page);

		$html .= '<div class="dyzc-publications-grid lang-' . esc_attr($lang) . '">';

		foreach ($api_response->hits->hits as $hit) {
			if (empty($hit->metadata)) continue;
			$m = $hit->metadata;

			$html .= '<article class="dyzc-pub-card">';

			// Title
			$html .= '<h3 class="dyzc-pub-title">'.esc_html($m->title).'</h3>';

			// Meta info (date, journal, type)
			$meta_parts = array();
			if (!empty($m->publication_date)) {
				$meta_parts[] = $this->format_date($m->publication_date, $lang);
			}
			if (!empty($m->resource_type->title)) {
				$meta_parts[] = esc_html($m->resource_type->title);
			}
			$journal = $m->journal->title ?? $m->journal_title ?? '';
			if ($journal) {
				$meta_parts[] = '<em>' . esc_html($journal) . '</em>';
			}
			if (!empty($meta_parts)) {
				$html .= '<div class="dyzc-pub-meta">' . wp_kses_post( implode(' • ', $meta_parts) ) . '</div>';
			}

			// Authors
			if (!empty($m->creators)) {
				$html .= '<div class="dyzc-pub-authors">';
				$author_names = array();
				$max_authors = 5;
				$creator_count = count($m->creators);

				foreach (array_slice($m->creators, 0, $max_authors) as $creator) {
					$name = isset($creator->name) ? esc_html($creator->name) : '';
					if ($name) {
						if (!empty($creator->affiliation)) {
							$author_names[] = '<span class="dyzc-author" title="' . esc_attr($creator->affiliation) . '">' . $name . '</span>';
						} else {
							$author_names[] = '<span class="dyzc-author">' . $name . '</span>';
						}
					}
				}

				$html .= wp_kses_post( implode(', ', $author_names) );
				if ($creator_count > $max_authors) {
					$html .= ' <span class="dyzc-et-al">et al.</span>';
				}
				$html .= '</div>';
			}

			// Abstract (collapsible)
			if (!empty($m->description)) {
				$clean_desc = wp_strip_all_tags($m->description);
				$short_desc = mb_strlen($clean_desc) > 200 ? mb_substr($clean_desc, 0, 200) . '…' : $clean_desc;

				$html .= '<div class="dyzc-pub-abstract">';
				$html .= '<p class="dyzc-abstract-short">' . esc_html($short_desc) . '</p>';
				if (mb_strlen($clean_desc) > 200) {
					$html .= '<p class="dyzc-abstract-full" style="display:none;">' . esc_html($clean_desc) . '</p>';
					$html .= '<button class="dyzc-toggle-abstract" data-expanded="false">' . esc_html( $this->get_label('show_more', $lang) ) . '</button>';
				}
				$html .= '</div>';
			}

			// Badges (tags/keywords)
			if (!empty($m->keywords)) {
				$html .= '<div class="dyzc-pub-badges">';
				foreach (array_slice($m->keywords, 0, 5) as $keyword) {
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

			// Zenodo link
			if (!empty($hit->links->self_html) || !empty($hit->links->latest_html)) {
				$zenodo_url = !empty($hit->links->self_html) ? $hit->links->self_html : $hit->links->latest_html;
				$html .= '<a href="' . esc_url($zenodo_url) . '" class="dyzc-btn dyzc-btn-secondary" target="_blank" rel="noopener">';
				$html .= esc_html( $this->get_label('view_repository', $lang) );
				$html .= '</a>';
			}

			$html .= '</div>'; // .dyzc-pub-actions
			$html .= '</article>'; // .dyzc-pub-card
		}

		$html .= $this->render_pagination($page, $nb_pages, $lang);
		$html .= '</div>'; // .dyzc-publications-grid

		return $html;
	}

	private function zp_retrieve_json($page, $options, $keyword_override, $type_filter = '', $subtype_filter = '') {
		$choice = isset($options['choice']) ? $options['choice'] : 'community';
		$id = isset($options['id_community_orcid']) ? trim($options['id_community_orcid']) : '';
		$access_token = isset($options['access_token']) ? trim($options['access_token']) : '';
		$extra_keyword = isset($options['extra_keyword']) ? trim($options['extra_keyword']) : '';
		$size = isset($options['number_publications']) ? max(1, min(100, (int)$options['number_publications'])) : 10;

		if (empty($id)) {
			return new WP_Error('no_id', 'No Community ID or ORCID configured.');
		}

		$final_keyword = !empty($keyword_override) ? $keyword_override : $extra_keyword;

		$transient_key = 'acadpush_' . md5($choice . $id . $final_keyword . $type_filter . $subtype_filter . $page . $size);
		$cached = get_transient($transient_key);
		if ($cached !== false) {
			return $cached;
		}

		if ($choice === 'orcid') {
			$query = 'creators.orcid:"' . $id . '"';
			if (!empty($final_keyword)) {
				$query .= ' AND ' . $final_keyword;
			}

			$url = 'https://zenodo.org/api/records/?q=' . rawurlencode($query) . '&size=' . $size . '&page=' . $page . '&sort=newest';

			// Add type and subtype as dedicated URL parameters
			if (!empty($type_filter)) {
				$url .= '&type=' . rawurlencode($type_filter);
			}
			if (!empty($subtype_filter)) {
				$url .= '&subtype=' . rawurlencode($subtype_filter);
			}
		} else {
			$url = 'https://zenodo.org/api/records/?communities=' . rawurlencode($id) . '&size=' . $size . '&page=' . $page . '&sort=newest';

			// Add keyword as q parameter if present
			if (!empty($final_keyword)) {
				$url .= '&q=' . rawurlencode($final_keyword);
			}

			// Add type and subtype as dedicated URL parameters
			if (!empty($type_filter)) {
				$url .= '&type=' . rawurlencode($type_filter);
			}
			if (!empty($subtype_filter)) {
				$url .= '&subtype=' . rawurlencode($subtype_filter);
			}
		}

		$args = array(
			'timeout' => 30,
			'user-agent' => 'Academic Publications Showcase/' . $this->version . ' (WordPress Plugin)',
			'headers' => array('Accept' => 'application/json'),
		);

		if (!empty($access_token)) {
			$args['headers']['Authorization'] = 'Bearer ' . $access_token;
		}

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code !== 200) {
			return new WP_Error('http_error', 'HTTP ' . $status_code);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('json_error', 'Invalid JSON response');
		}

		set_transient($transient_key, $data, HOUR_IN_SECONDS);
		return $data;
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
		$timestamp = strtotime($date_string);
		if (!$timestamp) return esc_html($date_string);

		$months_cs = array('', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec');
		$months_en = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

		$month_num = (int)gmdate('n', $timestamp);
		$year = gmdate('Y', $timestamp);

		$month = ($lang === 'cs') ? $months_cs[$month_num] : $months_en[$month_num];
		return $month . ', ' . $year;
	}

	private function get_label($key, $lang) {
		$labels = array(
			'cs' => array(
				'no_results' => 'Žádné publikace nenalezeny.',
				'view_publication' => 'Publikace',
				'view_repository' => 'Repozitář',
				'show_more' => 'Zobrazit více',
				'show_less' => 'Zobrazit méně',
				'previous' => 'Předchozí',
				'next' => 'Další',
			),
			'en' => array(
				'no_results' => 'No publications found.',
				'view_publication' => 'Publication',
				'view_repository' => 'Repository',
				'show_more' => 'Show more',
				'show_less' => 'Show less',
				'previous' => 'Previous',
				'next' => 'Next',
			),
		);

		$lang_labels = isset($labels[$lang]) ? $labels[$lang] : $labels['en'];
		return isset($lang_labels[$key]) ? $lang_labels[$key] : '';
	}
}
