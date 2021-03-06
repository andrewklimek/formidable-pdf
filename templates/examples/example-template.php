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

 $stylesheet_location = (file_exists(FP_PDF_TEMPLATE_LOCATION.'template.css')) ? FP_PDF_TEMPLATE_URL_LOCATION.'template.css' : FP_PDF_PLUGIN_URL .'styles/template.css' ;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    
    <link rel='stylesheet' href='<?php echo $stylesheet_location; ?>' type='text/css' />
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
			// FPPDF_Common::view_data($form_data);				
						
			/* get all the form values */
			$date_created		= $form_data['date_created'];						
			
			/* format the template */						
			?>                                 	
           
           
           <div class="body_copy">
           
           <img src="<?php echo FP_PDF_PLUGIN_DIR; ?>/images/formidablepro-logo.jpg" width="311" height="66"  /> 
		   
		   	<p class="date"><?php echo $date_created; ?></p>
            
            <p class="whom_concern_intro">Dear User,</p>
			
            <p>Anything you put here will output to the PDF...</p>			
            
            
            <br /><br />
            
            <p class="signature">
                Jake Jackson<br />
                <img src="<?php echo FP_PDF_PLUGIN_DIR ?>/images/signature.png" alt="Signature" width="100" height="60" /><br />
                Developer, Formidable Pro PDF Extended<br />
                <a href="http://www.formidablepropdfextended.com">www.formidablepropdfextended.com</a>
            </p>
           
           </div>

         
            <?php
        }

        ?>
	</body>
</html>