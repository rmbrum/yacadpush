<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * Fonte de dados alternativa: banco MySQL próprio.
 *
 * Lê publicações das tabelas criadas por schema.sql e retorna um objeto
 * idêntico ao retornado pela API do Zenodo, de modo que o restante do
 * plugin funcione sem alterações na camada de renderização.
 *
 * @package    Academic_Publications_Showcase
 * @subpackage Academic_Publications_Showcase/includes
 */
class ACADPUSH_DB {

    /** @var mysqli|null */
    private $conn = null;

    /** @var string|null Mensagem do último erro de conexão */
    public $connect_error = null;

    /**
     * `upload_type` values that belong to the Zenodo `publication` family
     * (the generic `publication` plus every publication subtype).
     */
    const PUBLICATION_UPLOAD_TYPES = [
        'publication', 'annotationcollection', 'book', 'section', 'conferencepaper',
        'datamanagementplan', 'article', 'patent', 'preprint', 'deliverable',
        'milestone', 'proposal', 'report', 'softwaredocumentation',
        'taxonomictreatment', 'technicalnote', 'thesis', 'workingpaper', 'other',
    ];

    /**
     * Cheap fingerprint of the data: changes whenever json_to_db.py inserts,
     * updates or deletes rows. Used in the cache key so a reload shows up at once.
     */
    public function data_version(): string {
        if ( ! $this->conn ) {
            return '';
        }
        $res = $this->conn->query( 'SELECT COUNT(*), MAX(updated_at) FROM publications' );
        if ( ! $res ) {
            return '';
        }
        $row = $res->fetch_row();
        return implode( '|', array_map( 'strval', $row ) );
    }

    /** Credential field => wp-config.php constant that overrides it. */
    const CREDENTIAL_CONSTANTS = [
        'host'     => 'YACADPUSH_DB_HOST',
        'port'     => 'YACADPUSH_DB_PORT',
        'name'     => 'YACADPUSH_DB_NAME',
        'user'     => 'YACADPUSH_DB_USER',
        'password' => 'YACADPUSH_DB_PASSWORD',
    ];

    /**
     * Effective database credentials, in increasing precedence:
     *   1. the Local Database tab (stored in wp_options);
     *   2. YACADPUSH_DB_* constants (wp-config.php), field by field;
     *   3. the `yacadpush_db_credentials` filter, e.g. from a mu-plugin that returns
     *      per-site values on a multisite network, where wp-config.php is shared.
     *
     * The filter receives and returns [host, port, name, user, password] and also gets
     * the plugin options as a second argument.
     *
     * @return array{host:string,port:int,name:string,user:string,password:string,overridden:string[]}
     *         `overridden` lists the fields not taken from the settings form.
     */
    public static function credentials( array $options ): array {
        $form = [
            'host'     => isset( $options['db_host'] )     ? trim( (string) $options['db_host'] ) : 'localhost',
            'port'     => isset( $options['db_port'] )     ? (int) $options['db_port']             : 3306,
            'name'     => isset( $options['db_name'] )     ? trim( (string) $options['db_name'] ) : '',
            'user'     => isset( $options['db_user'] )     ? trim( (string) $options['db_user'] ) : '',
            'password' => isset( $options['db_password'] ) ? (string) $options['db_password']    : '',
        ];

        $creds = $form;
        foreach ( self::CREDENTIAL_CONSTANTS as $field => $constant ) {
            if ( defined( $constant ) ) {
                $creds[ $field ] = constant( $constant );
            }
        }
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'yacadpush_db_credentials', $creds, $options );
            if ( is_array( $filtered ) ) {
                $creds = array_merge( $creds, array_intersect_key( $filtered, $form ) );
            }
        }

        $normalize = function ( array $c ): array {
            return [
                'host'     => trim( (string) $c['host'] ) !== '' ? trim( (string) $c['host'] ) : 'localhost',
                'port'     => (int) $c['port'] > 0 ? (int) $c['port'] : 3306,
                'name'     => trim( (string) $c['name'] ),
                'user'     => trim( (string) $c['user'] ),
                'password' => (string) $c['password'],
            ];
        };
        $form  = $normalize( $form );
        $creds = $normalize( $creds );

        $overridden = [];
        foreach ( array_keys( $form ) as $field ) {
            if ( defined( self::CREDENTIAL_CONSTANTS[ $field ] ) || $creds[ $field ] !== $form[ $field ] ) {
                $overridden[] = $field;
            }
        }
        $creds['overridden'] = $overridden;
        return $creds;
    }

    /**
     * Abre a conexão com o banco.
     *
     * @param array $options Opções do plugin (contém db_host, db_port, etc.)
     * @return bool True se conectou, false caso contrário.
     */
    public function connect( array $options ): bool {
        $creds    = self::credentials( $options );
        $host     = $creds['host'];
        $port     = $creds['port'];
        $dbname   = $creds['name'];
        $user     = $creds['user'];
        $password = $creds['password'];

        if ( empty( $dbname ) || empty( $user ) ) {
            $this->connect_error = 'Database name and user are required.';
            return false;
        }

        $this->conn = new mysqli( $host, $user, $password, $dbname, $port );

        if ( $this->conn->connect_errno ) {
            $this->connect_error = $this->conn->connect_error;
            $this->conn = null;
            return false;
        }

        $this->conn->set_charset( 'utf8mb4' );
        return true;
    }

    /**
     * Fecha a conexão.
     */
    public function close(): void {
        if ( $this->conn ) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    /**
     * Consulta publicações e retorna um objeto no formato da API Zenodo.
     *
     * O objeto retornado replica a estrutura:
     *   $result->hits->total->value  (int)
     *   $result->hits->hits[]        (array de objetos de publicação)
     *
     * Cada hit replica os campos usados por display_zenodo_data():
     *   $hit->doi
     *   $hit->metadata->title
     *   $hit->metadata->publication_date
     *   $hit->metadata->resource_type->title
     *   $hit->metadata->journal_title  (ou $hit->metadata->journal->title)
     *   $hit->metadata->creators[]->name
     *   $hit->metadata->creators[]->affiliation
     *   $hit->metadata->description
     *   $hit->metadata->keywords[]
     *   $hit->links->doi
     *   $hit->links->pdf          (link direto para o PDF — extensão própria)
     *   $hit->links->self_html    (link para o Zenodo, se disponível)
     *
     * @param int    $page            Página atual (1-based).
     * @param int    $per_page        Registros por página.
     * @param string $keyword         Filtro de palavra-chave (opcional).
     * @param string $type_filter     Filtro de upload_type (opcional).
     * @param string $subtype_filter  Filtro de subtype via keyword matching (opcional).
     * @param int    $year_from       First publication year to include (0 = no lower bound).
     * @param int    $year_to         Last publication year to include (0 = no upper bound).
     * @return object|WP_Error
     */
    public function query_publications(
        int    $page        = 1,
        int    $per_page    = 10,
        string $keyword     = '',
        string $type_filter = '',
        string $subtype_filter = '',
        int    $year_from   = 0,
        int    $year_to     = 0,
        string $order       = 'date'
    ) {
        if ( ! $this->conn ) {
            return new WP_Error( 'db_not_connected', 'Database connection not established.' );
        }

        $offset = ( max( 1, $page ) - 1 ) * $per_page;

        list( $where_sql, $params, $types ) = $this->build_where(
            $keyword, $type_filter, $subtype_filter, $year_from, $year_to
        );

        return $this->fetch_page( $where_sql, $params, $types, $per_page, $offset, $order );
    }

    /**
     * Order of the type groups in the compact view (group="type"); every other
     * upload type falls into "other", after these.
     */
    const TYPE_GROUP_ORDER = [ 'article', 'conferencepaper', 'section', 'book', 'preprint', 'report', 'thesis' ];

    /** Group key of an upload type. */
    public static function type_group( string $upload_type ): string {
        return in_array( $upload_type, self::TYPE_GROUP_ORDER, true ) ? $upload_type : 'other';
    }

    /** SQL rank of p.upload_type in TYPE_GROUP_ORDER (constants only, nothing bound). */
    private static function type_rank_sql(): string {
        $sql = 'CASE p.upload_type';
        foreach ( self::TYPE_GROUP_ORDER as $i => $t ) {
            $sql .= " WHEN '" . $t . "' THEN " . ( $i + 1 );
        }
        return $sql . ' ELSE ' . ( count( self::TYPE_GROUP_ORDER ) + 1 ) . ' END';
    }

    /**
     * Number of publications per year and type group, under the given filters.
     *
     * @return array<string,array<string,int>>|WP_Error year ('' when unknown) => [group => count].
     */
    public function count_by_year_and_type(
        string $keyword = '',
        string $type_filter = '',
        string $subtype_filter = '',
        int    $year_from = 0,
        int    $year_to = 0
    ) {
        if ( ! $this->conn ) {
            return new WP_Error( 'db_not_connected', 'Database connection not established.' );
        }

        list( $where_sql, $params, $types ) = $this->build_where(
            $keyword, $type_filter, $subtype_filter, $year_from, $year_to
        );

        $sql  = "SELECT LEFT(p.publication_date, 4) AS y, p.upload_type AS t, COUNT(*) AS n
                 FROM publications p
                 WHERE {$where_sql}
                 GROUP BY y, t";
        $stmt = $this->conn->prepare( $sql );
        if ( ! empty( $params ) ) {
            $stmt->bind_param( $types, ...$params );
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $counts = [];
        while ( $row = $res->fetch_assoc() ) {
            $year  = preg_match( '/^\d{4}$/', (string) $row['y'] ) ? (string) $row['y'] : '';
            $group = self::type_group( (string) $row['t'] );
            $counts[ $year ][ $group ] = ( $counts[ $year ][ $group ] ?? 0 ) + (int) $row['n'];
        }
        $stmt->close();
        return $counts;
    }

    /**
     * WHERE clause shared by the page query and the year list.
     *
     * @return array{0:string,1:array,2:string} SQL, bound values, bind_param types.
     */
    private function build_where(
        string $keyword,
        string $type_filter,
        string $subtype_filter,
        int    $year_from,
        int    $year_to
    ): array {
        $where_clauses = [ 'p.access_right != "closed"' ];
        $params        = [];
        $types         = '';

        if ( ! empty( $keyword ) ) {
            // Busca por keyword nas tabelas keywords e no título
            $where_clauses[] = '(
                EXISTS (
                    SELECT 1 FROM keywords k
                    WHERE k.publication_id = p.id
                      AND k.keyword LIKE ?
                )
                OR p.title LIKE ?
            )';
            // The value is bound as a parameter, so it must not be SQL-escaped;
            // only the LIKE wildcards (and the escape character) are escaped.
            $kw_like = '%' . addcslashes( $keyword, '\\%_' ) . '%';
            $params[] = $kw_like;
            $params[] = $kw_like;
            $types   .= 'ss';
        }

        // The shortcode speaks Zenodo vocabulary: `type` is a family
        // (publication, dataset, software...) and `subtype` a publication kind
        // (article, conferencepaper...). The database stores a single
        // `upload_type`, which holds the subtype for publications.
        if ( ! empty( $subtype_filter ) ) {
            $where_clauses[] = 'p.upload_type = ?';
            $params[]        = $subtype_filter;
            $types          .= 's';
        } elseif ( ! empty( $type_filter ) ) {
            $family = $type_filter === 'publication'
                ? self::PUBLICATION_UPLOAD_TYPES
                : [ $type_filter ];
            $where_clauses[] = 'p.upload_type IN (' . implode( ',', array_fill( 0, count( $family ), '?' ) ) . ')';
            foreach ( $family as $t ) {
                $params[] = $t;
                $types   .= 's';
            }
        }

        // publication_date is ISO text ('YYYY', 'YYYY-MM' or 'YYYY-MM-DD').
        if ( $year_from > 0 ) {
            $where_clauses[] = 'LEFT(p.publication_date, 4) >= ?';
            $params[]        = sprintf( '%04d', $year_from );
            $types          .= 's';
        }
        if ( $year_to > 0 ) {
            $where_clauses[] = 'LEFT(p.publication_date, 4) <= ?';
            $params[]        = sprintf( '%04d', $year_to );
            $types          .= 's';
        }

        return [ implode( ' AND ', $where_clauses ), $params, $types ];
    }

    /**
     * Publication years present under the given filters, newest first.
     *
     * @return array<int,int>|WP_Error Year => number of publications.
     */
    public function list_years(
        string $keyword = '',
        string $type_filter = '',
        string $subtype_filter = '',
        int    $year_from = 0,
        int    $year_to = 0
    ) {
        if ( ! $this->conn ) {
            return new WP_Error( 'db_not_connected', 'Database connection not established.' );
        }

        list( $where_sql, $params, $types ) = $this->build_where(
            $keyword, $type_filter, $subtype_filter, $year_from, $year_to
        );

        $sql  = "SELECT LEFT(p.publication_date, 4) AS y, COUNT(*) AS n
                 FROM publications p
                 WHERE {$where_sql} AND p.publication_date REGEXP '^[0-9]{4}'
                 GROUP BY y ORDER BY y DESC";
        $stmt = $this->conn->prepare( $sql );
        if ( ! empty( $params ) ) {
            $stmt->bind_param( $types, ...$params );
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $years = [];
        while ( $row = $res->fetch_assoc() ) {
            $years[ (int) $row['y'] ] = (int) $row['n'];
        }
        $stmt->close();
        return $years;
    }

    /**
     * Run the publications query (already filtered) and shape it like the Zenodo API.
     *
     * @return object
     */
    private function fetch_page( string $where_sql, array $params, string $types, int $per_page, int $offset, string $order = 'date' ) {
        // group="type" needs each year's publications ordered by type group, so a
        // page break never interleaves the groups.
        $order_sql = $order === 'type'
            ? 'LEFT(p.publication_date, 4) DESC, ' . self::type_rank_sql() . ', p.publication_date DESC, p.id DESC'
            : 'p.publication_date DESC, p.id DESC';

        // --- Contagem total ---
        $count_sql  = "SELECT COUNT(*) FROM publications p WHERE {$where_sql}";
        $count_stmt = $this->conn->prepare( $count_sql );
        if ( ! empty( $params ) ) {
            $count_stmt->bind_param( $types, ...$params );
        }
        $count_stmt->execute();
        $count_stmt->bind_result( $total );
        $count_stmt->fetch();
        $count_stmt->close();

        // --- Registros da página ---
        $data_sql = "
            SELECT
                p.id,
                p.doi,
                p.title,
                p.description,
                p.upload_type,
                p.publication_date,
                p.journal_title,
                p.journal_volume,
                p.journal_issue,
                p.journal_pages,
                p.conference_title,
                p.conference_place,
                p.conference_dates,
                p.publisher,
                p.ieee_html_url,
                p.ieee_pdf_url,
                p.zenodo_deposit_url
            FROM publications p
            WHERE {$where_sql}
            ORDER BY {$order_sql}
            LIMIT ? OFFSET ?
        ";

        $data_params = array_merge( $params, [ $per_page, $offset ] );
        $data_types  = $types . 'ii';

        $stmt = $this->conn->prepare( $data_sql );
        $stmt->bind_param( $data_types, ...$data_params );
        $stmt->execute();
        $result = $stmt->get_result();

        $hits = [];

        while ( $row = $result->fetch_assoc() ) {
            $pub_id = (int) $row['id'];

            // Autores
            $authors     = $this->fetch_authors( $pub_id );
            // Keywords
            $keywords    = $this->fetch_keywords( $pub_id );
            // Related identifiers (para DOI e PDF links)
            $related     = $this->fetch_related_identifiers( $pub_id );

            // Monta objeto metadata compatível com o que o plugin espera do Zenodo
            $metadata = (object) [
                'title'            => $row['title'],
                'description'      => $row['description'],
                'publication_date' => $row['publication_date'],
                'resource_type'    => (object) [
                    'title' => $this->upload_type_label( $row['upload_type'] ),
                    'subtype' => $row['upload_type'], // read by the citation styles
                ],
                'creators'  => $authors,
                'keywords'  => $keywords,
                // Campo journal: replica estrutura do Zenodo
                'journal'   => (object) [
                    'title'  => $row['journal_title'] ?? '',
                    'volume' => $row['journal_volume'] ?? '',
                    'issue'  => $row['journal_issue']  ?? '',
                    'pages'  => $row['journal_pages']  ?? '',
                ],
                // Atalho direto (usado em alguns templates)
                'journal_title' => $row['journal_title'] ?? '',
                // Zenodo's `meeting` block (conference papers).
                'meeting'   => (object) [
                    'title' => $row['conference_title'] ?? '',
                    'place' => $row['conference_place'] ?? '',
                    'dates' => $row['conference_dates'] ?? '',
                ],
                'publisher' => $row['publisher'] ?? '',
            ];

            // Monta links
            $doi_url  = ! empty( $row['doi'] ) ? 'https://doi.org/' . $row['doi'] : '';
            $pdf_url  = $row['ieee_pdf_url'] ?? '';

            // Fallback: tenta encontrar PDF nos related_identifiers
            if ( empty( $pdf_url ) ) {
                foreach ( $related as $ri ) {
                    if (
                        $ri->relation === 'isIdenticalTo'
                        && strpos( $ri->identifier, 'ieeexplore' ) !== false
                    ) {
                        $pdf_url = $ri->identifier;
                        break;
                    }
                }
            }

            $links = (object) [
                'doi'       => $doi_url,
                'pdf'       => $pdf_url,
                'self_html' => $row['zenodo_deposit_url'] ?? '',
            ];

            $hit = (object) [
                'doi'      => $row['doi'],
                'metadata' => $metadata,
                'links'    => $links,
            ];

            $hits[] = $hit;
        }

        $stmt->close();

        // Monta o objeto no formato hits.hits do Zenodo
        return (object) [
            'hits' => (object) [
                'total' => (object) [ 'value' => (int) $total ],
                'hits'  => $hits,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function fetch_authors( int $pub_id ): array {
        $sql  = 'SELECT full_name, affiliation, orcid FROM authors WHERE publication_id = ? ORDER BY position ASC';
        $stmt = $this->conn->prepare( $sql );
        $stmt->bind_param( 'i', $pub_id );
        $stmt->execute();
        $res  = $stmt->get_result();

        $authors = [];
        while ( $row = $res->fetch_assoc() ) {
            $authors[] = (object) [
                'name'        => $row['full_name'],
                'affiliation' => $row['affiliation'] ?? '',
                'orcid'       => $row['orcid'] ?? '',
            ];
        }
        $stmt->close();
        return $authors;
    }

    private function fetch_keywords( int $pub_id ): array {
        $sql  = 'SELECT keyword FROM keywords WHERE publication_id = ? ORDER BY id ASC';
        $stmt = $this->conn->prepare( $sql );
        $stmt->bind_param( 'i', $pub_id );
        $stmt->execute();
        $res  = $stmt->get_result();

        $keywords = [];
        while ( $row = $res->fetch_assoc() ) {
            $keywords[] = $row['keyword'];
        }
        $stmt->close();
        return $keywords;
    }

    private function fetch_related_identifiers( int $pub_id ): array {
        $sql  = 'SELECT identifier, relation, resource_type FROM related_identifiers WHERE publication_id = ?';
        $stmt = $this->conn->prepare( $sql );
        $stmt->bind_param( 'i', $pub_id );
        $stmt->execute();
        $res  = $stmt->get_result();

        $items = [];
        while ( $row = $res->fetch_assoc() ) {
            $items[] = (object) $row;
        }
        $stmt->close();
        return $items;
    }

    private function upload_type_label( string $type ): string {
        $map = [
            'article'          => 'Journal Article',
            'conferencepaper'  => 'Conference Paper',
            'book'             => 'Book',
            'section'          => 'Book Section',
            'preprint'         => 'Preprint',
            'report'           => 'Report',
            'thesis'           => 'Thesis',
            'dataset'          => 'Dataset',
            'software'         => 'Software',
            'publication'      => 'Publication',
        ];
        if ( function_exists( 'get_locale' ) && strpos( strtolower( get_locale() ), 'pt' ) === 0 ) {
            $map = [
                'article'          => 'Artigo em periódico',
                'conferencepaper'  => 'Artigo em evento',
                'book'             => 'Livro',
                'section'          => 'Capítulo de livro',
                'preprint'         => 'Preprint',
                'report'           => 'Relatório',
                'thesis'           => 'Tese ou dissertação',
                'dataset'          => 'Conjunto de dados',
                'software'         => 'Software',
                'publication'      => 'Publicação',
            ] + $map;
        }
        return $map[ $type ] ?? ucfirst( $type );
    }

    /**
     * Testa a conexão e retorna mensagem de status.
     * Usado na página de configurações para validar credenciais.
     */
    public static function test_connection( array $options ): array {
        $db = new self();
        if ( $db->connect( $options ) ) {
            // Verifica se a tabela publications existe
            $res = $db->conn->query( "SHOW TABLES LIKE 'publications'" );
            $has_table = $res && $res->num_rows > 0;
            $db->close();
            if ( $has_table ) {
                return [ 'success' => true, 'message' => 'Connection successful. Table "publications" found.' ];
            } else {
                return [ 'success' => true, 'message' => 'Connected, but table "publications" not found. Run schema.sql first.' ];
            }
        }
        return [ 'success' => false, 'message' => 'Connection failed: ' . $db->connect_error ];
    }
}
