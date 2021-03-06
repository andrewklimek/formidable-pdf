<?php

class FPPDF_Common
{
	public static function setup_ids()
	{
		global $form_id, $entry_id, $entry_ids;

		$form_id = $form_id ? $form_id : absint( filter_input(INPUT_GET,"fid") );
		$entry_ids = $entry_id ? array($entry_id) : explode(',', filter_input(INPUT_GET,"lid"));

		/**
		 * If form ID and entry ID hasn't been set stop the PDF from attempting to generate
		 */
		if(empty($form_id) || empty($entry_ids))
		{
			trigger_error('Form Id and entry Id are required parameters.');
			return;
		}
	}

	/**
	 * New simpler method
	 */
	public static function entry_data( $entry_id )
	{
		$data = [];
		// $data['field'] = [];// for backwards compat... or regex your templates: \$form_data\['field'\](\['[\w\d]+?'\])\['value'\] >>> $form_data$1

		$entry = FrmEntry::getOne($entry_id, true);

		$data['form_title']			= !empty( $entry->form_name ) ? $entry->form_name : '';// this seems to be undefined sometimes when FrmEntry::getOne pulls from cache... could fetch it somehow, but how often would it really be used in templates?
		$data['form_id']			= $entry->form_id;
		$data['entry_id']			= $entry->id;
		$data['updated_at']			= $entry->updated_at;
		$data['created_at']			= $entry->created_at;
		$data['timestamp']			= strtotime( $entry->created_at );
		$data['date_created']		= date('d/m/Y', $data['timestamp']);
		$data['date_created_usa']	= date('m/d/Y', $data['timestamp']);// this is weird, shouldn't they format it as desired in the templates?
		$data['label']				= [];// will hold the displayed label values for any fields that have separate saved values

		$fields = FrmField::getAll(['fi.form_id' => $entry->form_id], 'field_order');

		foreach ( $fields as $k => $f )
		{
			if ( strpos( $f->field_options['classes'], 'pdf_hidden') === false ) {
				$data[ $f->id ] = isset( $entry->metas[ $f->id ] ) ? $entry->metas[ $f->id ] : '';
			}
			// handle separate saved values
			if ( $f->field_options['separate_value'] ) {
				if ( ! $data[ $f->id ] ) {// still need to add blanks to the 'label' array so that they don't throw undefined index warnings when used in templates
					$data['label'][ $f->id ] = '';
				} elseif ( is_array( $data[ $f->id ] ) ) {// array stuff because it can be checkboxes or some other multi-select field
					$data['label'][ $f->id ] = [];
					foreach ( $f->options as $o ) {
						if ( in_array( $o['value'], $data[ $f->id ], true ) ) {
							$data['label'][ $f->id ][] = $o['label'];
						}
					}
				} else {
					foreach ( $f->options as $o ) {
						if ( $o['value'] == $data[ $f->id ] ) {
							$data['label'][ $f->id ] = $o['label'];
							break;
						}
					}
				}
			}
			// multi-select convert arrays to strings
			if ( $data[ $f->id ] ) {
				if ( is_array( $data[ $f->id ] ) ) {// fyi checkboxes with just one option aren't arrays
					$data[ $f->id ] = implode( '; ', $data[ $f->id ] );
					if ( isset( $data['label'][ $f->id ] ) ) {
						if ( is_array( $data['label'][ $f->id ] ) ) {
							$data['label'][ $f->id ] = implode( '; ', $data['label'][ $f->id ] );
			// TESTING
						} else {
							error_log(var_export($f,true) . "\npdf plugin: value was array but not label");
						}
					}
					if ( $f->type != "checkbox" ) {
						error_log(var_export($f,true) . "\npdf plugin: value is an array but not a checkbox field");
					}
			// END TESTING
				}
			}

			// make keys available too... for now?
			$data[ $f->field_key ] = $data[ $f->id ];
			if ( isset( $data['label'][ $f->id ] ) ) $data['label'][ $f->field_key ] = $data['label'][ $f->id ];
			// for backwards compat... or regex your templates: \$form_data\['field'\](\['[\w\d]+?'\])\['value'\] >>> $form_data$1
			// $data['field'][ $f->field_key ] = ['value' => $data[ $f->id ] ];
			// $data['field'][ $f->field_key ]['string'] = isset($data['label'][ $f->id ]) ? $data['label'][ $f->id ] : $data[ $f->id ];
		}
		// make a ['value'] array for separate value fields as well, to correspond to the ['label'] array, and allow more explicit references
		// $data['value'] = [];// define this above, just under the ['label'] = []
		// foreach ( $data['label'] as $k => $l ) {
		// 	$data['value'][ $k ] .= $data[ $k ];
		// }

		// debug functionality from view_data() which seemed fun
		if ( !empty( $_GET['data'] ) ) { echo '<pre>'; print_r($data); echo '</pre>'; }

		return $data;
	}

	/*
	 * Remove any form fields with pdf_hidden in the class name
	 */
	// public static function get_form_fields($form_id)
	// {
    //     $fields = FrmField::getAll(array('fi.form_id' => $form_id), 'field_order');

    //     foreach($fields as $k => $f){
	// 		if(strpos($f->field_options['classes'], 'pdf_hidden') !== false)
	// 		{
	// 			unset($fields[$k]);
	// 		}
    //     }

    //     return $fields;
	// }

	 /*
	  * Check if the system is fully installed and return the correct values
	  */
	public static function is_fully_installed() {
		global $frmpro_is_installed;

		if ( ! $frmpro_is_installed ) {
			if ( ! is_callable( 'FrmAppHelper::pro_is_installed' ) || ! FrmAppHelper::pro_is_installed() ){
				return false;
			}
		}

		if( (get_option('fp_pdf_extended_installed') != 'installed') || (!is_dir(FP_PDF_TEMPLATE_LOCATION)) )
		{
			return false;
		}

		 return true;
	 }

	public static function get_html_template($filename)
	{
	  global $form_id, $entry_id, $entry_ids;

	  ob_start();
	  require($filename);

	  $page = ob_get_contents();
	  ob_end_clean();

	  return $page;
	}

	/**
	 * Get the name of the PDF based on the Form and the submission
	 */
	public static function get_pdf_filename($form_id, $entry_id)
	{
		return "form-$form_id-entry-$entry_id.pdf";
	}

	/*
	 * We need to validate the PDF name
	 * Check the size limit, if the file name's syntax is correct
	 * and strip any characters that aren't classed as valid file name characters.
	 */
	public static function validate_pdf_name($name, $form_id = false, $entry_id = false)
	{
		$pdf_name = $name;

		if($form_id > 0)
		{
			$pdf_name = self::do_mergetags($pdf_name, $form_id, $entry_id);
		}

		/*
		 * Limit the size of the filename to 150 characters
		 */
		 if(strlen($pdf_name) > 150)
		 {
			$pdf_name = substr($pdf_name, 0, 150);
		 }

		/*
		 * Remove extension from the end of the filename so we can replace all '.'
		 * Will add back before we are finished
		 */
		if(substr($pdf_name, -4) == '.pdf')
		{
			$pdf_name = substr($pdf_name, 0, -4);
		}

		/*
		 * Remove any invalid (mostly Windows) characters from filename
		 */
		 $pdf_name = str_replace( ['/', '\\', '"', '*', '?', '|', ':', '<', '>', '.'], '-', $pdf_name );

		 $pdf_name = $pdf_name . '.pdf';

		return $pdf_name;
	}

	public static function do_mergetags($string, $form_id, $entry_id)
	{
		/* strip {all_fields} merge tag from $string */
		$string = str_replace('[default-message]', '', $string);

		$entry = FrmEntry::getOne($entry_id, true);
        $shortcodes = FrmProDisplaysHelper::get_shortcodes($string, $form_id);
        return FrmProContent::replace_shortcodes($string, $entry, $shortcodes);
	}

	/*
	 * WP_Filesystem API to manipulate files instead of using in-built PHP functions
	 * $post Array the post data to include in the request_filesystem_credntials API
	 */
	public static function initialise_WP_filesystem_API($post, $nonce)
	{

		$url = FP_PDF_SETTINGS_URL;

		if (false === ($creds = request_filesystem_credentials($url, '', false, false, $post) ) ) {
			/*
			 * If we get here, then we don't have correct permissions and we need to get the FTP details.
			 * request_filesystem_credentials will handle all that
			 */
			return false; // stop the normal page form from displaying
		}

		/*
		 * Check if the credentials are no good and display an error
		 */
		if ( ! WP_Filesystem($creds) ) {
			request_filesystem_credentials($url, '', true, false, null);
			return false;
		}

		return true;

	}

}
