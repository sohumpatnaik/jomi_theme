<?php

global $wpdb;
global $access_db_version;
global $access_table_name;

/**
 * ACCESS LOGIC
 */

/**
 * get useful article meta to help comb through article access rules
 * @param  [int] $id article id
 * @return [array] (category, id, status, author)
 */
function extract_selector_meta($id) {

	// TODO: match up against defaults?
	
	$publication_id = get_field('publication_id');

	$categories = get_the_category($id);
	$cat_ids = array();
	$cat_slugs = array();
	$cat_names = array();
	foreach($categories as $category) {
		//$category = ($category == '') ? '' : $category;
		array_push($cat_ids, $category->cat_ID);
		array_push($cat_slugs, $category->slug);
		array_push($cat_names, $category->name);
	}
	$status = (get_post_status($id) == false) ? '' : get_post_status($id);
	$coauthors = get_coauthors($id);
	$coauth_out = array();
	foreach($coauthors as $coauthor) {
		array_push($coauth_out, $coauthor->ID);
	}

	$selector_meta = array(
		'id' => $id,
		'pub_id' => $publication_id,
		'cat_ids' => $cat_ids,
		'cat_slugs' => $cat_slugs,
		'cat_names' => $cat_names,
		'status' => $status,
		'author' => $coauth_out,
	);
	return $selector_meta;
}
/**
 * use the user IP to get institution meta
 * can probably cache the result of this in the future
 * @return [int] institution ID (corresponds with row ID in the DB)
 */
function extract_institution_meta() {
	$ip = $_SERVER['REMOTE_ADDR'];

	// TODO: query institution table and get the institution rules
	
	$out = array(
		// institution ID
		'id' => 0
	);
	return $out;
}
/**
 * collect, sort, and concatenate the rules applying to this article
 * @param  [array] $selector_meta    selector meta object grabbed from extract_selector_meta
 * @param  [array] $institution_meta institution meta object grabbed from extract_institution_meta
 * @return [type]                   [description]
 */
function collect_rules($selector_meta, $institution_meta) {

  global $wpdb;
  global $access_table_name;
  
  //print_r($selector_meta);

  // init conditional
  $where_conditional = "(selector_type, selector_value) IN (";
  // categories
  $cat_ids = $selector_meta['cat_ids'];
  //$cat_slugs = $selector_meta['cat_slugs'];
  //$cat_names = $selector_meta['cat_names'];
  foreach($cat_ids as $index => $cat_id) {
  	$where_conditional .= "('category', $cat_id),";
  	//$cat_slug = $cat_slugs[$index];
  	//$where_conditional .= "('category', $cat_slug),";
  	//$cat_name = $cat_names[$index];
  	//$where_conditional .= "('category', $cat_name),";
  }
  // article id
  $id = $selector_meta['id'];
  $where_conditional .= "('article_id', $id),";
  // publication id
  $pub_id = $selector_meta['pub_id'];
  $where_conditional .= "('pub_id', $pub_id),";
  // status
  $status = $selector_meta['status'];
  $where_conditional .= "('post_status', '$status'),";
  // authors
  $authors = $selector_meta['author'];
  foreach($authors as $author) {
  	$where_conditional .= "('author', $author),";
  }
  // TODO: add institution meta cond. here:

  // cap it off
  $where_conditional .= "('-1','-1'))";

  $rules_query = "SELECT * 
                  FROM $access_table_name 
                  WHERE $where_conditional 
                  ORDER BY priority DESC";

  //echo $rules_query;
  $rules = $wpdb->get_results($rules_query);

  //check_db_errors();

  print_r($rules);
  return $rules;
}

function load_check_info() {
	global $reader;
	
	$current_user = wp_get_current_user();
    
    if ( ($current_user instanceof WP_User) ) {
    	$logged_in = true;
    	$user = array(
    		'login' => $current_user->user_login,
    		'email' => $current_user->user_email,
    		'display_name' => $current_user->display_name,
    		'id' => $current_user->ID
    	);
    	//return;
    } else {
    	$logged_in = false;
    	$user = array(
    		'login' => 'none',
    		'email' => 'none',
    		'display_name' => 'none',
    		'id' => 'none'
    	);
    }
     
    // DEBUG
    /*echo 'Username: ' . $current_user->user_login . '<br />';
    echo 'User email: ' . $current_user->user_email . '<br />';
    echo 'User first name: ' . $current_user->user_firstname . '<br />';
    echo 'User last name: ' . $current_user->user_lastname . '<br />';
    echo 'User display name: ' . $current_user->display_name . '<br />';
    echo 'User ID: ' . $current_user->ID . '<br />'; */
    //print_r($user);
	 
	$ip = $_SERVER['REMOTE_ADDR'];
	$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

	// TEST ONLY
	$ip = "173.13.115.174";

	//DEBUG
	//echo $ip;
	
	// check institutions here
	$institution = array(
	);

	try {
	    $record = $reader->city($ip);
	    $country = array (
	    	'iso' => $record->country->isoCode,
	    	'name' => $record->country->name
	    );
		$region = $record->mostSpecificSubdivision->isoCode;
		$city = $record->city->name;
	} catch (Exception $e) {
		// if can't find, default to Boston, MA, US
		$country = array(
			'iso' => 'US',
			'name' => 'United States'
		);
		$region = 'MA';
		$city = 'Boston';
	    //return new WP_Error( 'ip_not_found', "I've fallen and can't get up" );
	}

	// DEBUG
	/*print("\n" . $record->country->isoCode . "\n"); // 'US'
	print($record->country->name . "\n"); // 'United States'
	print($record->mostSpecificSubdivision->name . "\n"); // 'Minnesota'
	print($record->mostSpecificSubdivision->isoCode . "\n"); // 'MN'
	print($record->city->name . "\n"); // 'Minneapolis'*/

	$out = array(
		'logged_in' => $logged_in,
		'user' => $user,
		'institution' => $institution,
		'ip' => $ip,
		'country' => $country,
		'region' => $region,
		'city' => $city
	);
	return $out;
}


/**
 * use rules to check access to article
 * @param  [type] $rules [description]
 * @param  [type] $check_data array of user/session data to check against
 * @return [array] $blocks a list of block objects to apply
 */
function check_access($rules, $check_data) {

	if(empty($rules)) {
		//echo "empty rules";
		return;
	}
	if(empty($check_data)) {
		//echo "no check data";
		return;
	}

	$blocks = array();

	foreach($rules as $rule) {
		// check for invalid/empty result first and return if so
		switch($rule->result_type) {
			case '':
			case 'None':
			case 'NONE':
			case 'Default':
			case 'DEFAULT':
				//return;
				continue;
				break;
		}

		// TODO: check for invalid time results
		
		// SPLIT UP CHECK TYPES
		
		switch($rule->check_type) {
			case 'is_ip':

				$ip_check = $check_data['ip'];

				$ips = explode(',', $rule->check_value);
				foreach($ips as $ip) {
					if($ip_check == $ip) {
						
						array_push($blocks, array(
							'msg' => $rule->result_msg,
							'time_start' => $rule->result_time_start,
							'time_end' => $rule->result_time_end,
							'time_elapsed' => $rule->result_time_elapsed
						));

						//echo "ip matched";
						//return;
					}
				}

				break;
			case 'is_institution':

				$institution_check = $check_data['institution'];

				$institutions = explode(',', $rule->check_value);
				foreach($institutions as $institution) {
					// TODO institution check
				}

				break;
			case 'is_country':

				$country_check = $check_data['country'];

				// split up the CSV
				$countries = explode(",", $rule->check_value);
				foreach($countries as $country) {
					if($country_check['iso'] == $country or $country_check['name'] == $country) {

						array_push($blocks, array(
							'msg' => $rule->result_msg,
							'time_start' => $rule->result_time_start,
							'time_end' => $rule->result_time_end,
							'time_elapsed' => $rule->result_time_elapsed
						));

						//echo "country matched";
						//return;
					}
				}
				break;
			case 'is_user':

				$user_check = $check_data['user'];

				$users = explode(",", $rule->check_value);
				foreach($users as $user) {
					if($user_check['login'] == $user or
					   $user_check['email'] == $user or
					   $user_check['display_name'] == $user or
					   $user_check['id'] == $user) {

						array_push($blocks, array(
							'msg' => $rule->result_msg,
							'time_start' => $rule->result_time_start,
							'time_end' => $rule->result_time_end,
							'time_elapsed' => $rule->result_time_elapsed
						));
						//TODO: place block
						//echo "user matched";
						//return;
					}
				}
				break;
			default:
				echo "invalid check type";
				break;
		}
	}
	//remove dupes
	$blocks = array_unique($blocks);

	print_r($blocks);

	return $blocks;
}

?>