<?php

/**
 * Debugging can be done by adding &html=1 to the end of the URL when viewing the PDF
 * We no longer need to access the file directly.
 */ 
if(!class_exists('FPPDF_Core') ) {
	/* Accessed directly */
    exit;
}

/** 
 * Set up the form ID and entry ID, as well as we want page breaks displayed. 
 * Form ID and entry ID can be set by passing it to the URL - ?fid=1&lid=10
 */
 FPPDF_Common::setup_ids();

/**
 * Load the form data, including the custom style sheet which looks in the plugin's theme folder before defaulting back to the plugin's file.
 */
$form = RGFormsModel::get_form_meta($form_id);
$stylesheet_location = (file_exists(FP_PDF_TEMPLATE_LOCATION.'template.css')) ? FP_PDF_TEMPLATE_URL_LOCATION.'template.css' : FP_PDF_PLUGIN_URL .'styles/template.css' ;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
   
    <title>Formidable Pro PDF Extended</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
	<body>
        <?php	

        foreach($entry_ids as $entry_id) {

			$form_data = FPPDF_Common::entry_data($entry_id);

			// $fields = FPPDF_Common::get_form_fields($form_id, $entry_id);						
			
			// $form_data = FPPDF_Entry::show_entry(array(
            //     'id' => $entry_id, 
			// 	'fields' => $fields, 
            //     'user_info' => false,
			// 	'type' => 'array'		
            // ));
            
			// /*
			//  * Add &data=1 when viewing the PDF via the admin area to view the $form_data array
			//  */
			// PDF_Common::view_data($form_data);				
						
			/* get all the form values */
			/*$date_created		= $form_data['date_created'];
			
			$first_name 		= $form_data['1.Name']['first'];
			$last_name 			= $form_data['1.Name']['last'];*/			
		
			/* format the template */						
			?>                   
   <p>This should print on an A4 (portrait) sheet</p>
   
   <pagebreak sheet-size="A4-L" />
   <p>This page appears after the A4 portrait sheet and should print on an A4 (landscape) sheet</p>
   	<h1>mPDF Page Sizes</h1>
	<h3>Changing page (sheet) sizes within the document</h3>   
   <pagebreak sheet-size="A5-L" />
   
   <p>This should print on an A5 (landscape) sheet</p>
   	<h1>mPDF Page Sizes</h1>
	<h3>Changing page (sheet) sizes within the document</h3>   

            <?php
        }

        ?>
	</body>
</html>