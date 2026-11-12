<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://hvck.cz/
 * @since      1.0.0
 *
 * @package    Academic_Publications_Showcase
 * @subpackage Academic_Publications_Showcase/admin/partials
 */

// Note: nonce verification is handled automatically by settings_fields() / WordPress Settings API.
$acadpush_options = get_option( $this->plugin_name );
$acadpush_choice = isset( $acadpush_options['choice'] ) ? $acadpush_options['choice'] : 'community';
$acadpush_id_community_orcid = isset( $acadpush_options['id_community_orcid'] ) ? $acadpush_options['id_community_orcid'] : '';
$acadpush_access_token = isset( $acadpush_options['access_token'] ) ? $acadpush_options['access_token'] : '';
$acadpush_orcid_id = isset( $acadpush_options['orcid_id'] ) ? $acadpush_options['orcid_id'] : '';
$acadpush_extra_keyword = isset( $acadpush_options['extra_keyword'] ) ? $acadpush_options['extra_keyword'] : '';
$acadpush_number_publications = isset( $acadpush_options['number_publications'] ) ? $acadpush_options['number_publications'] : 10;
$acadpush_filter_categories_raw = isset( $acadpush_options['filter_categories_raw'] ) ? $acadpush_options['filter_categories_raw'] : '';

// Database options
$acadpush_db_host     = isset( $acadpush_options['db_host'] )     ? $acadpush_options['db_host']     : 'localhost';
$acadpush_db_port     = isset( $acadpush_options['db_port'] )     ? $acadpush_options['db_port']     : 3306;
$acadpush_db_name     = isset( $acadpush_options['db_name'] )     ? $acadpush_options['db_name']     : '';
$acadpush_db_user     = isset( $acadpush_options['db_user'] )     ? $acadpush_options['db_user']     : '';

// Design options
$acadpush_primary_color = isset( $acadpush_options['primary_color'] ) ? $acadpush_options['primary_color'] : '#2271b1';
$acadpush_card_bg_color = isset( $acadpush_options['card_bg_color'] ) ? $acadpush_options['card_bg_color'] : '#ffffff';
$acadpush_card_border_color = isset( $acadpush_options['card_border_color'] ) ? $acadpush_options['card_border_color'] : '#dcdcde';
$acadpush_text_color = isset( $acadpush_options['text_color'] ) ? $acadpush_options['text_color'] : '#1d2327';
$acadpush_meta_text_color = isset( $acadpush_options['meta_text_color'] ) ? $acadpush_options['meta_text_color'] : '#646970';
$acadpush_font_family = isset( $acadpush_options['font_family'] ) ? $acadpush_options['font_family'] : 'system';
$acadpush_font_size = isset( $acadpush_options['font_size'] ) ? $acadpush_options['font_size'] : 'medium';
$acadpush_card_layout = isset( $acadpush_options['card_layout'] ) ? $acadpush_options['card_layout'] : 'standard';
$acadpush_border_radius = isset( $acadpush_options['border_radius'] ) ? $acadpush_options['border_radius'] : 'medium';

// Validate tab parameter against allowlist
$acadpush_allowed_tabs = array( 'api', 'database', 'design', 'usage' );
$acadpush_raw_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'api'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation, no state change
$acadpush_active_tab = in_array( $acadpush_raw_tab, $acadpush_allowed_tabs, true ) ? $acadpush_raw_tab : 'api';
?>

<div class="wrap dyzc-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>&tab=api"
		   class="nav-tab <?php echo esc_attr( $acadpush_active_tab === 'api' ? 'nav-tab-active' : '' ); ?>">
			API Settings
		</a>
		<a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>&tab=database"
		   class="nav-tab <?php echo esc_attr( $acadpush_active_tab === 'database' ? 'nav-tab-active' : '' ); ?>">
			Local Database
		</a>
		<a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>&tab=design"
		   class="nav-tab <?php echo esc_attr( $acadpush_active_tab === 'design' ? 'nav-tab-active' : '' ); ?>">
			Design
		</a>
		<a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>&tab=usage"
		   class="nav-tab <?php echo esc_attr( $acadpush_active_tab === 'usage' ? 'nav-tab-active' : '' ); ?>">
			Usage
		</a>
	</h2>

	<div class="dyzc-tab-content">
		<?php if ( $acadpush_active_tab === 'api' ) : ?>
			<!-- API Settings Tab -->
			<form method="post" name="<?php echo esc_attr( $this->plugin_name ); ?>_options" action="options.php">
				<?php
				settings_fields( $this->plugin_name );
				do_settings_sections( $this->plugin_name );
				?>

				<div class="dyzc-section">
					<h3>Data Source &amp; Zenodo API Configuration</h3>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-choice">
										Data Source
									</label>
								</th>
								<td>
									<select name="<?php echo esc_attr( $this->plugin_name ); ?>[choice]"
									        id="<?php echo esc_attr( $this->plugin_name ); ?>-choice">
										<option value="community" <?php selected( $acadpush_choice, 'community' ); ?>>Community (Zenodo API)</option>
										<option value="orcid"     <?php selected( $acadpush_choice, 'orcid' ); ?>>ORCID (Zenodo API)</option>
										<option value="database"  <?php selected( $acadpush_choice, 'database' ); ?>>Local Database (MySQL)</option>
									</select>
									<span class="dyzc-help-text">
										<strong>Community / ORCID:</strong> busca dados diretamente na API do Zenodo.<br>
										<strong>Local Database:</strong> usa banco MySQL próprio alimentado por <code>json_to_db.py</code>. Configure as credenciais na aba <em>Local Database</em>.
									</span>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-id-community-orcid">
										Community ID / ORCID
									</label>
								</th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[id_community_orcid]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-id-community-orcid"
									       value="<?php echo esc_attr( $acadpush_id_community_orcid ); ?>"
									       placeholder="e.g., digiwell or 0000-0002-1276-2702">
									<span class="dyzc-help-text">
										<strong>Community:</strong> Enter community ID (e.g., <code>digiwell</code>) - see <a href="https://zenodo.org/communities/digiwell" target="_blank">https://zenodo.org/communities/digiwell</a><br>
										<strong>ORCID:</strong> Enter ORCID identifier (e.g., <code>0000-0002-1276-2702</code>)
									</span>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-access-token">
										Zenodo Access Token <span class="dyzc-recommended">(recommended)</span>
									</label>
								</th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[access_token]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-access-token"
									       value="<?php echo esc_attr( $acadpush_access_token ); ?>"
									       placeholder="Your Zenodo personal access token">
									<span class="dyzc-help-text">
										Recommended for accessing full publication data. Get your token at:
										<a href="https://zenodo.org/account/settings/applications/tokens/new/" target="_blank">Zenodo → Account → Applications → Personal access tokens</a>
									</span>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-extra-keyword">
										Filter by Keyword (optional)
									</label>
								</th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[extra_keyword]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-extra-keyword"
									       value="<?php echo esc_attr( $acadpush_extra_keyword ); ?>"
									       placeholder="e.g., open-access, dataset">
									<span class="dyzc-help-text">
										Filter publications by Zenodo keyword/tag. Leave empty to show all publications.
									</span>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-number-publications">
										Publications Per Page
									</label>
								</th>
								<td>
									<input type="number"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[number_publications]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-number-publications"
									       value="<?php echo esc_attr( $acadpush_number_publications ); ?>"
									       min="1"
									       max="100"
									       style="width: 100px;">
									<span class="dyzc-help-text">
										Number of publications to display per page (default: 10, Zenodo API max: 100).
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php submit_button( 'Save API Settings', 'primary', 'submit', true ); ?>
			</form>

		<?php elseif ( $acadpush_active_tab === 'database' ) : ?>
			<!-- Local Database Tab -->
			<form method="post" name="<?php echo esc_attr( $this->plugin_name ); ?>_options" action="options.php">
				<?php
				settings_fields( $this->plugin_name );
				do_settings_sections( $this->plugin_name );
				?>

				<!-- Hidden fields para preservar configurações das outras abas -->
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[choice]" value="<?php echo esc_attr( $acadpush_choice ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[id_community_orcid]" value="<?php echo esc_attr( $acadpush_id_community_orcid ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[access_token]" value="<?php echo esc_attr( $acadpush_access_token ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[orcid_id]" value="<?php echo esc_attr( $acadpush_orcid_id ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[extra_keyword]" value="<?php echo esc_attr( $acadpush_extra_keyword ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[number_publications]" value="<?php echo esc_attr( $acadpush_number_publications ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[filter_categories_raw]" value="<?php echo esc_attr( $acadpush_filter_categories_raw ); ?>">

				<div class="dyzc-section">
					<h3>MySQL Database Credentials</h3>
					<p>Configure the connection to the MySQL database populated by <code>json_to_db.py</code>. Only used when <strong>Data Source</strong> is set to <em>Local Database</em>.</p>
					<p class="description">The plugin only reads this database: give it a MySQL user with <code>SELECT</code> privilege only. To keep the credentials out of the WordPress database, define <code>YACADPUSH_DB_HOST</code>, <code>YACADPUSH_DB_PORT</code>, <code>YACADPUSH_DB_NAME</code>, <code>YACADPUSH_DB_USER</code> and <code>YACADPUSH_DB_PASSWORD</code> in <code>wp-config.php</code>, or return them from the <code>yacadpush_db_credentials</code> filter (for per-site values on a multisite network).</p>
					<?php
					$acadpush_creds = ACADPUSH_DB::credentials( is_array( $acadpush_options ) ? $acadpush_options : array() );
					if ( ! empty( $acadpush_creds['overridden'] ) ) :
						?>
						<div class="notice notice-info inline"><p>
							<?php
							echo esc_html(
								sprintf(
									'Defined in wp-config.php or by the yacadpush_db_credentials filter, so the form value is ignored: %s.',
									implode( ', ', $acadpush_creds['overridden'] )
								)
							);
							?>
						</p></div>
					<?php endif; ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $this->plugin_name ); ?>-db-host">Host</label></th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[db_host]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-db-host"
									       value="<?php echo esc_attr( $acadpush_db_host ); ?>"
									       placeholder="localhost">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $this->plugin_name ); ?>-db-port">Port</label></th>
								<td>
									<input type="number"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[db_port]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-db-port"
									       value="<?php echo esc_attr( $acadpush_db_port ); ?>"
									       min="1" max="65535" style="width:100px;">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $this->plugin_name ); ?>-db-name">Database Name</label></th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[db_name]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-db-name"
									       value="<?php echo esc_attr( $acadpush_db_name ); ?>"
									       placeholder="publications_db">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $this->plugin_name ); ?>-db-user">User</label></th>
								<td>
									<input type="text"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[db_user]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-db-user"
									       value="<?php echo esc_attr( $acadpush_db_user ); ?>"
									       autocomplete="off">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $this->plugin_name ); ?>-db-password">Password</label></th>
								<td>
									<input type="password"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[db_password]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-db-password"
									       value=""
									       placeholder="<?php echo ! empty( $acadpush_options['db_password'] ) ? '••••••••' : ''; ?>"
									       autocomplete="new-password">
									<span class="dyzc-help-text">Deixe em branco para manter a senha atual.</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php submit_button( 'Save Database Settings', 'primary', 'submit', true ); ?>
			</form>

		<?php elseif ( $acadpush_active_tab === 'design' ) : ?>
			<!-- Design Tab -->
			<form method="post" name="<?php echo esc_attr( $this->plugin_name ); ?>_options" action="options.php">
				<?php
				settings_fields( $this->plugin_name );
				do_settings_sections( $this->plugin_name );
				?>

				<!-- Hidden fields to preserve API settings -->
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[choice]" value="<?php echo esc_attr( $acadpush_choice ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[id_community_orcid]" value="<?php echo esc_attr( $acadpush_id_community_orcid ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[access_token]" value="<?php echo esc_attr( $acadpush_access_token ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[orcid_id]" value="<?php echo esc_attr( $acadpush_orcid_id ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[extra_keyword]" value="<?php echo esc_attr( $acadpush_extra_keyword ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[number_publications]" value="<?php echo esc_attr( $acadpush_number_publications ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[filter_categories_raw]" value="<?php echo esc_attr( $acadpush_filter_categories_raw ); ?>">
				<!-- Hidden fields to preserve database settings -->
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[db_host]" value="<?php echo esc_attr( $acadpush_db_host ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[db_port]" value="<?php echo esc_attr( $acadpush_db_port ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[db_name]" value="<?php echo esc_attr( $acadpush_db_name ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->plugin_name ); ?>[db_user]" value="<?php echo esc_attr( $acadpush_db_user ); ?>">

				<div class="dyzc-section">
					<h3>Color Scheme</h3>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-primary-color">Primary Color</label>
								</th>
								<td>
									<input type="color"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[primary_color]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-primary-color"
									       value="<?php echo esc_attr( $acadpush_primary_color ); ?>"
									       class="color-picker">
									<span class="dyzc-help-text">Used for links and accent elements.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-card-bg-color">Card Background</label>
								</th>
								<td>
									<input type="color"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[card_bg_color]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-card-bg-color"
									       value="<?php echo esc_attr( $acadpush_card_bg_color ); ?>"
									       class="color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-card-border-color">Card Border</label>
								</th>
								<td>
									<input type="color"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[card_border_color]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-card-border-color"
									       value="<?php echo esc_attr( $acadpush_card_border_color ); ?>"
									       class="color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-text-color">Text Color</label>
								</th>
								<td>
									<input type="color"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[text_color]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-text-color"
									       value="<?php echo esc_attr( $acadpush_text_color ); ?>"
									       class="color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-meta-text-color">Meta Text Color</label>
								</th>
								<td>
									<input type="color"
									       name="<?php echo esc_attr( $this->plugin_name ); ?>[meta_text_color]"
									       id="<?php echo esc_attr( $this->plugin_name ); ?>-meta-text-color"
									       value="<?php echo esc_attr( $acadpush_meta_text_color ); ?>"
									       class="color-picker">
									<span class="dyzc-help-text">Used for dates, authors, and secondary information.</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="dyzc-section">
					<h3>Typography &amp; Layout</h3>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-font-family">Font Family</label>
								</th>
								<td>
									<select name="<?php echo esc_attr( $this->plugin_name ); ?>[font_family]"
									        id="<?php echo esc_attr( $this->plugin_name ); ?>-font-family">
										<option value="system" <?php selected( $acadpush_font_family, 'system' ); ?>>System Default</option>
										<option value="Inter" <?php selected( $acadpush_font_family, 'Inter' ); ?>>Inter (Google Fonts)</option>
										<option value="Roboto" <?php selected( $acadpush_font_family, 'Roboto' ); ?>>Roboto (Google Fonts)</option>
										<option value="Open Sans" <?php selected( $acadpush_font_family, 'Open Sans' ); ?>>Open Sans (Google Fonts)</option>
										<option value="Merriweather" <?php selected( $acadpush_font_family, 'Merriweather' ); ?>>Merriweather (Google Fonts)</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-font-size">Font Size</label>
								</th>
								<td>
									<select name="<?php echo esc_attr( $this->plugin_name ); ?>[font_size]"
									        id="<?php echo esc_attr( $this->plugin_name ); ?>-font-size">
										<option value="small" <?php selected( $acadpush_font_size, 'small' ); ?>>Small</option>
										<option value="medium" <?php selected( $acadpush_font_size, 'medium' ); ?>>Medium (Default)</option>
										<option value="large" <?php selected( $acadpush_font_size, 'large' ); ?>>Large</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-card-layout">Card Layout</label>
								</th>
								<td>
									<select name="<?php echo esc_attr( $this->plugin_name ); ?>[card_layout]"
									        id="<?php echo esc_attr( $this->plugin_name ); ?>-card-layout">
										<option value="standard" <?php selected( $acadpush_card_layout, 'standard' ); ?>>Standard</option>
										<option value="compact" <?php selected( $acadpush_card_layout, 'compact' ); ?>>Compact</option>
										<option value="detailed" <?php selected( $acadpush_card_layout, 'detailed' ); ?>>Detailed</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $this->plugin_name ); ?>-border-radius">Border Radius</label>
								</th>
								<td>
									<select name="<?php echo esc_attr( $this->plugin_name ); ?>[border_radius]"
									        id="<?php echo esc_attr( $this->plugin_name ); ?>-border-radius">
										<option value="none" <?php selected( $acadpush_border_radius, 'none' ); ?>>None (0px)</option>
										<option value="small" <?php selected( $acadpush_border_radius, 'small' ); ?>>Small (4px)</option>
										<option value="medium" <?php selected( $acadpush_border_radius, 'medium' ); ?>>Medium (8px)</option>
										<option value="large" <?php selected( $acadpush_border_radius, 'large' ); ?>>Large (12px)</option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php submit_button( 'Save Design Settings', 'primary', 'submit', true ); ?>
			</form>

		<?php else : ?>
			<!-- Usage Tab -->
			<div class="dyzc-usage-card">
				<h3>Basic Shortcode</h3>
				<p>Add this shortcode to any page or post to display your Zenodo publications:</p>
				<div class="dyzc-code-block">
					<code>[showcase-publications]</code>
				</div>
				<p class="description">This will display all publications from your configured Community or ORCID.</p>
			</div>

			<div class="dyzc-usage-card">
				<h3>Available Parameters</h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Description</th>
							<th>Example Values</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>keyword</code></td>
							<td>Filter publications by Zenodo keyword/tag. Overrides the "Extra keyword" setting in API Settings tab.</td>
							<td><code>'DMP'</code>, <code>'research'</code>, <code>'2024'</code></td>
						</tr>
						<tr>
							<td><code>type</code></td>
							<td>Filter by general resource type (publication, dataset, software, etc.)</td>
							<td><code>'publication'</code>, <code>'dataset'</code>, <code>'software'</code></td>
						</tr>
						<tr>
							<td><code>subtype</code></td>
							<td>Filter by publication subtype (article, book, thesis, etc.). Only works when type='publication'.</td>
							<td><code>'article'</code>, <code>'conferencepaper'</code>, <code>'thesis'</code></td>
						</tr>
						<tr>
							<td><code>lang</code></td>
							<td>Set the display language for labels and date formatting.</td>
							<td><code>'en'</code> (English), <code>'cs'</code> (Czech), <code>'pt'</code> (Portuguese)</td>
						</tr>
						<tr>
							<td><code>view</code></td>
							<td>Layout: <code>cards</code> (default) or <code>compact</code>, one reference line per publication grouped under year headings.</td>
							<td><code>'compact'</code></td>
						</tr>
						<tr>
							<td><code>year</code></td>
							<td>Only publications from this year or range of years (either end may be left open).</td>
							<td><code>'2024'</code>, <code>'2015-2020'</code>, <code>'2010-'</code></td>
						</tr>
						<tr>
							<td><code>year_filter</code></td>
							<td>Show a year selector above the list. In database mode it lists the years present, with counts; in Zenodo modes it lists every year from the oldest deposited record to the current year.</td>
							<td><code>'yes'</code></td>
						</tr>
						<tr>
							<td><code>citation</code></td>
							<td>Citation style of the compact view: <code>simple</code> (default), <code>abnt</code> (NBR 6023:2018), <code>apa</code> (7th), <code>ieee</code>, <code>vancouver</code>, <code>chicago</code> (author-date).</td>
							<td><code>'abnt'</code></td>
						</tr>
						<tr>
							<td><code>names</code></td>
							<td>Given names in full (<code>full</code>, default) or as initials (<code>initials</code>). Applies to ABNT, Chicago and simple; APA, IEEE and Vancouver always use initials.</td>
							<td><code>'initials'</code></td>
						</tr>
						<tr>
							<td><code>max_authors</code></td>
							<td>List this many authors, then "et al.", instead of the style's own rule.</td>
							<td><code>'3'</code></td>
						</tr>
						<tr>
							<td><code>per_page</code></td>
							<td>Publications per page for this shortcode, instead of the API Settings value (max. 1000 in database mode, 100 with Zenodo).</td>
							<td><code>'100'</code></td>
						</tr>
						<tr>
							<td><code>group</code></td>
							<td>Compact view: <code>type</code> adds sub-headings by publication type inside each year (with counts in database mode).</td>
							<td><code>'type'</code></td>
						</tr>
						<tr>
							<td><code>citation_select</code></td>
							<td>Show a selector so the visitor can switch the citation style (compact view).</td>
							<td><code>'yes'</code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="dyzc-usage-card">
				<h3>Examples</h3>

				<div class="dyzc-example">
					<h4>Example 1: Display all publications in English</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications lang='en']</code>
					</div>
				</div>

				<div class="dyzc-example">
					<h4>Example 2: Filter by keyword "DMP"</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications keyword='DMP']</code>
					</div>
					<p class="description">Shows only publications tagged with "DMP" in Zenodo.</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 3: Filter by publication type</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications type='publication' subtype='article']</code>
					</div>
					<p class="description">Display only journal articles.</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 4: Show only datasets</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications type='dataset']</code>
					</div>
					<p class="description">Display only datasets (no subtype needed).</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 5: Combine keyword and type filters</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications type='publication' subtype='conferencepaper' keyword='AI']</code>
					</div>
					<p class="description">Display only conference papers about AI.</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 6: Compact list with a year selector</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications view='compact' year_filter='yes']</code>
					</div>
					<p class="description">One line per publication (authors, title, venue, year), grouped by year, with a selector to show a single year. Clicking a line opens the full card with the whole abstract; the icon next to the title opens the publication itself.</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 7: ABNT references with abbreviated names</h4>
					<div class="dyzc-code-block">
						<code>[showcase-publications view='compact' citation='abnt' names='initials' citation_select='yes']</code>
					</div>
					<p class="description">Compact list in ABNT NBR 6023:2018 ("SILVA, J. C."), with a selector for the other styles.</p>
				</div>

				<div class="dyzc-example">
					<h4>Example 8: Multiple pages with different filters</h4>
					<p>You can use different keywords on different pages of your site:</p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong>Publications page:</strong> <code>[showcase-publications]</code></li>
						<li><strong>Projects page:</strong> <code>[showcase-publications keyword='projects']</code></li>
						<li><strong>Datasets page:</strong> <code>[showcase-publications keyword='dataset']</code></li>
					</ul>
				</div>
			</div>

			<div class="dyzc-usage-card">
				<h3>Tips</h3>
				<ul style="list-style: disc; margin-left: 20px; line-height: 1.8;">
					<li>Make sure to configure your <strong>Community ID</strong> or <strong>ORCID</strong> in the <strong>API Settings</strong> tab first.</li>
					<li>Adding a <strong>Zenodo Access Token</strong> is recommended for accessing full publication metadata.</li>
					<li>Use the <strong>Design</strong> tab to customize colors and layout to match your site's theme.</li>
					<li>Test your shortcode on a draft page before publishing.</li>
					<li>Publications are cached for 1 hour to improve performance.</li>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
