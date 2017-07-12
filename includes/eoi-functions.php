<?php 


/* ADD ANIMATION CSS TO FRONT-END AND ADMIN PAGES WHEN ENABLED */
add_action( 'fca_eoi_display_lightbox', 'fca_eoi_load_animation_script_popup' );
add_action( 'fca_eoi_render_animation_meta_box', 'fca_eoi_load_animation_script_popup' );

/* REMOVE LINKS FOR USERS WITH FEWER PERMISSIONS THAN EDITOR */
			
function FCA_EOI_remove_admin_bar_link() {
	if (!current_user_can( 'delete_others_pages' )){
		remove_meta_box( 'fca_eoi_dashboard_widget', 'dashboard', 'normal' );	
	}
}

add_action( 'wp_before_admin_bar_render', 'FCA_EOI_remove_admin_bar_link' );

$fca_eoi_animation_enabled = false; //ONLY ENQUEUE WHEN ITS TURNED ON

function fca_eoi_load_animation_script_popup() {
	global $fca_eoi_animation_enabled;
	if ( $fca_eoi_animation_enabled ) {
		wp_enqueue_style( 'fca_eoi_powerups_animate', FCA_EOI_PLUGIN_URL . '/assets/vendor/animate/animate.css' );
	}
}

function fca_eoi_get_error_texts($id) {
		
		$post_meta = get_post_meta( $id , 'fca_eoi', true );
		$errorTexts = array(
            'field_required' => $post_meta[ 'error_text_field_required' ],
            'invalid_email' => $post_meta[ 'error_text_invalid_email' ],
        );
		
		if (!empty($errorTexts['field_required']) AND  !empty($errorTexts['invalid_email'])) {
			return array(
				'field_required' => $errorTexts['field_required'],
				'invalid_email' => $errorTexts['invalid_email'],
			);
		
		}else{
		
			return array(
				'field_required' => 'Error: This field is required.',
				'invalid_email' => "Error: Please enter a valid email address. For example \"max@domain.com\"."
			);
		}
}

function fca_eoi_get_thanks_mode($id) {
		
		$post_meta = get_post_meta( $id , 'fca_eoi', true );
		
        $mode = $post_meta[ 'thankyou_page_mode' ];
        
		if (empty ($mode)) {
			$mode = 'not set';
		}
		
		return $mode;
		
}

function fca_eoi_migrate_css() {
	
	require_once FCA_EOI_PLUGIN_DIR . 'powerups/4_new_css/powerup.php';
		
	//CHECK CURRENT SETTING
	$isActive = powerup_new_css_is_active();
	
	if ( !$isActive ) {
				
		$new_css_obj = new EoiNewCssMigration();
		$new_css_obj->migrate( 'old', 'new' );	
		
		powerup_new_css_set_active( TRUE );
	} 

}

function fca_eoi_compile_scss() {
	require_once FCA_EOI_PLUGIN_DIR . 'includes/eoi-layout.php';
	$layouts_types = array( 'lightbox',  'postbox', 'widget');
	$fca_eoi_global_scss_array = array();
	foreach ( $layouts_types as $layout_type ) {
	
		foreach ( glob( FCA_EOI_PLUGIN_DIR . "layouts/$layout_type/*", GLOB_ONLYDIR ) as $layout_path ) {
		
			$layout_id = basename( $layout_path );
			$layout_helper   = new EasyOptInsLayout( $layout_id );
			$scss = $layout_helper->new_scss_compiler();
			$scss_path = $layout_helper->path_to_resource( 'layout', 'scss' );
			
			if ( EasyOptInsLayout::uses_new_css() ) {
				$layout[ 'css' ] = file_exists( $scss_path )
					? '#fca_eoi_preview_form_container {' .
						file_get_contents( $scss_path ) .
						'.fca_eoi_layout_popup_close {' .
						  'display: none;' .
						'}' .
					  '}'
					: ''
				;
			} else {
				$layout[ 'css' ] = file_exists( $scss_path )
					? file_get_contents( $scss_path)
					: ''
				;
			}

			// Add $ltr SASS variable
			$layout[ 'css' ] = sprintf( '$ltr: %s;', is_rtl() ? 'false' : 'true' )
				. $layout[ 'css' ]
			;
			
			$name = $layout[ 'css' ];
			$layout[ 'css' ] = $scss->compile( $layout[ 'css' ] );
			$fca_eoi_global_scss_array[$name] = $layout[ 'css' ];
		}
	}

	$file = FCA_EOI_PLUGIN_DIR . "includes/cache";
	$fileWrite = file_put_contents($file, serialize($fca_eoi_global_scss_array)); 
}