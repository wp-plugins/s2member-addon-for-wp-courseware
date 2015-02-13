<?php
/*
 * Plugin Name: WP Courseware - s2Member Add On
 * Version: 1.1
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add support for the s2Member membership plugin for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */

// Main parent class
include_once 'class_members.inc.php';

// Clean up membership level IDs to match role name up activation
function wpcw_s2_db_cleanup(){
	global $wpdb;
	$count_roles = $GLOBALS['WS_PLUGIN__']['s2member']['c']['levels'];
		for ($i = 0; $i <= $count_roles; $i++){
			$do_it = $wpdb->get_var( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpcw_member_levels SET member_level_id = 's2member_level%d' WHERE member_level_id = 's2member_%d'",$i, $i) );
		}
	}

	register_activation_hook( __FILE__, 'wpcw_s2_db_cleanup' );

// Hook to load the class
add_action('init', 'WPCW_Members_S2_init',1);

/**
 * Initialize the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_Members_S2_init()
{

	$item = new WPCW_Members_S2Member();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Coursewar, attach.
	$item->attachToTools();

}

/**
 * Membership class that handles the specifics of the s2Member WordPress plugin and
 * handling the data for levels for that plugin.
 */
class WPCW_Members_S2Member extends WPCW_Members
{
	const GLUE_VERSION  = 1.00; 
	const EXTENSION_NAME = 's2Member';
	const EXTENSION_ID = 'WPCW_members_s2';
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialize using the parent constructor 
		parent::__construct(WPCW_Members_S2Member::EXTENSION_NAME, WPCW_Members_S2Member::EXTENSION_ID, WPCW_Members_S2Member::GLUE_VERSION);
	}
	
	/**
	 * Get list of membership levels
	 */
	protected function getMembershipLevels()
	{
		// Get a count of all levels that have been defined, and then get their respective labels.
		// There does not appear to be an API call for this, so it does feel a little wrong accessing
		// a variable directly.
		$levelCount = $GLOBALS["WS_PLUGIN__"]["s2member"]["c"]["levels"] + 0;
		if ($levelCount <= 0) {
			return false;
		}
		
		// Build array for the extension to use.
		$levelDataStructured = array();		
		for ($i = 0; $i <= $levelCount; $i++)
		{
			$levelItem = array();
			$levelItem['name'] 	= $GLOBALS["WS_PLUGIN__"]["s2member"]["o"]['level' . $i . '_label'];
			
			// Using s2member as part of string because the levels are just numbers. Just minimizes
			// any clashes by making the level ID a little more meaningful.
			$levelItem['id'] 	= 's2member_level' . $i;
							
			$levelDataStructured[$levelItem['id']] = $levelItem;
		}
						
		return $levelDataStructured;
	}

	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	protected function attach_updateUserCourseAccess()
	{
		// Update course access whenever the user role is change. Best that's possible with s2member 
		add_action('set_user_role', array($this, 'handle_updateUserCourseAccess'), 10);
	}

	
	/**
	 * Assign selected courses to members of a paticular level.
	 * @param Level ID in which members will get courses enrollment adjusted.
	 */
	protected function retroactive_assignment($level_ID)
    {
    	global $wpdb;

    	$page = new PageBuilder(false);

    	// $levelID = substr($level_ID, strpos($level_ID, "_") + 1);
		// $level_ID = 'S2member_level' . $levelID;

		$results = get_users( array( 'role' => $level_ID ) );

		if ($results){

			// Get user's assigned products and enroll them into courses accordingly
			foreach ($results as $result){
				$id = $result->ID;
				$s2member_access_level = 's2member_level' .get_user_field ("s2member_access_level", $id);	
				parent::handle_courseSync($id, array($s2member_access_level));
			}

		$page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            
        return;

		}else {
            $page->showMessage(__('No existing customers found for the specified product.', 'wp_courseware'));
        }
    }
	
	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 *
	 * @param Integer $id The ID if the user being changed.
	 */
	public function handle_updateUserCourseAccess($id)
	{		
		$s2member_access_level = 's2member_level' .get_user_field ("s2member_access_level", $id);		
						
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($id, array($s2member_access_level));
	}
	
	/**
	 * Detect presence of the membership plugin.
	 */
	public function found_membershipTool()
	{
		return function_exists('ws_plugin__s2member_configure_options_and_their_defaults');
	}
	
}

?>