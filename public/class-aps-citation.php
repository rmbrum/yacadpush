<?php
/**
 * Reference formatting for the compact view.
 *
 * Takes a record shaped like the Zenodo REST API (as returned by the Zenodo modes and by
 * ACADPUSH_DB) and builds one reference in a citation style: ABNT (NBR 6023:2025), APA 7th,
 * IEEE, Vancouver (NLM/ICMJE) or Chicago author-date (17th), plus "simple", the plugin's own
 * short form.
 *
 * Every piece of text is escaped here. The caller passes the title already rendered as HTML
 * (the link that opens the dialog) together with what goes right after it (the DOI icon).
 *
 * Limits, by design: titles are printed as stored (no sentence or title case conversion),
 * journal names are not abbreviated, and author names are split as stored ("Family, Given").
 *
 * @package YACADPUSH
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACADPUSH_Citation {

	/** Style key => display name. "simple" is translated by the caller. */
	const STYLES = array(
		'simple'    => 'simple',
		'abnt'      => 'ABNT',
		'apa'       => 'APA',
		'ieee'      => 'IEEE',
		'vancouver' => 'Vancouver',
		'chicago'   => 'Chicago',
	);

	/** Lowercase name particles left out of initials ("Elias de Almeida" -> "E. A."). */
	const PARTICLES = array( 'da', 'das', 'de', 'del', 'della', 'der', 'di', 'do', 'dos', 'du', 'e', 'van', 'von', 'y', 'la', 'le', 'den', 'ten', 'ter' );

	/** ABNT: particles that leave the front of the surname and of the part before the entry. */
	const ABNT_PARTICLES = array( 'da', 'das', 'de', 'do', 'dos', 'e', 'del', 'y' );

	/** ABNT: words that enter together with the previous one ("ROSA JÚNIOR", "AMERICO FILHO"). */
	const ABNT_SUFFIXES = array( 'filho', 'junior', 'júnior', 'jr', 'jr.', 'neto', 'sobrinho' );

	/** ABNT: prefixes that stay with the entry ("DAL BEM", "LA ROSA", "VAN DER PLAS"). */
	const ABNT_PREFIXES = array( 'dal', 'dalla', 'della', 'dei', 'di', 'du', 'la', 'le', 'lo', 'van', 'von', 'der', 'den', 'ter', 'ten' );

	/** @var array Labels for the "simple" style, in the page language (volume, issue, pages). */
	private $labels;

	/** @var array Surnames ABNT keeps whole, as name_key() => true. */
	private $compound = array();

	/** @var bool ABNT with initials: write the particles (da, de, dos...) instead of leaving them out. */
	private $keep_particles = false;

	/**
	 * @param array $labels   volume, issue and pages labels for "simple".
	 * @param array $compound Surnames that ABNT enters whole instead of by the last word
	 *                        ("Gomez Caicedo"), compared ignoring case and accents.
	 */
	public function __construct( array $labels = array(), array $compound = array() ) {
		$this->labels = array_merge(
			array( 'volume' => 'vol.', 'issue' => 'no.', 'pages' => 'pp.' ),
			$labels
		);
		foreach ( $compound as $surname ) {
			$key = $this->name_key( (string) $surname );
			if ( $key !== '' ) {
				$this->compound[ $key ] = true;
			}
		}
	}

	/**
	 * Splits a list of surnames typed one per line; blank lines and lines starting with "#" are skipped.
	 *
	 * @return string[]
	 */
	public static function parse_surname_list( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( $line !== '' && $line[0] !== '#' ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	public static function is_style( $style ) {
		return isset( self::STYLES[ $style ] );
	}

	/**
	 * One reference as HTML.
	 *
	 * @param object $m           Record metadata (Zenodo shape).
	 * @param string $doi         Bare DOI ("10.1109/..."), or ''.
	 * @param string $title_html  Title, already escaped (usually a link).
	 * @param string $after_title HTML placed right after the title and its punctuation (an icon).
	 * @param string $style       Key of self::STYLES.
	 * @param string $names       "full" or "initials"; only ABNT, Chicago and simple honour "full".
	 * @param int    $max_authors 0 = the style's own rule; N = first N, then "et al.".
	 * @param string $particles   ABNT with initials: "hide" (default) leaves out da, de, dos...
	 *                            ("AZAMBUJA, J. R. F."); "keep" writes them ("AZAMBUJA, J. R. F. de").
	 */
	public function format( $m, $doi, $title_html, $after_title, $style, $names = 'full', $max_authors = 0, $particles = 'hide' ) {
		$r = $this->normalize( $m, $doi );
		$r['title_html']  = $title_html;
		$r['after_title'] = $after_title;
		$r['initials']    = ( $names === 'initials' );
		$r['max_authors'] = max( 0, (int) $max_authors );
		$this->keep_particles = ( $particles === 'keep' );

		switch ( $style ) {
			case 'abnt':
				return $this->abnt( $r );
			case 'apa':
				return $this->apa( $r );
			case 'ieee':
				return $this->ieee( $r );
			case 'vancouver':
				return $this->vancouver( $r );
			case 'chicago':
				return $this->chicago( $r );
			default:
				return $this->simple( $r );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Record                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Flattens the Zenodo-shaped metadata into the fields the styles need.
	 */
	private function normalize( $m, $doi ) {
		$get = function ( $obj, $key ) {
			return ( is_object( $obj ) && isset( $obj->$key ) && is_scalar( $obj->$key ) ) ? trim( (string) $obj->$key ) : '';
		};

		$subtype = '';
		if ( isset( $m->resource_type ) && is_object( $m->resource_type ) ) {
			$subtype = $get( $m->resource_type, 'subtype' );
		}

		$journal   = isset( $m->journal ) ? $m->journal : null;
		$meeting   = isset( $m->meeting ) ? $m->meeting : null;
		$part_of   = isset( $m->part_of ) ? $m->part_of : null;
		$imprint   = isset( $m->imprint ) ? $m->imprint : null;
		$container = $get( $journal, 'title' );
		if ( $container === '' && ! empty( $m->journal_title ) && is_scalar( $m->journal_title ) ) {
			$container = trim( (string) $m->journal_title );
		}
		$conference = $get( $meeting, 'title' );

		// What kind of reference: from the subtype when known, else from the fields present.
		if ( in_array( $subtype, array( 'section', 'book', 'conferencepaper', 'article' ), true ) ) {
			$kind = array(
				'section'         => 'chapter',
				'book'            => 'book',
				'conferencepaper' => 'conference',
				'article'         => 'article',
			)[ $subtype ];
		} elseif ( $conference !== '' ) {
			$kind = 'conference';
		} elseif ( $container !== '' ) {
			$kind = 'article';
		} elseif ( $get( $part_of, 'title' ) !== '' ) {
			$kind = 'chapter';
		} else {
			$kind = 'other';
		}

		// A conference paper without a meeting title: use the container (proceedings) title.
		if ( $kind === 'conference' && $conference === '' ) {
			$conference = $container;
		}
		// Book chapters: Zenodo keeps the book in part_of, the database in journal.
		$book = $get( $part_of, 'title' ) !== '' ? $get( $part_of, 'title' ) : $container;

		$pages = $get( $journal, 'pages' );
		if ( $pages === '' ) {
			$pages = $get( $part_of, 'pages' );
		}
		$pages = trim( str_replace( array( '–', '—', '‐' ), '-', $pages ) );

		$publisher = ( ! empty( $m->publisher ) && is_scalar( $m->publisher ) ) ? trim( (string) $m->publisher ) : '';
		if ( $publisher === '' ) {
			$publisher = $get( $imprint, 'publisher' );
		}

		$year = '';
		if ( ! empty( $m->publication_date ) && preg_match( '/^(\d{4})/', (string) $m->publication_date, $ym ) ) {
			$year = $ym[1];
		}

		$title = trim( (string) ( isset( $m->title ) ? $m->title : '' ) );

		$authors = array();
		if ( ! empty( $m->creators ) && is_array( $m->creators ) ) {
			foreach ( $m->creators as $creator ) {
				$raw = isset( $creator->name ) ? trim( (string) $creator->name ) : '';
				if ( $raw === '' ) {
					continue;
				}
				$person                = $this->parse_name( $raw );
				$person['affiliation'] = ! empty( $creator->affiliation ) ? (string) $creator->affiliation : '';
				$authors[]             = $person;
			}
		}

		return array(
			'kind'       => $kind,
			'type_label' => ( isset( $m->resource_type->title ) && is_scalar( $m->resource_type->title ) ) ? (string) $m->resource_type->title : '',
			'title'      => $title,
			'journal'    => $kind === 'article' ? $container : '',
			'series'     => $kind === 'book' ? $container : '',
			'book'       => $kind === 'chapter' ? $book : '',
			'conference' => $kind === 'conference' ? $conference : '',
			'place'      => $get( $meeting, 'place' ),
			'volume'     => $get( $journal, 'volume' ),
			'issue'      => $get( $journal, 'issue' ),
			'pages'      => $pages,
			'publisher'  => $publisher,
			'year'       => $year,
			'doi'        => trim( (string) $doi ),
			'authors'    => $authors,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Names                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * "Family, Given" -> parts. Names without a comma are read as "Given Family", with
	 * lowercase particles kept in the family name ("Ludwig van Beethoven").
	 */
	private function parse_name( $raw ) {
		$raw = preg_replace( '/\s+/u', ' ', $raw );
		if ( strpos( $raw, ',' ) !== false ) {
			list( $family, $given ) = array_map( 'trim', explode( ',', $raw, 2 ) );
			return array( 'family' => $family, 'given' => $given );
		}
		$tokens = explode( ' ', $raw );
		if ( count( $tokens ) === 1 ) {
			return array( 'family' => $raw, 'given' => '' );
		}
		$family = array( array_pop( $tokens ) );
		while ( count( $tokens ) > 1 && in_array( end( $tokens ), self::PARTICLES, true ) ) {
			array_unshift( $family, array_pop( $tokens ) );
		}
		return array( 'family' => implode( ' ', $family ), 'given' => implode( ' ', $tokens ) );
	}

	/**
	 * Initials of the given names.
	 *
	 * @param bool $compact true: "AB" (Vancouver); false: "A. B.", "J.-C.".
	 */
	private function initials( $given, $compact = false, $keep_particles = false ) {
		$out = array();
		// "L.B." and "L. B." both become two initials.
		$given = preg_replace( '/\.(?=\S)/u', '. ', $given );
		foreach ( preg_split( '/\s+/u', trim( $given ) ) as $token ) {
			$token = trim( $token );
			if ( $token === '' ) {
				continue;
			}
			// "E." and a bare capital "E" are initials, not the particle "e"; "De" and "de" are particles.
			$is_particle = substr( $token, -1 ) !== '.'
				&& in_array( $this->lower( $token ), self::PARTICLES, true )
				&& ( $token === $this->lower( $token ) || strlen( $token ) > 1 );
			if ( $is_particle ) {
				// ABNT keeps particles as written ("E. de A."); the other styles drop them.
				if ( $keep_particles ) {
					$out[] = $this->lower( $token );
				}
				continue;
			}
			$parts = array();
			foreach ( explode( '-', $token ) as $piece ) {
				$piece = trim( $piece, '.' );
				if ( $piece !== '' ) {
					$parts[] = $this->upper( $this->substr( $piece, 0, 1 ) );
				}
			}
			if ( empty( $parts ) ) {
				continue;
			}
			$out[] = $compact ? implode( '', $parts ) : implode( '.-', $parts ) . '.';
		}
		return implode( $compact ? '' : ' ', $out );
	}

	/** A name in one of the forms the styles use, escaped and wrapped with its affiliation. */
	private function name( $person, $form, $initials ) {
		$family = $person['family'];
		$given  = $person['given'];
		$short  = $this->initials( $given );

		switch ( $form ) {
			case 'abnt':       // SILVA, João Carlos | SILVA, J. C.
				// Entry by the last surname (NBR 6023): "Furlanetto de Azambuja, José Rodrigo"
				// -> "AZAMBUJA, José Rodrigo Furlanetto de" | "AZAMBUJA, J. R. F. de".
				// With initials the particles are left out unless particles="keep": "AZAMBUJA, J. R. F.".
				list( $entry, $rest, $particles ) = $this->abnt_split( $family, $given );
				if ( $initials && ! $this->keep_particles ) {
					$after = $this->initials( $rest );
				} else {
					$after = $this->join( array( $initials ? $this->initials( $rest, false, true ) : $rest, $particles ), ' ' );
				}
				$text  = $this->upper( $entry ) . ( $after !== '' ? ', ' . $after : '' );
				break;
			case 'inverted':   // Silva, João Carlos | Silva, J. C.
				$text = $family . ( $given !== '' ? ', ' . ( $initials ? $short : $given ) : '' );
				break;
			case 'direct':     // João Carlos Silva | J. C. Silva
				$text = ( $given !== '' ? ( $initials ? $short : $given ) . ' ' : '' ) . $family;
				break;
			case 'vancouver':  // Silva JC
				$text = $family . ( $given !== '' ? ' ' . $this->initials( $given, true ) : '' );
				break;
			default:
				$text = $family;
		}

		$attr = $person['affiliation'] !== '' ? ' title="' . esc_attr( $person['affiliation'] ) . '"' : '';
		return '<span class="dyzc-author"' . $attr . '>' . esc_html( $text ) . '</span>';
	}

	/**
	 * ABNT entry: which words of the surname head the name, and what goes after the given names.
	 *
	 * 1. Particles at the front (da, de, dos, e, del, y...) leave it.
	 * 2. A surname in the compound list is the entry as a whole.
	 * 3. Otherwise the entry is the last word, taking along a preceding word when it is a suffix
	 *    (Júnior, Filho, Neto, Sobrinho) and foreign prefixes before it (Dal, La, Van der...).
	 * 4. The rest of the surname follows the given names; particles that stood right before the
	 *    entry go to the end. With nothing left but particles, those at the front go there too.
	 *
	 * @return array [ entry, given names followed by the rest of the surname, trailing particles ]
	 */
	private function abnt_split( $family, $given ) {
		$is_particle = function ( $word ) {
			return in_array( $this->lower( $word ), self::ABNT_PARTICLES, true );
		};

		$words = preg_split( '/\s+/u', trim( $family ), -1, PREG_SPLIT_NO_EMPTY );
		$lead  = array();
		while ( count( $words ) > 1 && $is_particle( $words[0] ) ) {
			$lead[] = $this->lower( array_shift( $words ) );
		}

		$count = count( $words );
		if ( $count <= 1 || isset( $this->compound[ $this->name_key( implode( ' ', $words ) ) ] ) ) {
			$entry = $words;
			$rest  = array();
		} else {
			$n = in_array( $this->lower( $words[ $count - 1 ] ), self::ABNT_SUFFIXES, true ) ? 2 : 1;
			while ( $count > $n && in_array( $this->lower( $words[ $count - $n - 1 ] ), self::ABNT_PREFIXES, true ) ) {
				$n++;
			}
			$entry = array_slice( $words, $count - $n );
			$rest  = array_slice( $words, 0, $count - $n );
		}

		$particles = array();
		while ( $rest && $is_particle( end( $rest ) ) ) {
			array_unshift( $particles, $this->lower( array_pop( $rest ) ) );
		}
		if ( ! $rest ) {
			$particles = array_merge( $lead, $particles );
			$lead      = array();
		}

		$after = trim( $given . ' ' . implode( ' ', array_merge( $lead, $rest ) ) );
		return array( implode( ' ', $entry ), $after, implode( ' ', $particles ) );
	}

	/** Comparison key for surnames: no accents, lowercase, single spaces. */
	private function name_key( $s ) {
		$s = function_exists( 'remove_accents' ) ? remove_accents( $s ) : $s;
		return $this->lower( trim( preg_replace( '/\s+/u', ' ', $s ) ) );
	}

	/** "et al." in the markup the stylesheet expects. */
	private function et_al() {
		return '<span class="dyzc-et-al">et al.</span>';
	}

	/**
	 * Author list.
	 *
	 * @param array $r       Normalized record.
	 * @param array $rule    form, sep, last_sep, two_sep, limit (list all up to it), keep (shown when over),
	 *                       first_form (form of the first name), apa (APA's 21+ rule).
	 */
	private function author_list( array $r, array $rule ) {
		$people = $r['authors'];
		$n      = count( $people );
		if ( $n === 0 ) {
			return '';
		}
		$rule = array_merge(
			array(
				'first_form' => null,
				'sep'        => '; ',
				'last_sep'   => null,
				'two_sep'    => null,
				'limit'      => 0,
				'keep'       => 1,
				'etal_sep'   => ' ',
				'apa'        => false,
				'initials'   => $r['initials'],
			),
			$rule
		);

		$render = function ( $i ) use ( $people, $rule ) {
			$form = ( $i === 0 && $rule['first_form'] ) ? $rule['first_form'] : $rule['form'];
			return $this->name( $people[ $i ], $form, $rule['initials'] );
		};

		// A limit set in the shortcode overrides the style's rule.
		$limit = $r['max_authors'] ? $r['max_authors'] : $rule['limit'];
		$keep  = $r['max_authors'] ? $r['max_authors'] : $rule['keep'];

		if ( $rule['apa'] && ! $r['max_authors'] && $n > 20 ) {
			// APA 7: the first 19, an ellipsis, then the last author.
			$names = array();
			for ( $i = 0; $i < 19; $i++ ) {
				$names[] = $render( $i );
			}
			return implode( ', ', $names ) . ', . . . ' . $render( $n - 1 );
		}

		if ( $limit && $n > $limit ) {
			$names = array();
			for ( $i = 0; $i < min( $keep, $n ); $i++ ) {
				$names[] = $render( $i );
			}
			return implode( $rule['sep'], $names ) . $rule['etal_sep'] . $this->et_al();
		}

		$names = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$names[] = $render( $i );
		}
		if ( $n === 2 && $rule['two_sep'] !== null ) {
			return $names[0] . $rule['two_sep'] . $names[1];
		}
		if ( $n > 1 && $rule['last_sep'] !== null ) {
			$last = array_pop( $names );
			return implode( $rule['sep'], $names ) . $rule['last_sep'] . $last;
		}
		return implode( $rule['sep'], $names );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private function upper( $s ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $s, 'UTF-8' ) : strtoupper( $s );
	}

	private function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	private function substr( $s, $start, $len ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $s, $start, $len, 'UTF-8' ) : substr( $s, $start, $len );
	}

	/** Adds a period unless the text already ends with terminal punctuation. */
	private function period( $html ) {
		$text = rtrim( wp_strip_all_tags( $html ) );
		return ( $text === '' || preg_match( '/[.?!]$/u', $text ) ) ? $html : $html . '.';
	}

	/** Title followed by $punct, unless the title itself ends with ? or !. */
	private function title( array $r, $punct = '.', $open = '', $close = '' ) {
		$ends = preg_match( '/[?!]$/u', $r['title'] ) ? '' : $punct;
		if ( $open !== '' ) {
			// Quoted titles: the comma or period goes inside the closing quote.
			return $open . $r['title_html'] . $ends . $close . $r['after_title'];
		}
		return $r['title_html'] . $ends . $r['after_title'];
	}

	private function pages( $pages, $dash ) {
		return str_replace( '-', $dash, $pages );
	}

	private function is_range( $pages ) {
		return strpos( $pages, '-' ) !== false;
	}

	/** NLM page ranges drop repeated leading digits: 311-319 -> 311-9. */
	private function nlm_pages( $pages ) {
		if ( preg_match( '/^(\d+)-(\d+)$/', $pages, $p ) && strlen( $p[1] ) === strlen( $p[2] ) ) {
			$i = 0;
			while ( $i < strlen( $p[1] ) - 1 && $p[1][ $i ] === $p[2][ $i ] ) {
				$i++;
			}
			return $p[1] . '-' . substr( $p[2], $i );
		}
		return $pages;
	}

	private function doi_link( $doi, $text ) {
		return '<a class="dyzc-doi" href="' . esc_url( 'https://doi.org/' . $doi ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . '</a>';
	}

	/** Joins non-empty pieces. */
	private function join( array $pieces, $sep ) {
		return implode( $sep, array_filter( $pieces, function ( $p ) {
			return $p !== '' && $p !== null;
		} ) );
	}

	/* ------------------------------------------------------------------ */
	/* Styles                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * ABNT NBR 6023:2025. Authors in full by default (names="initials" abbreviates them); every
	 * author is listed (the norm recommends it; max_authors gives "first N et al.").
	 * Unknown place of publication is "[S. l.]", and "[S. l.: s. n.]" when the publisher is unknown
	 * too (8.5.6). The proceedings title is not stored, so conference papers use "Anais [...]".
	 */
	private function abnt( array $r ) {
		$e   = 'esc_html';
		$out = array();

		$authors = $this->author_list( $r, array( 'form' => 'abnt', 'sep' => '; ' ) );
		if ( $authors !== '' ) {
			$out[] = $this->period( $authors );
		}

		$pages = $r['pages'] !== '' ? 'p. ' . $e( $this->pages( $r['pages'], '-' ) ) : '';
		$imprint = $this->join( array( $r['publisher'] !== '' ? '[S. l.]: ' . $e( $r['publisher'] ) : '[S. l.: s. n.]', $e( $r['year'] ) ), ', ' );

		switch ( $r['kind'] ) {
			case 'article':
				$out[] = $this->title( $r );
				$out[] = $this->join(
					array(
						'<strong>' . $e( $r['journal'] ) . '</strong>',
						'[S. l.]',
						$r['volume'] !== '' ? 'v. ' . $e( $r['volume'] ) : '',
						$r['issue'] !== '' ? 'n. ' . $e( $r['issue'] ) : '',
						$pages,
						$e( $r['year'] ),
					),
					', '
				) . '.';
				break;

			case 'conference':
				$out[] = $this->title( $r );
				$out[] = 'In: ' . $this->join( array( $e( $this->upper( $r['conference'] ) ), $e( $r['year'] ), $e( $r['place'] ) ), ', ' ) . '.';
				$out[] = '<strong>Anais</strong> [...].';
				$out[] = $imprint . '.';
				if ( $pages !== '' ) {
					$out[] = $pages . '.';
				}
				break;

			case 'chapter':
				$out[] = $this->title( $r );
				$out[] = 'In: <strong>' . $e( $r['book'] ) . '</strong>.';
				$out[] = $imprint . '.';
				if ( $pages !== '' ) {
					$out[] = $pages . '.';
				}
				break;

			case 'book':
				$out[] = '<strong>' . $this->title( $r ) . '</strong>';
				$out[] = $imprint . '.';
				if ( $r['series'] !== '' ) {
					$out[] = '(' . $e( $r['series'] ) . ').';
				}
				break;

			default:
				$out[] = $this->title( $r );
				$out[] = $imprint . '.';
		}

		if ( $r['doi'] !== '' ) {
			$out[] = 'DOI: ' . $this->doi_link( $r['doi'], $r['doi'] ) . '.';
		}
		return implode( ' ', $out );
	}

	/**
	 * APA 7th edition. Initials always; up to 20 authors, then the first 19, ". . ." and the last.
	 */
	private function apa( array $r ) {
		$e   = 'esc_html';
		$out = array();

		$authors = $this->author_list(
			$r,
			array(
				'form'     => 'inverted',
				'initials' => true,
				'sep'      => ', ',
				'last_sep' => ', &amp; ',
				'two_sep'  => ', &amp; ',
				'etal_sep' => ', ',
				'apa'      => true,
			)
		);
		$date = '(' . ( $r['year'] !== '' ? $e( $r['year'] ) : 'n.d.' ) . ').';
		if ( $authors !== '' ) {
			$out[] = $this->period( $authors ) . ' ' . $date;
		}

		$pages_en = $this->pages( $r['pages'], '–' );
		$pp       = $r['pages'] !== '' ? ( $this->is_range( $r['pages'] ) ? 'pp. ' : 'p. ' ) . $e( $pages_en ) : '';
		$publisher = $r['publisher'] !== '' ? $this->period( $e( $r['publisher'] ) ) : '';

		switch ( $r['kind'] ) {
			case 'article':
				$out[] = $this->title( $r );
				$source = '<em>' . $e( $r['journal'] ) . '</em>';
				if ( $r['volume'] !== '' ) {
					$source .= ', <em>' . $e( $r['volume'] ) . '</em>' . ( $r['issue'] !== '' ? '(' . $e( $r['issue'] ) . ')' : '' );
				}
				if ( $r['pages'] !== '' ) {
					$source .= ', ' . $e( $pages_en );
				}
				$out[] = $source . '.';
				break;

			case 'conference':
			case 'chapter':
				$out[] = $this->title( $r );
				$host  = $r['kind'] === 'conference' ? $r['conference'] : $r['book'];
				$out[] = 'In <em>' . $e( $host ) . '</em>' . ( $pp !== '' ? ' (' . $pp . ')' : '' ) . '.';
				$out[] = $publisher;
				break;

			case 'book':
				$out[] = '<em>' . $this->title( $r ) . '</em>';
				$out[] = $publisher;
				break;

			default:
				$out[] = $this->title( $r );
				$out[] = $publisher;
		}

		if ( $authors === '' ) {
			// No author: the title moves to the author position, followed by the date.
			$title = array_shift( $out );
			array_unshift( $out, $title, $date );
		}
		if ( $r['doi'] !== '' ) {
			$out[] = $this->doi_link( $r['doi'], 'https://doi.org/' . $r['doi'] );
		}
		return $this->join( $out, ' ' );
	}

	/**
	 * IEEE reference style. Initials always; up to six authors, then the first and "et al.".
	 */
	private function ieee( array $r ) {
		$e = 'esc_html';

		$authors = $this->author_list(
			$r,
			array(
				'form'     => 'direct',
				'initials' => true,
				'sep'      => ', ',
				'last_sep' => ', and ',
				'two_sep'  => ' and ',
				'limit'    => 6,
				'keep'     => 1,
			)
		);

		$pages = '';
		if ( $r['pages'] !== '' ) {
			$pages = ( $this->is_range( $r['pages'] ) ? 'pp. ' : 'p. ' ) . $e( $this->pages( $r['pages'], '–' ) );
		}

		$quoted = $this->title( $r, ',', '“', '”' );
		switch ( $r['kind'] ) {
			case 'article':
				$parts = array(
					$quoted . ' <em>' . $e( $r['journal'] ) . '</em>',
					$r['volume'] !== '' ? 'vol. ' . $e( $r['volume'] ) : '',
					$r['issue'] !== '' ? 'no. ' . $e( $r['issue'] ) : '',
					$pages,
					$e( $r['year'] ),
				);
				break;

			case 'conference':
				$name  = $r['conference'];
				$proc  = preg_match( '/^proc/i', $name ) ? '' : 'Proc. ';
				$parts = array(
					$quoted . ' in <em>' . $e( $proc . $name ) . '</em>',
					$e( $r['place'] ),
					$e( $r['year'] ),
					$pages,
				);
				break;

			case 'chapter':
				$parts = array(
					$quoted . ' in <em>' . $e( $r['book'] ) . '</em>',
					$e( $r['publisher'] ),
					$e( $r['year'] ),
					$pages,
				);
				break;

			case 'book':
				// Book titles are italic and end with a period: *Title*. Publisher, year.
				$parts = array(
					'<em>' . $this->title( $r ) . '</em> ' . $e( $r['publisher'] ),
					$e( $r['year'] ),
				);
				break;

			default:
				$parts = array( $quoted . ' ' . $e( $r['publisher'] ), $e( $r['year'] ) );
		}

		if ( $r['doi'] !== '' ) {
			$parts[] = 'doi: ' . $this->doi_link( $r['doi'], $r['doi'] );
		}
		$body = $this->join( array_map( 'trim', $parts ), ', ' ) . '.';
		return $authors !== '' ? $authors . ', ' . $body : $body;
	}

	/**
	 * Vancouver (NLM/ICMJE). "Silva JC"; up to six authors, then ", et al."; page ranges drop
	 * repeated digits (311-9).
	 */
	private function vancouver( array $r ) {
		$e   = 'esc_html';
		$out = array();

		$authors = $this->author_list(
			$r,
			array(
				'form'     => 'vancouver',
				'sep'      => ', ',
				'limit'    => 6,
				'keep'     => 6,
				'etal_sep' => ', ',
			)
		);
		if ( $authors !== '' ) {
			$out[] = $this->period( $authors );
		}
		$out[] = $this->title( $r );

		$pages     = $e( $this->nlm_pages( $r['pages'] ) );
		$publisher = $this->join( array( $e( $r['publisher'] ), $e( $r['year'] ) ), '; ' );

		switch ( $r['kind'] ) {
			case 'article':
				$issue = $r['volume'] !== '' ? $e( $r['volume'] ) . ( $r['issue'] !== '' ? '(' . $e( $r['issue'] ) . ')' : '' ) : '';
				$cite  = $e( $r['year'] ) . ( $issue !== '' ? ';' . $issue : '' ) . ( $pages !== '' ? ':' . $pages : '' );
				$out[] = $e( $r['journal'] ) . '. ' . $cite . '.';
				break;

			case 'conference':
				$out[] = 'In: ' . $this->join( array( $e( $r['conference'] ), $e( $r['year'] ), $e( $r['place'] ) ), '; ' ) . '.';
				$out[] = $publisher !== '' ? $publisher . '.' : '';
				$out[] = $pages !== '' ? 'p. ' . $pages . '.' : '';
				break;

			case 'chapter':
				$out[] = 'In: ' . $e( $r['book'] ) . '.';
				$out[] = $publisher !== '' ? $publisher . '.' : '';
				$out[] = $pages !== '' ? 'p. ' . $pages . '.' : '';
				break;

			default:
				$out[] = $publisher !== '' ? $publisher . '.' : '';
		}

		if ( $r['doi'] !== '' ) {
			$out[] = 'doi:' . $this->doi_link( $r['doi'], $r['doi'] );
		}
		return $this->join( $out, ' ' );
	}

	/**
	 * Chicago author-date, 17th edition (reference list). The first author is inverted, the
	 * others are not; more than ten authors gives the first seven and "et al.".
	 */
	private function chicago( array $r ) {
		$e   = 'esc_html';
		$out = array();

		$authors = $this->author_list(
			$r,
			array(
				'first_form' => 'inverted',
				'form'       => 'direct',
				'sep'        => ', ',
				'last_sep'   => ', and ',
				'two_sep'    => ', and ',
				'limit'      => 10,
				'keep'       => 7,
				'etal_sep'   => ', ',
			)
		);
		if ( $authors !== '' ) {
			$out[] = $this->period( $authors );
		}
		$out[] = ( $r['year'] !== '' ? $e( $r['year'] ) : 'n.d.' ) . '.';

		$pages     = $e( $this->pages( $r['pages'], '–' ) );
		$publisher = $r['publisher'] !== '' ? $this->period( $e( $r['publisher'] ) ) : '';
		$quoted    = $this->title( $r, '.', '“', '”' );

		switch ( $r['kind'] ) {
			case 'article':
				$out[] = $quoted;
				$src   = '<em>' . $e( $r['journal'] ) . '</em>';
				if ( $r['volume'] !== '' ) {
					$src .= ' ' . $e( $r['volume'] ) . ( $r['issue'] !== '' ? ' (' . $e( $r['issue'] ) . ')' : '' );
				}
				$src  .= $pages !== '' ? ': ' . $pages : '';
				$out[] = $src . '.';
				break;

			case 'conference':
			case 'chapter':
				$out[] = $quoted;
				$host  = $r['kind'] === 'conference' ? $r['conference'] : $r['book'];
				$out[] = 'In <em>' . $e( $host ) . '</em>' . ( $pages !== '' ? ', ' . $pages : '' ) . '.';
				$out[] = $publisher;
				break;

			case 'book':
				$out[] = '<em>' . $this->title( $r ) . '</em>';
				$out[] = $publisher;
				break;

			default:
				$out[] = $quoted;
				$out[] = $publisher;
		}

		if ( $r['doi'] !== '' ) {
			$out[] = $this->doi_link( $r['doi'], 'https://doi.org/' . $r['doi'] ) . '.';
		}
		return $this->join( $out, ' ' );
	}

	/**
	 * The plugin's short form (the 2.1.0 compact line): authors; title; venue, volume, issue,
	 * pages, year. The first five authors, then "et al.".
	 */
	private function simple( array $r ) {
		$e   = 'esc_html';
		$out = array();

		$authors = $this->author_list( $r, array( 'form' => 'inverted', 'sep' => '; ', 'limit' => 5, 'keep' => 5 ) );
		if ( $authors !== '' ) {
			$out[] = $this->period( $authors );
		}
		$out[] = $this->title( $r );

		$venue = $r['journal'] . $r['book'] . $r['series'];
		if ( $r['kind'] === 'conference' ) {
			$venue = $r['conference'];
			if ( $r['place'] !== '' && stripos( $venue, $r['place'] ) === false ) {
				$venue .= ' (' . $r['place'] . ')';
			}
		}
		$source = array();
		if ( $venue !== '' ) {
			$source[] = '<em class="dyzc-pub-venue">' . $e( $venue ) . '</em>';
		} elseif ( $r['type_label'] !== '' ) {
			$source[] = $e( $r['type_label'] );
		}
		if ( $r['volume'] !== '' ) {
			$source[] = $e( $this->labels['volume'] . ' ' . $r['volume'] );
		}
		if ( $r['issue'] !== '' ) {
			$source[] = $e( $this->labels['issue'] . ' ' . $r['issue'] );
		}
		if ( $r['pages'] !== '' ) {
			$source[] = $e( $this->labels['pages'] . ' ' . $r['pages'] );
		}
		if ( $r['year'] !== '' ) {
			$source[] = $e( $r['year'] );
		}
		if ( ! empty( $source ) ) {
			$out[] = '<span class="dyzc-pub-source">' . implode( ', ', $source ) . '</span>.';
		}
		return implode( ' ', $out );
	}
}
