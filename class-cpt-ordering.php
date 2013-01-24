<?php

/**
* Order_CPT Class
* Include this script on init or it will not work.
*/
class Order_CPT {

	public $cpt_to_order;

	/**
	 * Setup the process of ordering CPT lists in wp-admin
	 * @param string $cpt_to_order custom post type slug to order
	 * @param string $taxonomy     taxonomies to list
	 */
	function __construct($cpt_to_order, $taxonomy = '') {
		if ( !is_admin() ) {
			return;
		}
		$this->cpt_to_order = $cpt_to_order;

		$this->enqueue_scripts_and_styles();

		//load custom columns on the edit slides page
		add_filter( "manage_edit-{$this->cpt_to_order}_columns", array( $this, 'additional_column_names' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'additional_column_data' ), 10, 2 );

		add_action( 'wp_ajax_save_order_cpt', array( $this, 'save_order' ) );

		if ( !empty($taxonomy) ) {
			$this->taxonomy = $taxonomy;
			add_filter('restrict_manage_posts' , array( $this, "restrict_by_event_type") );
			add_filter('parse_query', array( $this, 'convert_id_to_slug') );
		}
	}

	public function enqueue_scripts_and_styles() {

		if ( isset($_GET['post_type']) && $this->cpt_to_order === $_GET['post_type'] && is_admin() ) {
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-sortable');
			add_action( 'admin_head' , array( $this, 'order_cpt_script' ) );
		}
	}

	public function order_cpt_script() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('table.widefat:not(.tags)').sortable({
					update: function(event, ui) {
						var cpt_order = $('table.widefat').sortable('serialize'),
							data = {
								action: 'save_order_cpt',
								data: cpt_order
							}
						$.post(
							ajaxurl,
							cpt_order,
							function(returned_data) {}
						);
					},
					items: 'tbody tr',
					axis: 'y',
					containment: 'table.widefat tbody',
					cursor: 'move',
					cancel: 'tr.inline-edit-slides',
					handle: '.column-order img'
				});
			});
		</script>
		<?php
	}


	/**
	 *
	 *
	 * @param unknown $columns
	 * @return unknown
	 */
	public function additional_column_names($columns) {
		unset($columns['cb']);
		$new_columns['cb']    = '<input type="checkbox">';
		$new_columns['order'] = 'Order';
		$new_columns += $columns;
		return $new_columns;
	}


	/**
	 *
	 * @param unknown $columns
	 */
	public function additional_column_data($columns) {
		global $post;

		if ( isset($_GET['post_type']) && $this->cpt_to_order == $_GET['post_type'] && is_admin() ) {
			switch ( $columns ) {
				case 'order' :
					// if the  taxonomy is set and there is no request for taxonomy
					// do not allow ordering
					if ( !empty($this->taxonomy) && empty($_GET[$this->taxonomy]) ) {
						echo '[X]';
					} else {
						echo '<img class="bigfishslides_drag" src="'.BFPLUGIN_URL.'/images/roll-over-drag.png" title="Drag To Order" />';
					}
				break;
			}
		}
	}


	/* Save the CPT post order */

	/**
	 *
	 */
	public function save_order() {
		global $wpdb;

		if ( isset( $_POST['post'] ) ) {
			foreach ( (array) $_POST['post'] as $position => $post_id ) {
				$wpdb->update(
					$wpdb->posts,
					array('menu_order' => (int) $position),
					array('ID' => (int) $post_id)
				);
			}
		}

	}

	function restrict_by_event_type() {
		global $typenow;

		if ( $typenow === $this->cpt_to_order ) {
			$selected = isset($_GET[$this->taxonomy]) ? esc_attr($_GET[$this->taxonomy]) : '';
			$info_taxonomy = get_taxonomy($this->taxonomy);

			wp_dropdown_categories(
				array(
					'show_option_all' => __("Show All {$info_taxonomy->label}"),
					'taxonomy'        => $this->taxonomy,
					'name'            => $this->taxonomy,
					'orderby'         => 'name',
					'selected'        => $selected,
					'show_count'      => false,
					'hide_empty'      => false,
				)
			);
		};
	}


	function convert_id_to_slug($query) {
		global $pagenow;
		$q_vars = &$query->query_vars;

		$is_post_type = false;
		if ( !empty($q_vars['post_type']) && $this->cpt_to_order === $q_vars['post_type'] ) {
			$is_post_type = true;
		}

		$is_taxonomy = false;
		if ( !empty($q_vars[$this->taxonomy]) && is_numeric($q_vars[$this->taxonomy]) && $q_vars[$this->taxonomy] !== 0 ) {
			$is_taxonomy = true;
		}

		if ( $pagenow == 'edit.php' && $is_post_type && $is_taxonomy ) {
			$term = get_term_by('id', $q_vars[$this->taxonomy], $this->taxonomy);
			$q_vars[$this->taxonomy] = $term->slug;
		}
	}

}
?>
