<?php
/*
Plugin Name: Tintin Quotes
Plugin URI: http://www.aphexddb.com/wordpress
Description: Billions of Bilious Blue Blistering Barnacles! Display some classic <a href="http://en.wikipedia.org/wiki/The_Adventures_of_Tintin">Tintin</a> quotes (well mostly Captain Haddock's insults) on your blog. <a href="options-general.php?page=tintin-quotes.php">Configuration Page</a>.
Version: 1.02
Author: Gardiner Allen
Author URI: http://www.aphexddb.com
*/

//
// Released under the Creative Commons Attribution-Share Alike 3.0 United States License
// http://creativecommons.org/licenses/by-sa/3.0/us/
//
// This is an add-on for WordPress
// http://www.wordpress.org/
//
// Thanks to the Captain Haddock's Curses list (http://www.tintinologist.org/guides/lists/curses.html) for inspiration!
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

//ini_set('display_errors', '1');
//ini_set('error_reporting', E_ALL);

function tintinquotes_exists() { return true; }

load_plugin_textdomain('tintin-quotes');

if (!function_exists('is_admin_page')) {
	function is_admin_page() {
		if (function_exists('is_admin')) {
			return is_admin();
		}
		if (function_exists('check_admin_referer')) {
			return true;
		}
		else {
			return false;
		}
	}
}

class tintin_quotes {
	function tintin_quotes() {
		$this->options = array(
			'tintin_character',
			'version'
		);
		$this->tintin_character = '1'; // default to Captain Haddock ;) 
		$this->version = '1.02'; // default to current version
	}
	
	function install() {
		global $wpdb;
				
		// load installer file with all the SQL dumps
		$f = dirname(__FILE__) . '/' . 'tintin-quotes-sql.php';		
		include_once($f);
				
		foreach ($this->options as $option) {
			add_option('tintinquotes_'.$option, $this->$option);
		}		
	}	

	function get_settings() {
		foreach ($this->options as $option) {
			$this->$option = get_option('tintinquotes_'.$option);
		}
	}

	function update_settings() {
		if (current_user_can('manage_options')) {
			foreach ($this->options as $option) {
				update_option('tintinquotes_'.$option, $this->$option);
			}
		}
	}

	function populate_settings() {
		foreach ($this->options as $option) {
			if (isset($_POST['tintinquotes_'.$option])) {
				$this->$option = stripslashes($_POST['tintinquotes_'.$option]);
			}
		}
	}

	function get_insult($character_id=0) {
		global $wpdb, $ddb;
		
		if ($character_id) $id = $character_id;
		else if ($this->tintin_character) $id = $this->tintin_character;
		else $id = 0;
		
		if ($id) $where = " WHERE i.character_id = '$id' ";
		else $where = null;
		
		$tintinquotes_quote_query = "
		SELECT i.insult, c.character_name
		FROM $wpdb->ddbinsults i 
		LEFT JOIN $wpdb->ddbchars c ON c.id = i.character_id
		$where
		ORDER BY RAND() LIMIT 1 
		";
		
		$quote = wp_cache_get( 'tintinquotes_quote' );
		if ( false == $quote ) {
			$quote = $wpdb->get_results( $tintinquotes_quote_query );
			wp_cache_set( 'tintinquotes_characters', $quote );
		} 			
		
		if (sizeof($quote)) {
			return sprintf("<span class=\"tintin_insult\">%s!</span> <span class=\"tintin_character\">- %s</span>", $quote[0]->insult, $quote[0]->character_name);
		} else {
			return 	__('Billions of Blue Blistering Barnacles! No insults found in the database!', 'tintin-quotes');
		}
	}
	
	function upgrade_check($version="1.0") {
		global $wpdb;
				
		// table structure changes
		if ($version < 1.01) {
			$wpdb->query("ALTER TABLE `$wpdb->ddbinsults` CHANGE `insult` `insult` TEXT NOT NULL");
		}	
	}		
}

function tintinquotes_sidebar($character_id=0) {
	global $wpdb, $ddb;
	
	printf("<div class=\"tintinquotes_insult\">\n%s\n</div>\n", $ddb->get_insult($character_id));
}


function tintinquotes_widget_init() {
	if (!function_exists('register_sidebar_widget')) {
		return;
	}
	function tintinquotes_widget($args) {
		extract($args);
		$options = get_option('tintinquotes_widget');
		$title = $options['title'];
		if (empty($title)) {
		}
		echo $before_widget . $before_title . $title . $after_title;
		tintinquotes_sidebar();
		echo $after_widget;
	}
	register_sidebar_widget(array(__('Tintin Quotes', 'tintin-quotes'), 'widgets'), 'tintinquotes_widget');
	
	function tintinquotes_widget_control() {
		$options = get_option('tintinquotes_widget');
		if (!is_array($options)) {
			$options = array(
				'title' => __("Tintin Quote", 'tintin-quotes')
			);
		}
		if (isset($_POST['ak_action']) && $_POST['ak_action'] == 'tintinquotes_update_widget_options') {
			$options['title'] = strip_tags(stripslashes($_POST['tintinquotes_widget_title']));
			update_option('tintinquotes_widget', $options);
		}

		// Be sure you format your options to be valid HTML attributes.
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		
		print('
			<p>'.__('Additional Tintin character quote options are available on the <a href="options-general.php?page=tintin-quotes.php">configuration page</a>.', 'tintin-quotes').'
			<input type="hidden" id="ak_action" name="ak_action" value="tintinquotes_update_widget_options" />
		');
	}
	register_widget_control(array(__('Tintin Quotes', 'tintin-quotes'), 'widgets'), 'tintinquotes_widget_control', 300, 100);

}
add_action('widgets_init', 'tintinquotes_widget_init');

function tintinquotes_init() {
	global $wpdb, $ddb;
	$ddb = new tintin_quotes;	
	$wpdb->ddbinsults = $wpdb->prefix.'tintin_insults';
	$wpdb->ddbchars = $wpdb->prefix.'tintin_characters';
	if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
		$tables = $wpdb->get_col("
			SHOW TABLES
		");
		if (!in_array($wpdb->ddbinsults, $tables) &&
			!in_array($wpdb->ddbchars, $tables)) {
			$ddb->install();
		}
	}
	
	// Upgrade Table structure if needed
	$ddb->get_settings();
	$ddb->upgrade_check($ddb->version);
	
	$ddb->get_settings();	
}
add_action('init', 'tintinquotes_init');

function tintinquotes_head() {
	print('
		<link rel="stylesheet" type="text/css" href="'.get_bloginfo('wpurl').'/index.php?ak_action=tintinquotes_css" />
	');
}
add_action('wp_head', 'tintinquotes_head');

function tintinquotes_head_admin() {
	print('<link rel="stylesheet" type="text/css" href="'.get_bloginfo('wpurl').'/index.php?ak_action=tintinquotes_css_admin" />');
}
add_action('admin_head', 'tintinquotes_head_admin');

function tintinquotes_request_handler() {
	global $wpdb, $ddb;
	if (!empty($_GET['ak_action'])) {
		switch($_GET['ak_action']) {			
			case 'tintinquotes_css_admin':
			case 'tintinquotes_css':
				header("Content-type: text/css");
?>
.tintinquotes_insult {
	margin: 0;
	padding: 0;
}
.tintinquotes_insult .tintin_insult {
	display: block;
	margin: 0;
	padding: 0;
}
.tintinquotes_insult .tintin_character {
	display: block;
	margin: 0;	
	padding: 0;
	padding-left: 2em;
}
<?php
				die();
				break;			
		}
	}
	
	if (!empty($_POST['ak_action'])) {
		switch($_POST['ak_action']) {
			case 'tintinquotes_update_settings':
				$ddb->populate_settings();
				$ddb->update_settings();
				header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=tintin-quotes.php&updated=true');
				die();
				break;
		}
	}	
}
add_action('init', 'tintinquotes_request_handler', 10);

// Get list of characters
function tintinquotes_characters() {
	global $wpdb, $ddb;
		
	$tintinquotes_character_query = "
	SELECT
	c.*, count(i.id) as insult_count
	FROM $wpdb->ddbchars c
	LEFT JOIN $wpdb->ddbinsults i on i.character_id = c.id
	GROUP BY c.character_name
	ORDER BY insult_count DESC
	";
		
	$characters = wp_cache_get( 'tintinquotes_characters' );
	if ( false == $characters ) {
		$characters = $wpdb->get_results( $tintinquotes_character_query );
		wp_cache_set( 'tintinquotes_characters', $characters );
	} 	
	
	return $characters;
}

// Simple character dropdown for theme option pages
function tintinquotes_simple_theme_options_form($fieldname='tintin_character', $character_id=null) {
	global $wpdb, $ddb;
		
	$characters = tintinquotes_characters();
		
	if (!$ddb->tintin_character) {
		$defaultSelected = 'selected="selected"';
	}
	else {
		$defaultSelected = '';
	}
	$tintin_char_options = '<option value="0" '.$defaultSelected.'>'.__('All Tintin Characters', 'tintin-quotes').'</option>';
		
	if ($character_id) $charID = $character_id;
	else $charID = $ddb->tintin_character;
		
	foreach ($characters as $character) {				
		if ($character->insult_count > 0) {
			if ($character->id == $charID) {
				$selected = 'selected="selected"';
			}
			else {
				$selected = '';
			}
			$tintin_char_options .= "\n\t<option value='$character->id' $selected>$character->character_name ($character->insult_count quotes)</option>";
		}
	}	

	print('<select name="'.$fieldname.'" id="'.$fieldname.'">'.$tintin_char_options.'</select>');
}

function tintinquotes_options_form($character_id=0) {
	global $wpdb, $ddb;
		
	$characters = tintinquotes_characters();
	
	if (!$ddb->tintin_character) {
		$defaultSelected = 'selected="selected"';
	}
	else {
		$defaultSelected = '';
	}
	$tintin_char_options = '<option value="0" '.$defaultSelected.'>'.__('All Tintin Characters', 'tintin-quotes').'</option>';
		
	foreach ($characters as $character) {				
		if ($character->insult_count > 0) {
			if ($character->id == $ddb->tintin_character) {
				$selected = 'selected="selected"';
			}
			else {
				$selected = '';
			}
			$tintin_char_options .= "\n\t<option value='$character->id' $selected>$character->character_name ($character->insult_count quotes)</option>";
		}
	}	

	if ( $_GET['tintinquotes-updated'] ) {
		print('
			<div id="message" class="updated fade">
				<p>'.__('Tintin Quotes updated.', 'tintin-quotes').'</p>
			</div>
		');
	}
	print('
			<div class="wrap">
				<h2>'.__('Tintin Quotes Options', 'tintin-quotes').'</h2>
				<form id="tintin_quote_options" name="tintin_quote_options" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
					<fieldset class="options">
						<p>
							<label for="tintinquotes_tintin_character">'.__('Tintin character to quote', 'tintin-quotes').'</label>
							<select name="tintinquotes_tintin_character" id="tintinquotes_tintin_character">'.$tintin_char_options.'</select>
						</p>					
						<input type="hidden" name="ak_action" value="tintinquotes_update_settings" />
					</fieldset>						
					<p class="submit">
						<input type="submit" name="submit" value="'.__('Update Tintin Quotes Options', 'tintin-quotes').'" />
					</p>
				</form>				
				<h2>'.__('Sample Quote', 'tintin-quotes').'</h2>
					<div class="tintinquotes_insult">
					'.$ddb->get_insult($character_id=0).'
					</div>
			</div>
	');
}

function tintinquotes_menu_items() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('Tintin Quotes Options', 'tintin-quotes')
			, __('Tintin Quotes', 'tintin-quotes')
			, 10
			, basename(__FILE__)
			, 'tintinquotes_options_form'
		);
	}
}
add_action('admin_menu', 'tintinquotes_menu_items');


?>