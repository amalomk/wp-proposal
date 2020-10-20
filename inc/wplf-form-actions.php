<?php

add_action( 'wplf_post_validate_proposition', 'wplf_send_email_copy', 20 );
function wplf_send_email_copy( $return, $proposition_id = null ) {
  // Normally $proposition_id is null, but when resending copies it isn't, which is why this check exists
  if ( ! $proposition_id ) {
    $proposition_id = $return->proposition_id;
  }

  // _form_id is already validated and we know it exists by this point
  $form_id = intval( ( isset( $proposition_id ) ) ? get_post_meta( $proposition_id, '_form_id', true ) : $_POST['_form_id'] );

  $form = get_post( intval( $form_id ) );

  $form_title = esc_html( get_the_title( $form ) );
  $form_meta = get_post_meta( $form_id );

  $referrer = esc_url_raw( ( isset( $proposition_id ) ) ? get_post_meta( $proposition_id, 'referrer', true ) : $_POST['referrer'] );
  $email_enabled = ! empty( $form_meta['_wplf_email_copy_enabled'] ) ? (int) $form_meta['_wplf_email_copy_enabled'][0] : false;

  if ( $email_enabled ) {
    $to = isset( $form_meta['_wplf_email_copy_to'] ) ? $form_meta['_wplf_email_copy_to'][0] : get_option( 'admin_email' );

    // translators: %proposition-id% is replaced with proposition id and %referrer% with referrer url
    $subject = __( '[%proposition-id%] New proposition from %referrer%', 'wp-proposal' );
    if ( isset( $form_meta['_wplf_email_copy_subject'] ) ) {
      $subject = $form_meta['_wplf_email_copy_subject'][0];
    }

    $to = empty( $to ) ? get_option( 'admin_email' ) : $to;

    // translators: %form-title% is replaced with form title and %form-id% with form id
    // @codingStandardsIgnoreStart
    // %f gets detected as a placeholder for wp_sprintf
    $content = __( 'Form %form-title% (ID %form-id%) was submitted with values below: ', 'wp-proposal' );
    // @codingStandardsIgnoreEnd
    $content = apply_filters( 'wplf_email_copy_content_start', $content, $form_title, $form_id ) . "\n\n";

    $fields = $_POST;
    if ( isset( $proposition_id ) ) {
      $fields = get_post_meta( $proposition_id );
    }

    $content .= wplf_email_copy_make_fields_key_value_list( $fields, $form->ID, $form->post_name );

    // default pre-filtered values for email headers and attachments
    $headers = array();
    $attachments = array();

    if ( isset( $form_meta['_wplf_email_copy_from'][0] ) ) {
      $from = wplf_email_copy_replace_tags( $form_meta['_wplf_email_copy_from'][0], $form, $proposition_id );
      $from_address = wplf_email_copy_replace_tags( $form_meta['_wplf_email_copy_from_address'][0], $form, $proposition_id );
      $headers .= "From: $from <$from_address>";
    }

    if ( isset( $form_meta['_wplf_email_copy_content'] ) ) {
      $content = $form_meta['_wplf_email_copy_content'][0];
    }

    // maybe replace template tags with real content
    $to = wplf_email_copy_replace_tags( $to, $form, $proposition_id );
    $subject = wplf_email_copy_replace_tags( $subject, $form, $proposition_id );
    $content = wplf_email_copy_replace_tags( $content, $form, $proposition_id );

    // allow filtering email fields
    $to = apply_filters( 'wplf_email_copy_to', $to, $return, $proposition_id );
    $subject = apply_filters( 'wplf_email_copy_subject', $subject, $return, $proposition_id );
    $content = apply_filters( 'wplf_email_copy_content', $content, $return, $proposition_id );
    $headers = apply_filters( 'wplf_email_copy_headers', $headers, $return, $proposition_id );
    $attachments = apply_filters( 'wplf_email_copy_attachments', $attachments, $return, $proposition_id );

    // form slug specific filters
    $to = apply_filters( "wplf_{$form->post_name}_email_copy_to", $to, $return, $proposition_id );
    $subject = apply_filters( "wplf_{$form->post_name}_email_copy_subject", $subject, $return, $proposition_id );
    $content = apply_filters( "wplf_{$form->post_name}_email_copy_content", $content, $return, $proposition_id );
    $headers = apply_filters( "wplf_{$form->post_name}_email_copy_headers", $headers, $return, $proposition_id );
    $attachments = apply_filters( "wplf_{$form->post_name}_email_copy_attachments", $attachments, $return, $proposition_id );

    // form ID specific filters
    $to = apply_filters( "wplf_{$form->ID}_email_copy_to", $to, $return, $proposition_id );
    $subject = apply_filters( "wplf_{$form->ID}_email_copy_subject", $subject, $return, $proposition_id );
    $content = apply_filters( "wplf_{$form->ID}_email_copy_content", $content, $return, $proposition_id );
    $headers = apply_filters( "wplf_{$form->ID}_email_copy_headers", $headers, $return, $proposition_id );
    $attachments = apply_filters( "wplf_{$form->ID}_email_copy_attachments", $attachments, $return, $proposition_id );

    wp_mail( $to, $subject, $content, $headers, $attachments );
  }
}

function wplf_email_copy_make_fields_key_value_list( $fields, $form_id = 0, $form_name = '' ) {
  $list = '';

  foreach ( $fields as $key => $value ) {
    if ( '_' === $key[0] ) {
      continue;
    }

    $value = $value[0];
    $value = wplf_email_maybe_implode_serialized_value( $value, $form_id, $form_name );

    // @codingStandardsIgnoreStart
    // WP coding standards don't like print_r
    // @TODO: come up with a prettier format for default mail output
    $list .= esc_html( $key ) . ': ' . esc_html( print_r( $value, true ) ) . "\n";
    // @codingStandardsIgnoreEnd
  }

  return $list;
}

function wplf_email_copy_replace_tags( $content, $form = null, $proposition_id = null ) {
  if ( ! $form || ! $proposition_id ) {
    return $content;
  }

  $fields = $_POST;
  if ( isset( $proposition_id ) ) {
    $fields = get_post_meta( $proposition_id );
  }

  $fields_key_value = wplf_email_copy_make_fields_key_value_list( $fields, $form->ID, $form->post_name );

  $defaults_store = array(
    'proposition-id' => $proposition_id,
    'referrer'      => esc_url_raw( ( null !== $proposition_id )
      ? get_post_meta( $proposition_id, 'referrer', true )
      : $_POST['referrer'] ),
    'form-title'    => esc_html( get_the_title( $form ) ),
    'form-id'       => $form->ID,
    'user-id'       => ( null !== get_current_user_id() )
      ? wp_get_current_user()->display_name . ' (ID ' . get_current_user_id() . ')'
      : __( 'No user logged in', 'wp-proposal' ),
    'timestamp'     => current_time( 'mysql' ),
    'datetime'      => current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
    'language'      => ( function_exists( 'pll_current_language' ) ) ? pll_current_language( 'locale' ) : get_locale(),
    'all-form-data' => $fields_key_value,
  );

  $fields = $_POST;
  if ( null !== $proposition_id ) {
    $fields = get_post_meta( $proposition_id );
  }

  preg_match_all( '/%(.+?)%/', $content, $matches );
  foreach ( $matches[0] as $match ) {
    // match contains the braces, get rid of them.
    $string = trim( str_replace( array( '%' ), array( '' ), $match ) );

    if ( isset( $fields[ $string ] ) ) {
      $value = $fields[ $string ][0];
    } elseif ( isset( $defaults_store[ $string ] ) ) {
      $value = $defaults_store[ $string ];
    }

    $value = wplf_email_maybe_implode_serialized_value( $value, $form->ID, $form->post_name );
    $content = str_replace( $match, $value, $content );
  }

  return $content;
}

// @codingStandardsIgnoreStart Generic.CodeAnalysis.UnusedFunctionParameter
function wplf_email_maybe_implode_serialized_value( $value, $form_id = 0, $form_name = '' ) {
// @codingStandardsIgnoreEnd Generic.CodeAnalysis.UnusedFunctionParameter
  $value = maybe_unserialize( $value );

  if ( is_array( $value ) ) {
    $implode_glue = apply_filters( 'wplf_email_array_field_implode_glue', ', ' );
    $implode_glue = apply_filters( "wplf_{$form_name}_email_array_field_implode_glue", $implode_glue );
    $implode_glue = apply_filters( "wplf_{$form_id}_email_array_field_implode_glue", $implode_glue );

    $value = implode( $implode_glue, $value );
  }

  return $value;
}