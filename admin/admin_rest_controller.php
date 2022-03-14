<?php

if (!class_exists('BMLTIntegration')) {
	require_once(BMAW_PLUGIN_DIR . 'admin/bmlt_integration.php');
}

function bmaw_submissions_controller()
{
	$controller = new bmaw_submissions_rest();
	$controller->register_routes();
}


class bmaw_submissions_rest extends WP_REST_Controller
{

	protected $namespace;
	protected $rest_base;

	public function __construct()
	{

		$this->namespace = 'bmaw-submission/v1';
		$this->submissions_rest_base = 'submissions';
		$this->service_areas_rest_base = 'serviceareas';
		$this->bmlt_integration = new BMLTIntegration;
	}

	public function register_routes()
	{

		// submissions/
		register_rest_route($this->namespace, '/' . $this->submissions_rest_base, array(

			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_submissions'),
				'permission_callback' => array($this, 'get_submissions_permissions_check'),
			),
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array($this, 'post_submissions'),
				'permission_callback' => array($this, 'post_submissions_permissions_check'),
				'args'            => $this->get_endpoint_args_for_item_schema(false),
			),
			'schema' => null,

		));
		// GET submissions/<id>
		register_rest_route($this->namespace, '/' . $this->submissions_rest_base . '/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_submission'),
			'permission_callback' => array($this, 'get_submissions_permissions_check'),
		));
		// DELETE submissions/<id>
		register_rest_route($this->namespace, '/' . $this->submissions_rest_base . '/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array($this, 'delete_submission'),
			'permission_callback' => array($this, 'delete_submission_permissions_check'),
		));
		// POST submissions/<id>/approve
		register_rest_route($this->namespace, '/' . $this->submissions_rest_base . '/(?P<id>[\d]+)/approve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'approve_submission'),
			'permission_callback' => array($this, 'approve_submission_action_permissions_check'),
		));
		// POST submissions/<id>/reject
		register_rest_route($this->namespace, '/' . $this->submissions_rest_base . '/(?P<id>[\d]+)/reject', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'reject_submission'),
			'permission_callback' => array($this, 'reject_submission_action_permissions_check'),
		));

		register_rest_route(
			$this->namespace,
			'/' . $this->service_areas_rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_service_areas'),
				'permission_callback' => array($this, 'get_service_areas_permissions_check'),
			),
		);
	}
	/**
	 * Check permissions for submission management. These are general purpose checks for all submission editors, granular edit permission will be checked within the callback itself.
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_submissions_permissions_check($request)
	{
		error_log("get submissions current user " . get_current_user_id());
		if (!current_user_can('bmaw_manage_submissions')) {
			return new WP_Error('rest_forbidden', esc_html__('Access denied: You cannot view submissions.'), array('status' => $this->authorization_status_code()));
		}
		return true;
	}

	public function approve_submission_action_permissions_check($request)
	{
		error_log("approve submission current user " . get_current_user_id());
		if (!current_user_can('bmaw_manage_submissions')) {
			return new WP_Error('rest_forbidden', esc_html__('Access denied: You cannot accept this submission.'), array('status' => $this->authorization_status_code()));
		}
		return true;
	}

	public function reject_submission_action_permissions_check($request)
	{
		error_log("reject submission current user " . get_current_user_id());
		if (!current_user_can('bmaw_manage_submissions')) {
			return new WP_Error('rest_forbidden', esc_html__('Access denied: You cannot reject this submission.'), array('status' => $this->authorization_status_code()));
		}
		return true;
	}

	public function delete_submission_permissions_check($request)
	{
		error_log("delete submission current user " . get_current_user_id());
		if (!current_user_can('bmaw_manage_submissions')) {
			return new WP_Error('rest_forbidden', esc_html__('Access denied: You cannot delete this submission.'), array('status' => $this->authorization_status_code()));
		}
		return true;
	}

	/**
	 * Check permissions for user management.
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_service_areas_permissions_check($request)
	{
		error_log("get_service_areas_permissions_check " . get_current_user_id());
		if (!current_user_can('manage_options')) {
			return new WP_Error('rest_forbidden', esc_html__('Access denied: You cannot view service_areas.'), array('status' => $this->authorization_status_code()));
		}
		return true;
	}

	/**
	 * Check permissions for form post
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return bool|WP_Error
	 */
	public function post_submissions_permissions_check($request)
	{
		// Anyone can post a form submission
		return true;
	}
	/**
	 * Grabs all the submissions in the list.
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return mixed|WP_REST_Response
	 */

	public function get_submissions($request)
	{

		global $wpdb;
		global $bmaw_submissions_table_name;

		$result = $wpdb->get_results('SELECT * FROM ' . $bmaw_submissions_table_name, ARRAY_A);

		// Return all of our comment response data.
		return rest_ensure_response($result);
	}

	/**
	 * Returns a single submission
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return mixed|WP_REST_Response
	 */

	public function get_submission($request)
	{

		global $wpdb;
		global $bmaw_submissions_table_name;
		$sql = $wpdb->prepare('SELECT * FROM ' . $bmaw_submissions_table_name . ' where id="%d" limit 1', $request['id']);
		$result = $wpdb->get_results($sql, ARRAY_A);

		// Return all of our comment response data.
		return rest_ensure_response($result);
	}

	/**
	 * Deletes a single submission
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return mixed|WP_REST_Response
	 */

	public function delete_submission($request)
	{

		global $wpdb;
		global $bmaw_submissions_table_name;
		$sql = $wpdb->prepare('DELETE FROM ' . $bmaw_submissions_table_name . ' where id="%d" limit 1', $request['id']);
		$result = $wpdb->get_results($sql, ARRAY_A);

		// Return all of our comment response data.
		return rest_ensure_response($result);
	}

	private function vdump($object)
	{
		ob_start();
		var_dump($object);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
	/**
	 * Approve a single submission
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return mixed|WP_REST_Response
	 */

	public function approve_submission($request)
	{
		$change_id = $request->get_param('id');

		error_log("getting changes for id " . $change_id);

		global $wpdb;
		global $bmaw_submissions_table_name;

		$sql = $wpdb->prepare('SELECT change_made FROM ' . $bmaw_submissions_table_name . ' where id="%d" limit 1', $request['id']);
		$result = $wpdb->get_results($sql, ARRAY_A);
		if ($result[0]['change_made'] === 'Approved') {
			return "{'response':'already approved'}";
		}

		$sql = $wpdb->prepare('SELECT changes_requested FROM ' . $bmaw_submissions_table_name . ' where id="%d" limit 1', $request['id']);
		$result = $wpdb->get_results($sql, ARRAY_A);
		if ($result) {
			error_log(vdump($result));
		} else {
			error_log("no result found");
		}
		$change = unserialize($result[0]['changes_requested']);
		error_log("deserialised");
		error_log(vdump($change));
		$change['admin_action'] = 'modify_meeting';

		$response = $this->bmlt_integration->postConfiguredRootServerRequestSemantic('local_server/server_admin/json.php', $change);
		// ERROR HANDLING NEEDED
		// if( is_wp_error( $response ) ) {
		// 	wp_die("BMLT Configuration Error - Unable to retrieve meeting formats");
		// }
		$current_user = wp_get_current_user();
		$username = $current_user->user_login;

		$sql = $wpdb->prepare('UPDATE ' . $bmaw_submissions_table_name . ' set change_made = "%s", changed_by = "%s", change_time = "%s" where id="%d" limit 1', 'Approved', $username, current_time('mysql', true), $request['id']);
		$result = $wpdb->get_results($sql, ARRAY_A);

		return "{'response':'approved'}";
	}

	/**
	 * Form post
	 *
	 * @param WP_REST_Request $request get data from request.
	 *
	 * @return mixed|WP_Error|WP_REST_Response
	 */

	public function post_submissions($request)
	{
		error_log($this->vdump($request->get_body_params()));

		$resp = meeting_update_form_handler_rest($request->get_body_params());

		return rest_ensure_response($resp);
	}

	public function get_service_areas($request)
	{

		// call bmlt for service area list
		// add list of wp uids with access
		// return as array of all service areas

		global $wpdb;
		global $bmaw_service_areas_table_name;

		$req = array();
		$req['admin_action'] = 'get_service_body_info';
		$req['flat'] = '';
		$bmlt_integration = new BMLTIntegration;

		// get an xml for a workaround
		$response = $bmlt_integration->postConfiguredRootServerRequestSemantic('local_server/server_admin/xml.php', $req);
		if (is_wp_error($response)) {
			wp_die("BMLT Configuration Error - Unable to retrieve meeting formats");
		}

		$xml = simplexml_load_string($response['body']);
		$arr = json_decode(json_encode($xml), 1);

		$sblist = array();
		$idlist = array();

		foreach ($arr['service_body'] as $key => $value) {
			$idlist[] = $value['@attributes']['id'];
			if (array_key_exists('@attributes', $value)) {
				$sblist[] = array('name' => $value['@attributes']['name'], 'id' => $value['@attributes']['id']);
			}
		}

		// update our service area list in case there have been some new ones added
		error_log("get ids");
		$sqlresult = $wpdb->get_col('SELECT service_area_id FROM ' . $bmaw_service_areas_table_name . ';',0);
		// error_log($sql);
		// $sqlresult = $wpdb->get_col($sql, 0);
		error_log(vdump($sqlresult));
		$missing = array_diff($idlist, $sqlresult);
		error_log("missing ids");
		error_log(vdump($missing));

		foreach ($missing as $value)
		{
			$sql = $wpdb->prepare('INSERT into '. $bmaw_service_areas_table_name . ' values (service_area_id="%d", show_on_form=NULL',$value);
			$wpdb->query($sql);
		}

		// $sql = $wpdb->prepare('SELECT user_array FROM ' . $bmaw_service_areas_table_name . ' where service_area_id="%d" limit 1', $request['id']);
		// $sqlresult = $wpdb->get_results($sql, ARRAY_A);

		// error_log("sqlresult = " . vdump($sqlresult));


		// global $wpdb;
		// global $bmaw_service_areas_table_name;

		// // user_array is a comma seperated list of all the users entitled to edit this service area with bmaw
		// $sql = $wpdb->prepare('SELECT user_array FROM ' . $bmaw_service_areas_table_name . ' where service_area_id="%d" limit 1', $request['id']);
		// $sqlresult = $wpdb->get_results($sql, ARRAY_A);

		// // error_log("sqlresult = ".vdump($sqlresult));
		// $arr = array();
		// // Did we even get a result?
		// if ($sqlresult) {
		// 	$str = unserialize($sqlresult[0]['user_array']);
		// 	// error_log("arr = ".vdump($str));
		// 	// split the list up and then search it when creating the select
		// 	$arr = explode(",", $str);
		// }

		// $request = new WP_REST_Request('GET', '/wp/v2/users');
		// $result = rest_do_request($request);

		// $data = $result->get_data();
		// $select = array('results' => array());
		// foreach ($data as $user) {
		// 	$data = array('id' => $user['id'], 'text' => $user['name']);
		// 	// if we have a match from the administration list, mark it as selected
		// 	if (in_array($user['id'], $arr)) {
		// 		$data['selected'] = true;
		// 	}
		// 	$select['results'][] = $data;
		// }
		// // var_dump( $select );
		// // Return all of our comment response data.
		// return rest_ensure_response($select);
	}

	/**
	 * Sets up the proper HTTP status code for authorization.
	 *
	 * @return int
	 */
	public function authorization_status_code()
	{

		$status = 401;

		if (is_user_logged_in()) {
			$status = 403;
		}

		return $status;
	}
}
