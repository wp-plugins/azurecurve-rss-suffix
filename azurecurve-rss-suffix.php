<?php
/*
Plugin Name: azurecurve RSS Suffix
Plugin URI: http://wordpress.azurecurve.co.uk/plugins/rss-suffix/
Description: Add a suffix to rss entries
Version: 1.0.1
Author: azurecurve
Author URI: http://wordpress.azurecurve.co.uk/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt
 */

register_activation_hook( __FILE__, 'azc_rss_set_default_options' );

function azc_rss_set_default_options($networkwide) {
	
	$new_options = array(
				'rss_suffix' => ''
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_rss_options' ) === false ) {
					add_option( 'azc_rss_options', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_rss_options' ) === false ) {
				add_option( 'azc_rss_options', $new_options );
			}
		}
		if ( get_site_option( 'azc_rss_options' ) === false ) {
			add_site_option( 'azc_rss_options', $new_options );
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_rss_options' ) === false ) {
			add_option( 'azc_rss_options', $new_options );
		}
	}
}

add_filter('the_excerpt_rss', 'agentwp_append_rss_suffix');
add_filter('the_content', 'agentwp_append_rss_suffix');

function agentwp_append_rss_suffix($content) {
	global $post;
	
	if(is_feed()){
		$options = get_option( 'azc_rss_options' );
		
		$rss_suffix = '';
		if (strlen($options['rss_suffix']) > 0){
			$rss_suffix = stripslashes($options['rss_suffix']);
		}else{
			$network_options = get_site_option( 'azc_rss_options' );
			if (strlen($network_options['rss_suffix']) > 0){
				$rss_suffix = stripslashes($network_options['rss_suffix']);
			}
		}
		
		if (strlen($rss_suffix) > 0){
			$rss_suffix = str_replace('$site_url', get_site_url(), $rss_suffix);
			$rss_suffix = str_replace('$site_title', get_bloginfo('name'), $rss_suffix);
			$rss_suffix = str_replace('$site_tagline', get_bloginfo('description'), $rss_suffix);
			$rss_suffix = str_replace('$post_url', get_permalink( $post->ID ), $rss_suffix);
			$rss_suffix = str_replace('$post_title', $post->post_title, $rss_suffix);
			$content = $content.'<p>'.$rss_suffix.'</p>';
		}
	}
	return $content;
	
}
 
add_filter('plugin_action_links', 'azc_rss_plugin_action_links', 10, 2);

function azc_rss_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azurecurve-rss-suffix">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}


add_action( 'admin_menu', 'azc_rss_settings_menu' );

function azc_rss_settings_menu() {
	add_options_page( 'azurecurve RSS Suffix',
	'azurecurve RSS Suffix', 'manage_options',
	'azurecurve-rss-suffix', 'azc_rss_config_page' );
}

function azc_rss_config_page() {
	if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_rss_options' );
	?>
	<div id="azc-rss-general" class="wrap">
		<fieldset>
			<h2>azurecurve RSS Suffix Configuration</h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_rss_options" />
				<input name="page_options" type="hidden" value="rss_suffix" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_rss' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p>Set the suffix to be added to all items in the RSS feed. If multisite being used leave this suffix blank to get multisite default.</p>
				</td></tr>
				<tr><th scope="row"><label for="width">RSS Suffix</label></th><td>
					<textarea name="rss_suffix" rows="4" cols="50" id="rss_suffix" class="regular-text code"><?php echo stripslashes($options['rss_suffix'])?></textarea>
					<p class="description">Set the default suffix for RSS. The following variables can be used;
					<ol><li>$site_title</li>
					<li>$site_tagline</li>
					<li>$site_url</li>
					<li>$post_url</li>
					<li>$post_title</li></ol>
					For example: <em>Read original post &lt;a href='$post_url'&gt;$post_title&lt;/a&gt; at &lt;a href='$site_url'&gt;$site_title|$site_tagline&lt;/a&gt;</em>
					</p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }

add_action( 'admin_init', 'azc_rss_admin_init' );

function azc_rss_admin_init() {
	add_action( 'admin_post_save_azc_rss_options', 'process_azc_rss_options' );
}

function process_azc_rss_options() {
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){
		wp_die( 'Not allowed' );
	}
	// Check that nonce field created in configuration form is present
	check_admin_referer( 'azc_rss' );
	settings_fields('azc_rss');
	
	// Retrieve original plugin options array
	$options = get_option( 'azc_rss_options' );
	
	$option_name = 'rss_suffix';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}
	
	// Store updated options array to database
	update_option( 'azc_rss_options', $options );
	
	// Redirect the page to the configuration form that was processed
	wp_redirect( add_query_arg( 'page', 'azurecurve-rss-suffix', admin_url( 'options-general.php' ) ) );
	exit;
}

add_action('network_admin_menu', 'add_azc_rss_network_settings_page');

function add_azc_rss_network_settings_page() {
	if (function_exists('is_multisite') && is_multisite()) {
		add_submenu_page(
			'settings.php',
			'azurecurve RSS Suffix Settings',
			'azurecurve RSS Suffix',
			'manage_network_options',
			'azurecurve-rss-suffix',
			'azc_rss_network_settings_page'
			);
	}
}

function azc_rss_network_settings_page(){
	$options = get_site_option('azc_rss_options');

	?>
	<div id="azc-rss-general" class="wrap">
		<fieldset>
			<h2>azurecurve RSS Suffix Network Configuration</h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_rss_options" />
				<input name="page_options" type="hidden" value="suffix" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_rss' ); ?>
				<table class="form-table">
				<tr><td colspan=2>
					<p>Set the suffix to be added to all items in the RSS feed. If multisite being used leave this suffix blank to get multisite default.</p>
				</td></tr>
				<tr><th scope="row"><label for="width">Default RSS Suffix</label></th><td>
					<textarea name="rss_suffix" rows="4" cols="50" id="rss_suffix" class="regular-text code"><?php echo stripslashes($options['rss_suffix'])?></textarea>
					<p class="description">Set the default suffix for RSS. The following variables can be used;
					<ol><li>$site_title</li>
					<li>$site_tagline</li>
					<li>$site_url</li></ol>
					</p>
				</td></tr>
				</table>
				<input type="submit" value="Submit" class="button-primary" />
			</form>
		</fieldset>
	</div>
	<?php
}

add_action('network_admin_edit_update_azc_rss_network_options', 'process_azc_rss_network_options');

function process_azc_rss_network_options(){     
	if(!current_user_can('manage_network_options')) wp_die('FU');
	check_admin_referer('azc_rss');
	
	// Retrieve original plugin options array
	$options = get_site_option( 'azc_rss_options' );

	$option_name = 'rss_suffix';
	if ( isset( $_POST[$option_name] ) ) {
		$options[$option_name] = ($_POST[$option_name]);
	}
	
	update_site_option( 'azc_rss_options', $options );

	wp_redirect(network_admin_url('settings.php?page=azurecurve-rss-suffix'));
	exit;  
}

?>