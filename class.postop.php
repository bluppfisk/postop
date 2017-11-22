<?php

class Postop
{
	function __construct()
	{
		global $wpdb;
		$this->db = $wpdb;
		$this->table_name = $this->db->prefix . "postop_reviews";
	}

	public function init()
	{
		add_shortcode('postop_review', array(&$this, 'solicit_review'));
		add_shortcode('postop_show_reviews', array(&$this, 'show_reviews'));
	}

	public function show_reviews($params)
	{
		$this->include_goatee();
		$num = intval($params['num']);
		if ($requests = $this->get_live_requests($num)) {
			$reviews = [];
			foreach ($requests as $request) {
				$name = $request['given_name']." ".$request['family_name'][0].".";
				$stars = "*";
				for ($i=2;$i<=$request['rating'];$i++) {
					$stars .= "*";
				}
				$body = substr($request['body'], 0, 200).(strlen($request['body']) > 200 ? "..." : "");
				$review = [
					'id' => $request['id'],
					'date' => date('d M Y', strtotime($request['date'])),
					'author' => $name,
					'rating' => $request['rating'],
					'stars' => $stars,
					'body' => $body
				];
				$reviews[] = $review;
			}

			$aggregates = $this->db->get_row("SELECT AVG(rating) AS average, COUNT(id) AS count FROM ".$this->table_name." WHERE may_be_published = 1 AND access_token = '' AND approved = 1");

			$average = $aggregates->average;
			$review_count = $aggregates->count;

			$stars = "*";
			for ($i=2;$i<=$average;$i++) {
				$stars .= "*";
			}

			$business = [
				'reviews' => $reviews,
				'name' => 'Eyecenter',
				'url' => 'https://eyecenter.be',
				'phone' => '09/12382143',
				'street' => 'Street',
				'zip' => '9000',
				'city' => 'Gent',
				'region' => 'Oost-Vlaanderen',
				'aggregate_rating' => $average,
				'review_count' => $review_count,
				'stars' => $stars
			];

			print(postop_Goatee::fill($this->load_template('review_item.html'), $business));

		} else {
			echo("Nog geen reviews");
		}
	}

	private function load_template($name)
	{
		return file_get_contents($this->get_plugin_dir()."include/templates/".$name);
	}

	private function include_goatee()
	{
		include_once($this->get_plugin_dir()."/include/goatee-php/postop-goatee.php");
	}

	private function get_plugin_dir()
	{
		return WP_PLUGIN_DIR . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
	}

	private function get_live_requests($limit, $start=0)
	{
		$requests = $this->db->get_results("SELECT id, date, given_name, family_name, rating, body FROM ".$this->table_name." WHERE may_be_published = 1 AND approved = 1 AND access_token = '' ORDER BY id DESC LIMIT ".$start.",".$limit, ARRAY_A);
		if (!$requests) {
			return false;
		}
		return $requests;
	}

	public function solicit_review()
	{
		if ( array_key_exists( 'po_access_token', $_GET ) && $_GET['po_access_token'] != '' ) {
			// access token given, let's get the data
			$access_token = $_GET['po_access_token'];
			$review_data = $this->get_review_data( $access_token );

			if ( !$review_data ) {
				$this->invalid_token();
			} else {
				if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
					// form was submitted, let's process it
					$review_data['rating'] = $_POST['po_rating'];
					$review_data['body'] = $_POST['po_body'];
					$review_data['date'] = date( 'Y-m-d H:i:s' );
					if ( !empty( $_POST['po_permission'] ) ) {
						$review_data['may_be_published'] = 1;
					} else {
						$review_data['may_be_published'] = 0;
					}
					$review_data['access_token'] = ''; // empty to invalidate token
					$review_data['approved'] = 0; // pending admin approval
					
					// persist data
					$result = $this->db->update( $this->table_name, $review_data, array( 'access_token' => $access_token ) );

					$this->thank_you();
				} else {
					$this->form( $review_data );
				}
			}
		} else {
			// no access token provided
			$this->forbidden();
		}
	}

	private function forbidden()
	{
		$this->include_goatee();
		print(postop_Goatee::fill($this->load_template('messages.html'), ['403' => true]));
	}

	private function invalid_token()
	{
		$this->include_goatee();
		print(postop_Goatee::fill($this->load_template('messages.html'), ['404' => true]));
	}

	private function get_review_data( $access_token )
	{
		$review_data = $this->db->get_row( "SELECT * FROM ".$this->table_name." WHERE access_token = '".$access_token."'", ARRAY_A );
		if ( !$review_data ) {
			return false;
		}
		return $review_data;
	}

	private function form( $review_data )
	{
		$this->include_goatee();
		print(postop_Goatee::fill($this->load_template('form_feedback.html'), $review_data));
	}

	private function thank_you()
	{
		$this->include_goatee();
		print(postop_Goatee::fill($this->load_template('messages.html'), ['thank_you' => true]));
	}
}