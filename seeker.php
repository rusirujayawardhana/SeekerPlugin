<?php
/*
Plugin Name: Seeker
Plugin URI: https://surge.global
Description: Revolutionize your website's search functionality with Seeker, a powerful WordPress plugin that enhances user experience and makes finding information effortless. With Seeker, users can search for specific keywords across pages, posts, and custom post types, including meta information, enabling comprehensive results that leave no stone unturned. The intuitive table format presents search results in an organized manner, allowing users to quickly navigate and identify relevant content. Tailor Seeker to your needs by customizing search parameters, including post types and specific metadata to analyze. Unlock the true potential of your website's search capabilities with Seeker and provide users with a seamless way to access the content they seek. Install Seeker today and experience the next generation of search capabilities for your WordPress website. 
Version: 1.0
Author: Surge Global
Author URI: https://surge.global
*/

class Seeker_Plugin {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_seek_posts', array($this, 'seek_posts'));
        add_action('wp_ajax_nopriv_seek_posts', array($this, 'seek_posts'));
		add_action('admin_menu', array($this, 'add_menu_page'));
    }

    public function enqueue_scripts() {
		//random version number
		$version_number = rand(1,9999999);
		
        // Enqueue jQuery and custom script
        wp_enqueue_script('jquery', plugins_url('assets/jquery-3.7.0.min.js', __FILE__), array(), $version_number , true);
        wp_enqueue_script('seeker_script', plugins_url('assets/script.js', __FILE__), array('jquery'), $version_number , true);
        wp_localize_script('seeker_script', 'seeker_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
		
		// Enqueue the style.css file
    	wp_enqueue_style('seeker_style', plugins_url('assets/style.css', __FILE__), array(), $version_number , 'all' );
    }

    public function seek_posts() {
        // Handle the AJAX request and search for posts
        $keyword = sanitize_text_field($_POST['keyword']);
        $results = $this->perform_search($keyword);

        // Return the search results as JSON
        wp_send_json($results);
    }

   private function perform_search($keyword) {
		global $wpdb;

		// Search in post content
		$args = array(
			's'                   => $keyword,
			'post_type'           => array('post', 'page', 'custom_post_type'),
			'posts_per_page'      => -1,
			'ignore_sticky_posts' => true,
		);

		$query = new WP_Query($args);
		$results = array();

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$post_data = array(
					'title'     => get_the_title(),
					'edit_link' => get_edit_post_link(get_the_ID()),
					'page_id'   => get_the_ID(),
					'page_link' => get_permalink(),
					'source'    => $this->get_keyword_source(get_the_ID(), $keyword),
					// Add more data as needed
				);
				$results[] = $post_data;
			}
			wp_reset_postdata();
		}

		// Search in all post meta fields
		$meta_query = $wpdb->prepare(
			"
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key != ''
			AND meta_value LIKE %s
			",
			'%' . $wpdb->esc_like($keyword) . '%'
		);

		$meta_post_ids = $wpdb->get_col($meta_query);

		if (!empty($meta_post_ids)) {
			$meta_post_ids = array_unique($meta_post_ids);

			foreach ($meta_post_ids as $post_id) {
				$post_data = array(
					'title'     => get_the_title($post_id),
					'edit_link' => get_edit_post_link($post_id),
					'page_id'   => $post_id,
					'page_link' => get_permalink($post_id),
					'source'    => 'Meta',
					// Add more data as needed
				);
				$results[] = $post_data;
			}
		}
		return $results;
	}


	private function get_keyword_source($post_id, $keyword) {
		// Check if the keyword is found in post content or post meta
		$content = get_post_field('post_content', $post_id);

		// Check post content for the keyword
		if (stripos($content, $keyword) !== false) {
			return 'Content';
		}	
	}
	
	
	public function add_menu_page() {
        add_options_page(
            'Seeker Settings',
            'Seeker',
            'manage_options',
            'seeker_settings',
            array($this, 'render_settings_page'),
        );
    }
	
	public function render_settings_page() {
		?>
		<form id="seeker-form">    
			<div class="seeker-form-wrapper">
				<input type="text" id="seeker-keyword" name="keyword" placeholder="Enter a keyword">
				<button type="submit">Search</button>
			</div>
		</form>
		<div id="seeker-results"></div>

		<script>
			jQuery(document).ready(function($) {
				$('#seeker-form').on('submit', function(e) {
					e.preventDefault();
					var keyword = $('#seeker-keyword').val();

					$.ajax({
						type: 'POST',
						url: '<?php echo admin_url('admin-ajax.php'); ?>',
						data: {
							action: 'seek_posts',
							keyword: keyword
						},
						success: function(response) {
							if (response.length > 0) {
								var html = '<table>';
								html += '<tr><th>Page ID</th><th>Title</th><th>Edit Link</th><th>Keyword Source</th></tr>';

								$.each(response, function(index, post) {
									html += '<tr>';
									html += '<td>' + post.page_id + '</td>';
									html += '<td><a href="' + post.page_link + '">' + post.title  + '</a></td>';
									html += '<td><a href="' + post.edit_link + '">' + post.edit_link  + '</a></td>';
									html += '<td>' + post.source + '</td>';
									html += '</tr>';
								});

								html += '</table>';

								$('#seeker-results').html(html);
							} else {
								$('#seeker-results').html('<p>No results found.</p>');
							}
						}
					});
				});
			});
		</script>
		<?php
	}
}

// Initialize the plugin
new Seeker_Plugin();
