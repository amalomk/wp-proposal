<?php
wp_enqueue_style( 'mon-custom-style', get_template_directory_uri().'/mon-custom-style.css' );
if ( ! class_exists( 'CPT_WPLF_proposition' ) ) :

class CPT_WPLF_proposition {
  /**
   * CPT for the propositions
   */
  public static $instance;

  public static function init() {
    if ( is_null( self::$instance ) ) {
      self::$instance = new CPT_WPLF_proposition();
    }
    return self::$instance;
  }

  /**
   * Hook our actions, filters and such
   */
  public function __construct() {
    // init custom post type
    add_action( 'init', array( $this, 'register_cpt' ) );

    // post.php view
    add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_cpt' ) );

    // edit.php view
    add_filter( 'manage_edit-wplf-proposition_columns', array( $this, 'custom_columns_cpt' ), 100, 1 );
    add_action( 'manage_posts_custom_column', array( $this, 'custom_columns_display_cpt' ), 10, 2 );
    add_action( 'restrict_manage_posts', array( $this, 'form_filter_dropdown' ) );
    add_filter( 'pre_get_posts', array( $this, 'filter_by_form' ) );

    // add custom bulk actions
    add_action( 'admin_notices', array( $this, 'wplf_proposition_bulk_action_admin_notice' ) );
    add_filter( 'bulk_actions-edit-wplf-proposition', array( $this, 'register_wplf_proposition_bulk_actions' ) );
    add_filter( 'handle_bulk_actions-edit-wplf-proposition', array( $this, 'wplf_proposition_bulk_action_handler' ), 10, 3 );
  }

  public static function register_cpt() {
    $labels = array(
      'name'               => _x( 'propositions', 'post type general name', 'wp-proposal' ),
      'singular_name'      => _x( 'proposition', 'post type singular name', 'wp-proposal' ),
      'menu_name'          => _x( 'propositions', 'admin menu', 'wp-proposal' ),
      'name_admin_bar'     => _x( 'proposition', 'add new on admin bar', 'wp-proposal' ),
      'add_new'            => _x( 'Add New', 'proposition', 'wp-proposal' ),
      'add_new_item'       => __( 'Add New proposition', 'wp-proposal' ),
      'new_item'           => __( 'New proposition', 'wp-proposal' ),
      'edit_item'          => __( 'Edit proposition', 'wp-proposal' ),
      'view_item'          => __( 'View proposition', 'wp-proposal' ),
      'all_items'          => __( 'propositions', 'wp-proposal' ),
      'search_items'       => __( 'Search propositions', 'wp-proposal' ),
      'not_found'          => __( 'No propositions found.', 'wp-proposal' ),
      'not_found_in_trash' => __( 'No propositions found in Trash.', 'wp-proposal' ),
    );

    $args = array(
      'labels'             => $labels,
      'public'             => true,
     
      'show_ui'            => true,
      'show_in_menu'       => 'edit.php?post_type=wplf-form',
      'menu_icon'          => 'dashicons-archive',
      'query_var'          => false,
      'rewrite'            => null,
      'capability_type'    => 'post',
      'capabilities' => array(
        'create_posts' => 'do_not_allow',
      ),
      'map_meta_cap' => true,
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => null,
      'supports'           => array( 'title', 'custom-fields' ),
    );

    register_post_type( 'wplf-proposition', $args );
  }


  /**
   * Custom column display for proposition CPT in edit.php
   */
  public function custom_columns_display_cpt( $column, $post_id ) {
    if ( 'referrer' === $column ) {
      if ( $referrer = get_post_meta( $post_id, 'referrer', true ) ) {
        echo '<a href="' . esc_url_raw( $referrer ) . '">' . esc_url( $referrer ) . '</a>';
      }
    }
    if ( 'form' === $column ) {
      if ( $form_id = get_post_meta( $post_id, '_form_id', true ) ) {
        $form = get_post( $form_id );
        echo '<a href="' . esc_url_raw( get_edit_post_link( $form_id, '' ) ) . '" target="_blank">';
        echo esc_html( $form->post_title );
        echo '</a>';
      }
    }
  }

  /**
   * Custom columns in edit.php for propositions
   */
  public function custom_columns_cpt( $columns ) {
    $new_columns = array(
      'cb' => $columns['cb'],
      'title' => $columns['title'],
      'referrer' => __( 'Referrer', 'wp-proposal' ),
      'form' => __( 'Form', 'wp-proposal' ),
      'date' => $columns['date'],
    );
    return $new_columns;
  }

  /**
   * Show a form filter in the edit.php view
   */
  public function form_filter_dropdown() {
    global $pagenow;

    $allowed = array( 'wplf-proposition' ); // show filter on these post types (currently only one?)
    $allowed = apply_filters( 'wplf-dropdown-filter', $allowed );
    $post_type = get_query_var( 'post_type' );

    if ( 'edit.php' !== $pagenow || ! in_array( $post_type, $allowed, true ) ) {
      return;
    }

    $transient = get_transient( 'wplf-form-filter' );

    if ( $transient ) {
      $propositions = $transient;
    } else {
      $query = new WP_Query( array(
        'post_per_page' => '-1',
        'post_type' => 'wplf-form',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
      ) );
      $propositions = $query->get_posts();

      set_transient( 'wplf-form-filter', $propositions, 15 * MINUTE_IN_SECONDS );
    }

?>
<label for="filter-by-form" class="screen-reader-text">Filter by form</label>
<select name="form" id="filter-by-form">
  <option value="0"><?php esc_html_e( 'All propositions', 'wp-proposal' ); ?></option>
  <?php foreach ( $propositions as $form ) : ?>
    <option
      value="<?php echo intval( $form->ID ); ?>"
      <?php echo isset( $_REQUEST['form'] ) && intval( $_REQUEST['form'] ) === $form->ID ? 'selected' : ''; ?>
    ><?php echo esc_html( $form->post_title ); ?></option>
  <?php endforeach; ?>
</select>
<?php
  }

  /**
   * Filter by form in the edit.php view
   */
  public function filter_by_form( $query ) {
    global $pagenow;

    if ( 'edit.php' !== $pagenow ) {
      return $query;
    }

    if ( $query->get( 'post_type' ) !== 'wplf-proposition' ) {
      return $query;
    }

    if ( isset( $_REQUEST['form'] ) && ! empty( $_REQUEST['form'] ) ) {
      $query->set( 'meta_key', '_form_id' );
      $query->set( 'meta_value', intval( $_REQUEST['form'] ) );
    }

    return $query;
  }

  public function register_wplf_proposition_bulk_actions( $bulk_actions ) {
    $bulk_actions['wplf_resend_copy'] = __( 'Resend email copy', 'wp-proposal' );
    return $bulk_actions;
  }

  public function wplf_proposition_bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
    if ( $doaction !== 'wplf_resend_copy' ) {
      return $redirect_to;
    }

    foreach ( $post_ids as $post_id ) {
      $return = new stdClass();
      $return->ok = 1;

      wplf_send_email_copy( $return, $post_id );
    }

    $redirect_to = add_query_arg( 'wplf_resent', count( $post_ids ), $redirect_to );
    return $redirect_to;
  }

  public function wplf_proposition_bulk_action_admin_notice() {
    if ( ! empty( $_REQUEST['wplf_resent'] ) ) {
      $count = intval( $_REQUEST['wplf_resent'] );
      printf(
        '<div id="wplf-proposition-bulk-resend-message" class="notice notice-success"><p>' .
          esc_html(
             // translators: %s is number of propositions
            _n(
              'Resent email copy of %s proposition.',
              'Resent email copy of %s propositions.',
              $count,
             'wp-proposal'
            )
          ) .
          '</p></div>',
        intval( $count )
      );
    }
  }

  /**
   * Add meta box to show fields in form
   */
  public function add_meta_boxes_cpt() {
    // Shortcode meta box
    add_meta_box(
      'wplf-shortcode',
      __( 'proposition', 'wp-proposal' ),
      array( $this, 'metabox_proposition' ),
      'wplf-proposition',
      'normal',
      'high'
    );
  }

  /**
   * The proposition metabox callback
   */
  public function metabox_proposition() {
    global $post;
    $postmeta = get_post_meta( $post->ID );
    $fields = array_keys( $postmeta );
    $home_path = get_home_path();
?>
<p>
  <table class="wp-list-table widefat striped">
    <thead>
      <tr>
        <th><strong><?php esc_html_e( 'Field', 'wp-proposal' ); ?></strong></th>
        <th><strong><?php esc_html_e( 'Value', 'wp-proposal' ); ?></strong></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $fields as $field ) : ?>
        <?php if ( '_' !== $field[0] ) : ?>
        <?php
        $value = $postmeta[ $field ][0];

        // maybe show a link for the field if suitable
        $possible_link = '';

        // if the field ends with '_attachment' and there is an attachment url that corresponds to the id, show a link
        $attachment_suffix = '_attachment';
        if ( substr( $field, -strlen( $attachment_suffix ) ) === $attachment_suffix ) {
          if ( wp_get_attachment_url( $value ) ) {
            $link_text = __( 'View Attachment', 'wp-proposal' );
            $possible_link = '<a target="_blank" href="' . get_edit_post_link( $value ) . '" style="float:right">';
            $possible_link .= $link_text . '</a>';
          }
        }

        // Show a link if the field corresponds to a URL
        // assume values starting with '/' are root relative URLs and should be handled as links
        $value_is_url = false;
        if ( strlen( $value ) > 0 ) {
          $value_is_url = $value[0] === '/' ? true : filter_var( $value, FILTER_VALIDATE_URL );
        }
        if ( $value_is_url ) {
          $link_text = __( 'Open Link', 'wp-proposal' );
          $possible_link = '<a target="_blank" href="' . $value . '" style="float:right">' . $link_text . '</a>';
        }
        ?>
        <tr>
          <th><strong><?php echo esc_html( $field ); ?></strong> <?php echo wp_kses( $possible_link, '' ); ?></th>
          <?php if ( strlen( $value ) > 60 || strpos( $value, "\n" ) ) : ?>
          <td><textarea style="width:100%" readonly><?php echo esc_textarea( $value ); ?></textarea></td>
          <?php else : ?>
          <td><input style="width:100%" type="text" value="<?php echo esc_attr( $value ); ?>" readonly></td>
          <?php endif; ?>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</p>
<?php
  }
}

endif;
