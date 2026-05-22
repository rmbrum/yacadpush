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
     * Abre a conexão com o banco.
     *
     * @param array $options Opções do plugin (contém db_host, db_port, etc.)
     * @return bool True se conectou, false caso contrário.
     */
    public function connect( array $options ): bool {
        $host     = isset( $options['db_host'] )     ? trim( $options['db_host'] )     : 'localhost';
        $port     = isset( $options['db_port'] )     ? (int) $options['db_port']       : 3306;
        $dbname   = isset( $options['db_name'] )     ? trim( $options['db_name'] )     : '';
        $user     = isset( $options['db_user'] )     ? trim( $options['db_user'] )     : '';
        $password = isset( $options['db_password'] ) ? $options['db_password']         : '';

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
     * @return object|WP_Error
     */
    public function query_publications(
        int    $page        = 1,
        int    $per_page    = 10,
        string $keyword     = '',
        string $type_filter = '',
        string $subtype_filter = ''
    ) {
        if ( ! $this->conn ) {
            return new WP_Error( 'db_not_connected', 'Database connection not established.' );
        }

        $offset = ( max( 1, $page ) - 1 ) * $per_page;

        // --- Construção da query com filtros opcionais ---
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
            $kw_like = '%' . $this->conn->real_escape_string( $keyword ) . '%';
            $params[] = $kw_like;
            $params[] = $kw_like;
            $types   .= 'ss';
        }

        if ( ! empty( $type_filter ) ) {
            $where_clauses[] = 'p.upload_type = ?';
            $params[]        = $type_filter;
            $types          .= 's';
        }

        // subtype é armazenado como keyword pelo ieee_to_zenodo.py
        // mas o upload_type (article, conferencepaper, etc.) mapeia subtypes
        if ( ! empty( $subtype_filter ) ) {
            $where_clauses[] = 'p.upload_type = ?';
            $params[]        = $subtype_filter;
            $types          .= 's';
        }

        $where_sql = implode( ' AND ', $where_clauses );

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
                p.ieee_html_url,
                p.ieee_pdf_url,
                p.zenodo_deposit_url
            FROM publications p
            WHERE {$where_sql}
            ORDER BY p.publication_date DESC, p.id DESC
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
            'report'           => 'Report',
            'thesis'           => 'Thesis',
            'dataset'          => 'Dataset',
            'software'         => 'Software',
            'publication'      => 'Publication',
        ];
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
