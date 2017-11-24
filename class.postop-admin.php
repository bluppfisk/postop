<?php

class Postop_Admin
{
	private $options;

	function __construct()
	{
		global $wpdb;
		$this->db = $wpdb;
		$this->table_name = $this->db->prefix . "postop_reviews";
	}

	public function init()
	{
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_menu', function()
		{
			remove_submenu_page('Postop', 'Postop');
		});
		add_action('admin_init', array( &$this, 'register_settings'));

		if (isset($_GET['toggle'])) {
			$this->toggle_live($_GET['toggle']);
			wp_redirect(admin_url('admin.php?page=postop_manage'));
		}

		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			if (isset($_POST['edit_review_id'])) {
				$this->update_review();
			}
		}
	}

	public function admin_menu()
	{
		add_menu_page( 
			'Post-op Feedback',
			'Post-op Feedback',
			'manage_options',
			'Postop',
			array( &$this, 'manage_reviews_page' )
		);

		add_submenu_page(
			'Postop',
			get_admin_page_title(),
			'Alle Feedback',
			'manage_options',
			'postop_manage',
			array( &$this, 'manage_reviews_page' )
	    );

		add_submenu_page(
			'Postop',
			get_admin_page_title(),
			'Feedback Vragen',
			'manage_options',
			'postop_request',
			array( &$this, 'request_reviews_page' )
	    );

	    add_submenu_page(
	    	'Postop',
	    	get_admin_page_title(),
	    	'Instellingen',
	    	'manage_options',
	    	'postop_settings',
	    	array(&$this, 'postop_settings')
	    );
	}

	public function business_details()
	{
		printf(
            '<input type="text" id="name" placeholder="Naam" name="business_details[name]" value="%s" /><br />
            <input type="text" id="street" placeholder="Straat" name="business_details[street]" value="%s" /><br />
            <input type="text" id="zip" placeholder="Postcode" name="business_details[zip]" value="%s" /><br />
            <input type="text" id="city" placeholder="Stad" name="business_details[city]" value="%s" /><br />
            <input type="text" id="region" placeholder="Regio" name="business_details[region]" value="%s" /><br />
            <input type="text" id="country" placeholder="Landcode" name="business_details[country]" value="%s" /><br />
            <input type="text" id="phone" placeholder="Telefoonnummer" name="business_details[phone]" value="%s" /><br />',
        	isset(get_option('business_details')['name'])? get_option('business_details')['name'] : '',
        	isset(get_option('business_details')['street'])? get_option('business_details')['street'] : '',
        	isset(get_option('business_details')['zip'])? get_option('business_details')['zip'] : '',
        	isset(get_option('business_details')['city'])? get_option('business_details')['city'] : '',
        	isset(get_option('business_details')['region'])? get_option('business_details')['region'] : '',
        	isset(get_option('business_details')['country'])?get_option('business_details')['country'] : '',
        	isset(get_option('business_details')['phone'])? get_option('business_details')['phone'] : ''
        );
	}

	public function postop_settings()
	{
		add_settings_section(
			'business_settings_section',
			'Zaakgegevens',
			array(&$this, 'show_settings'),
			'postop_settings'
		);

		add_settings_field(
            'business_details',
            'Adres',
            array( $this, 'business_details' ),
            'postop_settings',
            'business_settings_section'
        );

		print_r($this->options);
        ?>
        <div class="wrap">
            <h1>Instellingen</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'po_business_details' );
                do_settings_sections( 'postop_settings' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
	}

	public function sanitize($input)
    {
    	foreach ($input as $key=>$value) {
    		$new_input[$key] = sanitize_text_field($value);
    	}

        return $new_input;
    }

	public function show_settings()
	{
		echo ("Vul hier de zaakgegevens in.");
	}

	public function register_settings()
	{
		register_setting( 'po_business_details', 'business_details', array(&$this, 'sanitize') );
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

	public function request_reviews_page()
	{
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$new_request = array(
				'given_name' => $_POST['po_request_given_name'],
				'family_name' => $_POST['po_request_family_name'],
				'email' => $_POST['po_request_email'],
				'access_token' => $this->generate_token(25)
			);
			if ($this->register_new_request($new_request) && $this->notify_customer($new_request)) {
				echo("<h2>Ok!</h2>Email verstuurd aan ". $new_request['email']);
			} else {
				echo("<h2>Oeps!</h2>Er is iets misgelopen bij het emailen of het schrijven in de database.");	
			}		
		}
		$this->include_goatee();
		print(postop_Goatee::fill($this->load_template('feedback_vragen.html'), ['requests' => $this->get_requests(10)]));
	}

	private function toggle_live($id)
	{
		$approved = $this->db->get_var( "SELECT approved FROM ".$this->table_name." WHERE id = '".$id."'" );
		$data = array('approved' => !$approved);
		$result = $this->db->update( $this->table_name, $data, array( 'id' => $id ) );
	}

	private function register_new_request($new_request)
	{
		return $this->db->insert($this->table_name, $new_request);
	}

	private function notify_customer($new_request)
	{
		$subject = "Hoe was uw oogbehandeling bij Eyecenter?";
		$message = "http://localhost:8000/?page_id=4&po_access_token=".$new_request['access_token'];

		return wp_mail(
			$new_request['email'],
			$subject,
			$message
		);
	}

	private function generate_token($length)
	{
		$token = bin2hex(openssl_random_pseudo_bytes($length));
		return $token;
	}

	private function get_requests($limit, $start=0)
	{
		$requests = $this->db->get_results("SELECT * FROM ".$this->table_name." ORDER BY id DESC LIMIT ".$start.",".$limit, ARRAY_A);
		if (!$requests) {
			return false;
		}
		return $requests;
	}

	private function get_request_data($id)
	{
		$request_data = $this->db->get_row("SELECT * FROM ".$this->table_name." WHERE id = ".$id);
		if (!$request_data) {
			return false;
		}
		return $request_data;
	}

	private function update_review()
	{
		$updated_review = array(
			'given_name' => $_POST['given_name'],
			'family_name' => $_POST['family_name'],
			'rating' => $_POST['rating'],
			'body' => $_POST['body'],
			'may_be_published' => (empty($_POST['may_be_published']) ? false : true),
			'approved' => (empty($_POST['approved']) ? false : true)
		);
		$success = $this->db->update($this->table_name, $updated_review, array('id' => $_POST['edit_review_id']));

		if ($success) {
			wp_redirect(admin_url('admin.php?page=postop_manage'));
		}
	}

	public function manage_reviews_page()
	{
		?>
			<h1>Alle Feedback</h1>

			<?php
				if (isset($_GET['edit_review'])) {
					$request = $this->get_request_data($_GET['edit_review']);
					?>
						<form action="" method="POST" name="po_review">
							<input type="hidden" name="edit_review_id" value="<?php echo ($request->id); ?>">
						<table>
						<tr>
							<td>Voornaam:</td><td><input type="text" name="given_name" value="<?php echo($request->given_name); ?>"></td>
						</tr>
						<tr>
							<td>Familienaam:</td><td><input type="text" name="family_name" value="<?php echo($request->family_name); ?>"></td>
						</tr>
						<tr>
							<td>Score (0&ndash;5):</td><td><select name="rating" id="postop_review_rating">
								<option value=0>niet beantwoord</option>
								<?php
								for ($i=1;$i<=5;$i++) {
									print("<option value=".$i);
									print($request->rating == $i ? " selected" : "");
									print(">".$i."</option>");
								}
								?>
							</select>
							</td>
						</tr>
						<tr>
							<td>Review:</td><td><textarea name="body"><?php echo($request->body); ?></textarea></td>
						</tr>
						<tr>
							<td>Mag publiek?</td><td><input type="checkbox" name="may_be_published" <?php echo ($request->may_be_published ? "checked" : ""); ?>></td>
						</tr>
						<tr>
							<td>Publiceren?</td><td><input type="checkbox" name="approved" <?php echo ($request->approved ? "checked" : ""); ?>></td>
						</tr>
						<tr>
							<td>Access token</td><td><?php echo ($request->access_token ? : "gebruikt"); ?></td>
						</tr>
						<tr><td colspan=2><input type="submit" value="Opslaan"></td></tr>
						</table>
						</form>
					<?php
				}
			?>
			<span style='background-color: lightgreen'>Toestemming voor publicatie</span>
			<span style='background-color: orange'>Geen toestemming voor publicatie</span>
			<table class="wp-list-table widefat fixed striped pages">
				<thead>
				<tr><th>Datum</th><th>Voornaam</th><th>Familienaam</th><th>Email</th><th>Score</th><th>Review</th><th>Live?</th><th>Bewerken</th></tr>
			</thead>
			<tbody>
				<?php
					$page = 1;
					if (isset($_GET['paginate'])) {
						$page = $_GET['paginate'];
					}
					foreach ($this->get_requests(10, ($page-1) * 10) as $request) {
						print("<tr style='background-color: ". ($request['may_be_published'] ? "lightgreen" : "orange") ."'><td>".$request['date']
							."</td><td>".$request['given_name']
							."</td><td>".$request['family_name']
							."</td><td>".$request['email']
							."</td><td>".($request['access_token'] ? "niet beantwoord" : $request['rating'])
							."</td><td>".substr($request['body'], 0, 20)."..."
							."</td><td><a href='".admin_url('admin.php?page=postop_manage')."&toggle=".$request['id']."'>".($request['approved'] ? "Ja" : "Nee")
							."</td><td><a href='".admin_url('admin.php?page=postop_manage&edit_review=').$request['id']."'>Bewerken</a>"
							."</a></td></tr>");
					}
				?>
			</tbody>
			</table>
			<br />
		<?php
			$num_entries = $this->db->get_var("SELECT COUNT(id) FROM ".$this->table_name);
			$pages = floor($num_entries/20);

			for ($i=0;$i<$pages;$i++) {
				echo("<a href='"
					.admin_url('admin.php?page=postop_manage&paginate=')
					.($i+1)
					."'>"
					.($i+1)
					."</a> | ");
			}

	}
}