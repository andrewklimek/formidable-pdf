<?php
/*
Plugin Name: Formidable PDF
Plugin URI: https://github.com/andrewklimek/formidable-pdf
Description: Formidable PDF allows you to save/view/download a PDF from the front- and back-end, and automate PDF creation on form submission.
Version: 0.1
Author: Andrew J Klimek
Author URI: https://github.com/andrewklimek/
Text Domain: ffpdf

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/


/*
* Define our constants
*/
if(!defined('FP_PDF_EXTENDED_VERSION')) define('FP_PDF_EXTENDED_VERSION', '1.7.0');
if(!defined('FP_PDF_EXTENDED_SUPPORTED_VERSION')) define('FP_PDF_EXTENDED_SUPPORTED_VERSION', '2.0' );
if(!defined('FP_PDF_EXTENDED_WP_SUPPORTED_VERSION')) define('FP_PDF_EXTENDED_WP_SUPPORTED_VERSION', '3.6');

if(!defined('FP_PDF_PLUGIN_DIR')) define('FP_PDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
if(!defined('FP_PDF_PLUGIN_URL')) define('FP_PDF_PLUGIN_URL', plugin_dir_url( __FILE__ ));
if(!defined('FP_PDF_SETTINGS_URL')) define("FP_PDF_SETTINGS_URL", site_url() .'/wp-admin/admin.php?page=formidable-settings#PDF_settings');
if(!defined('FP_PDF_EXTENDED_PLUGIN_BASENAME')) define('FP_PDF_EXTENDED_PLUGIN_BASENAME', plugin_basename(__FILE__));

// old location
// if(!defined('FP_PDF_SAVE_FOLDER')) define('FP_PDF_SAVE_FOLDER', 'FORMIDABLE_PDF_TEMPLATES');
// if(!defined('FP_PDF_TEMPLATE_LOCATION')) define('FP_PDF_TEMPLATE_LOCATION', get_stylesheet_directory().'/'.FP_PDF_SAVE_FOLDER.'/');// wp_get_upload_dir()
// if(!defined('FP_PDF_SAVE_LOCATION')) define('FP_PDF_SAVE_LOCATION', get_stylesheet_directory().'/'.FP_PDF_SAVE_FOLDER.'/output/');
// if(!defined('FP_PDF_FONT_LOCATION')) define('FP_PDF_FONT_LOCATION', get_stylesheet_directory().'/'.FP_PDF_SAVE_FOLDER.'/fonts/');
// if(!defined('FP_PDF_TEMPLATE_URL_LOCATION')) define('FP_PDF_TEMPLATE_URL_LOCATION', get_stylesheet_directory_uri().'/'. FP_PDF_SAVE_FOLDER .'/');

// new location
$upload_dir = wp_get_upload_dir();
if(!defined('FP_PDF_SAVE_FOLDER')) define('FP_PDF_SAVE_FOLDER', 'formidable-pdf');
if(!defined('FP_PDF_TEMPLATE_LOCATION')) define('FP_PDF_TEMPLATE_LOCATION', $upload_dir['basedir'].'/'.FP_PDF_SAVE_FOLDER.'/');// wp_get_upload_dir()
if(!defined('FP_PDF_SAVE_LOCATION')) define('FP_PDF_SAVE_LOCATION', FP_PDF_TEMPLATE_LOCATION .'output/');
if(!defined('FP_PDF_FONT_LOCATION')) define('FP_PDF_FONT_LOCATION', FP_PDF_TEMPLATE_LOCATION .'fonts/');
if(!defined('FP_PDF_TEMPLATE_URL_LOCATION')) define('FP_PDF_TEMPLATE_URL_LOCATION', $upload_dir['baseurl'] .'/'. FP_PDF_SAVE_FOLDER .'/');

/*
* Do we need to deploy template files this edition? If yes set to true.
*/
if(!defined('FP_PDF_DEPLOY')) define('FP_PDF_DEPLOY', false);

/*
* Include the core files
*/
include FP_PDF_PLUGIN_DIR . 'pdf-common.php';
include FP_PDF_PLUGIN_DIR . 'pdf-configuration-indexer.php';
include FP_PDF_PLUGIN_DIR . 'installation-update-manager.php';
include FP_PDF_PLUGIN_DIR . 'pdf-render.php';
include FP_PDF_PLUGIN_DIR . 'pdf-settings.php';
// include FP_PDF_PLUGIN_DIR . 'pdf-entry-detail.php';
include FP_PDF_PLUGIN_DIR . 'pdf-custom-display.php';

/*
* Initiate the class after Formidable Pro has been loaded using the init hook.
*/
add_action( 'init', 'FPPDF_Core::pdf_init' );
add_action( 'admin_init', 'FPPDF_Core::admin_init' );

/*
 * Add settings page AJAX listeners
 */
add_action( 'wp_ajax_fppdfe_initialise', 'FPPDF_Settings::ajax_deploy' );


class FPPDF_Core extends FPPDFGenerator
{
	private $render;


	/*
	 * Run public initialisation function
	 */
	public static function pdf_init()
	{
		 /*
		  * Check if Formidable Pro is installed before we continue
		  * Include common functions for test
		  */
		  if ( FPPDF_Common::is_formidable_supported( FP_PDF_EXTENDED_SUPPORTED_VERSION ) === false ) {
			 add_action( 'after_plugin_row_' . FP_PDF_EXTENDED_PLUGIN_BASENAME, 'FPPDF_Common::display_compatibility_error' );
			 return;
		  } else if ( FPPDF_Common::is_wordpress_supported( FP_PDF_EXTENDED_WP_SUPPORTED_VERSION ) === false ) {
			 add_action( 'after_plugin_row_' . FP_PDF_EXTENDED_PLUGIN_BASENAME, 'FPPDF_Common::display_wp_compatibility_error' );
			 return;
		  }
      //else {
			// add_action( 'after_plugin_row_' . FP_PDF_EXTENDED_PLUGIN_BASENAME, 'FPPDF_Core::add_documentation_byline' );
		  //}


	   /*
	    * As it's called inside a undefined function we need to globalise the $fppdf namespace
		*/
	    global $fppdf;
		$fppdf = new FPPDF_Core();

   }

   /*
    * Only run in the admin area
	*/
   public static function admin_init()
   {
	   wp_enqueue_script('fppdfe_admin', FP_PDF_PLUGIN_URL . 'js/admin.js', array('jquery'), '1', true);
	   wp_localize_script( 'fppdfe_admin', 'FPPDFE', array(
	   		'nonce' => wp_create_nonce( 'fppdfe_nonce' )
	   ) );

	   wp_enqueue_style('fppdfe_admin', FP_PDF_PLUGIN_URL . 'css/admin.css');
   }

	public function __construct()
	{
		/*
		 * Set up the PDF configuration and indexer
		 * Accessed through $this->configuration and $this->index.
		 */
		parent::__construct();

		/*
		 * Add our installation/file handling hooks
		 */
		add_action( 'admin_init', 'FPPDF_Core::gfe_admin_init', 9 );
		register_activation_hook( __FILE__, 'FPPDF_InstallUpdater::install' );


		/*
		 * Add our main hooks if the system is installed correctly
		 */
		 if(FPPDF_Common::is_fully_installed() === false)
		 {
			return;
		 }

		add_action('frm_edit_entry_sidebar', array($this, 'detail_pdf_link'), 10, 1);
		add_action('frm_show_entry_sidebar', array($this, 'detail_pdf_link'), 10, 1);

		add_action('frm_row_actions', array($this, 'pdf_link'), 10, 2);


		add_action('wp', array($this, 'process_exterior_pages'));

		/*
		 * Register render class
		 */
		$this->render = new FPPDFRender();

		/*
		 * Run PDF generate / email code based on version
		 */
		add_filter( 'frm_notification_attachment', 'FPPDF_Core::create_and_attach_pdf', 10, 3 );

	}

	/**
	 * Check to see if Formidable Pro is actually installed
	 */
	public static function gfe_admin_init() {

		/*
		 * Check if database plugin version matches current plugin version and updates if needed
		 */
		if(get_option('fp_pdf_extended_version') != FP_PDF_EXTENDED_VERSION)
		{
			update_option('fp_pdf_extended_version', FP_PDF_EXTENDED_VERSION);
			
			if( FP_PDF_EXTENDED_VERSION < 1.7 )
			{
			    update_option('fp_pdf_extended_deploy', 'no');
    			/* redirect */
    			Header('Location: '.FP_PDF_SETTINGS_URL);
    			exit;
			}
		}

		/*
		 * Check if GF PDF Extended is correctly installed. If not we'll run the installer.
		 */
		$theme_switch = get_option('gfpdfe_switch_theme');

		if( ( ( get_option('fp_pdf_extended_installed') != 'installed' ) || ( ! is_dir(FP_PDF_TEMPLATE_LOCATION)) ) && ( ! filter_input(INPUT_POST,'upgrade') && ( empty( $theme_switch['old'] ) ) ) ) {
			/*
			 * Prompt user to initialise plugin
			 */
			 add_action( 'admin_notices',  'FPPDF_InstallUpdater::FP_PDF_not_deployed_fresh' );
		}

    	 FPPDF_Settings::settings_page();

	}


	function detail_pdf_link($record) {
		/*
		 * Get the template name
		 * Class: PDFGenerator
		 * File: pdf-configuration-indexer.php
		 */
		 global $fppdf;

		 $form_id = $record->form_id;
		 $entry_id = $record->id;

		 $templates = $this->get_template($form_id, true);

		/* exit early if templates not found */
		if($templates === false)
		{
			return;
		}			 

		?>
        <div class="postbox fppdfe">
        	<h3 class="hndle"><span>PDFs</span></h3>
        	<div class="inside">
        <?php
		if(is_array($templates))
		{
			?>


                        	<?php foreach($templates as $id => $val):

							$name = $fppdf->get_pdf_name($id, $form_id, $entry_id);
							$aid = (int) $id + 1;
							?>
                            <div class="detailed_pdf">
								<span><?php
									echo $name;
									$url = home_url() .'/?pdf=1&aid='. $aid .'&fid=' . $form_id . '&lid=' . $entry_id . '&template=' . $val['template'];
								?></span>
                                <a href="<?php echo $url; ?>" target="_blank" class="button">View</a>
				 				<a href="<?php echo $url.'&download=1'; ?>" target="_blank" class="button">Download</a></div>

                            <?php endforeach; ?>


            <?php
		}
		elseif($templates !== false)
		{
			$url = home_url() .'/?pdf=1&fid=' . $form_id . '&lid=' . $entry_id . '&template=' . $templates;

			?>
			PDF: <a href="<?php echo $url; ?>" target="_blank" class="button">View</a>
				 <a href="<?php echo $url.'&download=1'; ?>" onclick="var url='<?php echo $url.'&download=1'; ?>'; window.open (url); return false;" class="button">Download</a>
			<?php
		}
		?>
        	</div>
        </div>
        <?php
	}

	function pdf_link($actions, $entry) {
		/*
		 * Get the template name
		 * Class: PDFGenerator
		 * File: pdf-configuration-indexer.php
		 */
		 $form_id = $entry->form_id;
		 $entry_id = $entry->id;

		 global $fppdf;

		$templates = $this->get_template($form_id, true);

		/* exit early if templates not found */
		if($templates === false)
		{
			return $actions;
		}		

		if(is_array($templates))
		{
			ob_start();
			?>
                <span class="fp_has_submenu">
                   <a target="" href="#" title="View PDF configured for this form" onclick="return false" class="">View PDFs</a>

                    <div class="fp_submenu">
                        <ul>
                        	<?php foreach($templates as $id => $t):
							 $name = $fppdf->get_pdf_name($id, $form_id, $entry_id);
							 $aid = (int) $id + 1;
							?>
                            <li class="">
                            	<a target="_blank" href="<?php echo home_url(); ?>/?pdf=1&aid=<?php echo $aid; ?>&fid=<?php echo $form_id; ?>&lid=<?php echo $entry_id; ?>&template=<?php echo $t['template']; ?>"><?php echo $name; ?></a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </span>

                <?php
			$actions['pdf'] = ob_get_contents();
			ob_end_clean();

		}
		else
		{

			ob_start();
			?>
			<a target="_blank" href="<?php echo home_url(); ?>/?pdf=1&fid=<?php echo $form_id; ?>&lid=<?php echo $entry_id; ?>&template=<?php echo $templates; ?>"> View PDF</a>
			<?php
			$actions['pdf'] = ob_get_contents();
			ob_end_clean();
		}

		return $actions;
	}

	/*
	 * Handle incoming routes
	 * Look for $_GET['pdf'] variable, authenticate user and generate/display PDF
	 */
	function process_exterior_pages() {
	  global $wpdb;

	  /*
	   * As far as I can tell, all 3 $_GET variables are needed to proceed, not just 'pdf
	   */
	  if ( empty($_GET['pdf']) || empty($_GET['fid']) || empty($_GET['lid']) )
	  {
		return;
	  }

		$form_id = (int) $_GET['fid'];
		$entry_id = (int) $_GET['lid'];

		/*
		 * Get the template name
		 * Class: PDFGenerator
		 * File: pdf-configuration-indexer.php
		 */
		$template = $this->get_template($form_id);				
		
		/*
		 * Before setting up PDF options we will check if a configuration is found
		 * If not, we will set up defaults defined in configuration.php
		 */		
		$index = $this->check_configuration($form_id, $template);	

		/*
		 * Run if user is not logged in
		 */
		 if(!is_user_logged_in() && empty($_GET['nonce']))
		 {
			 if( $this->configuration[$index]['access'] !== 'all')
			 {
				auth_redirect();
			 }
		 }
		 elseif(isset($_GET['nonce']))
		 {
			/*
			 * Using the custom display, which needs PDFs to be public
			 * If nonce matches then we will display the results
			 */
			 $user_template = $_GET['template'];

			 $nonce = $_GET['nonce'];

			 if ( ! wp_verify_nonce( $nonce,  'fppdf_' . $form_id . $entry_id. $user_template ) ) {
				 /*
				  * Failed
				  */
				  exit('Access to PDF Denied');
			 }
		 }
		 else
		 {
			  /*
			   * Ensure logged in users have the correct privilages
			   */
			  if(!current_user_can('frm_view_entries'))
			  {
				  /*
				   * User doesn't have the correct access privilages
				   * Let's check if they are assigned to the form
				   */
					$user_logged_entries = $wpdb->get_var("SELECT count(*) FROM `{$wpdb->prefix}frm_items` WHERE form_id={$form_id} AND id={$entry_id} AND user_id=". get_current_user_id() );

					/*
					 * Failed again.
					 * One last check against the IP<---removed this part
					 * If it matches the record then we will show the PDF
					 * 
					 * TODO Might want to rewrite this whole block to check access === all first?
					 */
					if($user_logged_entries == 0)
					{
						if( $this->configuration[$index]['access'] !== 'all')
						{
							/*
							 * Don't show the PDF
							 */
							 return;
						}

					}
			  }

			  /*
			   * Because this user is logged in with the correct access
			   * we will allow a template to be shown by setting the template variable
			   */

			   if( (isset($_GET['template'])) && ($template != $_GET['template']) && (substr($_GET['template'], -4) == '.php') )
			   {
					$template = $_GET['template'];
			   }

		 }



		$pdf_arguments = $this->generate_pdf_parameters($index, $form_id, $entry_id, $template);

		/*
		 * Add output to arguments
		 */
		$output = 'view';
		if(isset($_GET['download']))
		{
			$output = 'download';
		}

		$pdf_arguments['output'] = $output;

		$this->render->PDF_Generator($form_id, $entry_id, $pdf_arguments);

	  exit();
	}

	public static function create_and_attach_pdf( $attachments, $form, $args ) {
		$notification = self::do_notification($attachments, $form, $args);
    	return $notification;
	}

	/*
	 * Handles the Formidable Pro notification logic
	 */
	public static function do_notification($attachments, $form, $args)
	{
		/*
		 * Allow the template/function access to these variables
		 */
		global $fppdf, $form_id, $entry_id;

		$notification_name = (isset($args['email_key'])) ? $args['email_key'] : '';

		/*
		 * Set data used to determine if PDF needs to be created and attached to notification
		 * Don't change anything here.
		 */

		$form_id           = $form->id;
		$entry_id           = $args['entry']->id;
		$folder_id 		   = $form_id.$entry_id.'/';

		/*
		 * Before setting up PDF options we will check if a configuration is found
		 * If not, we will set up defaults defined in configuration.php
		 */
		 $fppdf->check_configuration($form_id);

		/*
		 * Check if form is in configuration
		 */
		if(!$config = $fppdf->get_config($form_id))
		 {
			 return $notification;
		 }

		/*
		 * To have our configuration indexes so loop through the PDF template configuration
		 * and generate and attach PDF files.
		 */

		 foreach($config as $index)
		 {
				$template = (isset($fppdf->configuration[$index]['template'])) ? $fppdf->configuration[$index]['template'] : '';

				/* Get notifications user wants PDF attached to and check if the correct notifications hook is running */
				$notifications = $fppdf->get_form_notifications( $form, $index, $args );

				/*
				 * Premium plugin filter
				 * Allows manual override of the notification
				 * Allows the multi-report plugin to automate PDFs based on weekly/fortnightly/monthly basis
				 * Only allow boolean to be returned
				 */
				 $notification_override = (bool) apply_filters('gfpdfe_notification_override', false, $notification_name, $attachments, $form, $args, $fppdf);

				if ($fppdf->check_notification($notification_name, $notifications) || $notification_override === true)
				{
					$pdf_arguments = $fppdf->generate_pdf_parameters($index, $form_id, $entry_id, $template);

					/* generate and save default PDF */
					$filename = $fppdf->render->PDF_Generator($form_id, $entry_id, $pdf_arguments);

					$attachments[] = $filename;
				}

		 }
		 return $attachments;
	}

	/*
	 * Check if name in notification_name String/Array matches value in $notifcations array
	 */
	public function check_notification($notification_name, $notifications)
	{
		if(is_array($notification_name))
		{
			foreach($notification_name as $name)
			{
				if(in_array($name, $notifications))
				{
					return true;
				}
			}
		}
		else
		{
			if(in_array($notification_name, $notifications))
			{
				return true;
			}
		}

		return false;
	}

	public static function get_form_notifications( $form, $index, $args ) {
		global $fppdf;

		/*
		 * Check if notification field even exists
		 */
		 if(!isset($fppdf->configuration[$index]['notifications']))
		 {
			return array();
		 }

		/*
		 * Get this notification id
		 */
		$this_notification = $args['email_key'];

		$new_notifications = array();

		/*
		 * If notifications is true the user wants to attach the PDF to all notifications
		 */
		if ( $fppdf->configuration[ $index ]['notifications'] === true ) {
			$new_notifications[] = $this_notification;
		} else {
			foreach ( (array) $fppdf->configuration[ $index ]['notifications'] as $name ) {
				if ( $name == $this_notification ) {
					$new_notifications[] = $name;
				}
			}
		}

		return $new_notifications;
	}

	/*
	 * Generate PDF parameters to pass to the PDF renderer
	 * $index Integer The configuration index number
	 */
	private function generate_pdf_parameters($index, $form_id, $entry_id, $template = '')
	{

		$pdf_name        = !empty($this->configuration[$index]['filename']) ? FPPDF_Common::validate_pdf_name($this->configuration[$index]['filename'], $form_id, $entry_id) : FPPDF_Common::get_pdf_filename($form_id, $entry_id);
		$template        = !empty($template) ? $template : $this->get_template($index);
		
		$pdf_size        = (isset($this->configuration[$index]['pdf_size']) && (is_array($this->configuration[$index]['pdf_size']) || strlen($this->configuration[$index]['pdf_size']) > 0)) ? $this->configuration[$index]['pdf_size'] : self::$default['pdf_size'];
		$orientation     = !empty($this->configuration[$index]['orientation']) ? $this->configuration[$index]['orientation'] : self::$default['orientation'];
		$security        = !empty($this->configuration[$index]['security']) ? $this->configuration[$index]['security'] : self::$default['security'];
		$premium         = !empty($this->configuration[$index]['premium']) ? true: false;
		
		
		/*
		* Validate privileges
		* If blank and security is true then set privileges to all
		*/
		$privileges      = (isset($this->configuration[$index]['pdf_privileges'])) ? $this->validate_privileges($this->configuration[$index]['pdf_privileges']) : $this->validate_privileges('');
		
		$pdf_password    = (isset($this->configuration[$index]['pdf_password'])) ? FPPDF_Common::do_mergetags($this->configuration[$index]['pdf_password'], $form_id, $entry_id) : '';
		$master_password = (isset($this->configuration[$index]['pdf_master_password'])) ? FPPDF_Common::do_mergetags($this->configuration[$index]['pdf_master_password'], $form_id, $entry_id) : '';
		$rtl             = (isset($this->configuration[$index]['rtl'])) ? $this->configuration[$index]['rtl'] : false;
		
		/* added in v3.4.0 */
		$pdfa1b          = !empty($this->configuration[$index]['pdfa1b']) ? true : false;		
		
		/* added in v3.4.0 */
		$pdfx1a          = !empty($this->configuration[$index]['pdfx1a']) ? true : false;				


		/*
		 * Run the options through filters
		 */
		$pdf_name        = apply_filters('fppdf_pdf_name', $pdf_name, $form_id, $entry_id);
		$template        = apply_filters('fppdf_template', $template, $form_id, $entry_id);
		$orientation     = apply_filters('fppdf_orientation', $orientation, $form_id, $entry_id);
		$security        = apply_filters('fppdf_security', $security, $form_id, $entry_id);
		$privileges      = apply_filters('fppdf_privilages', $privileges, $form_id, $entry_id);
		$pdf_password    = apply_filters('fppdf_password', $pdf_password, $form_id, $entry_id);
		$master_password = apply_filters('fppdf_master_password', $master_password, $form_id, $entry_id);
		$rtl             = apply_filters('fppdf_rtl', $rtl, $form_id, $entry_id);

		$pdf_arguments = array(
			'pdfname'             => $pdf_name,
			'template'            => $template,
			'pdf_size'            => $pdf_size, /* set to one of the following, or array - in millimeters */
			'orientation'         => $orientation, /* landscape or portrait */
			'security'            => $security, /* true or false. if true the security settings below will be applied. Default false. */
			'pdf_password'        => $pdf_password, /* set a password to view the PDF */
			'pdf_privileges'      => $privileges, /* assign user privliages to the PDF */
			'pdf_master_password' => $master_password, /* set a master password to the PDF can't be modified without it */
			'rtl'                 => $rtl,
			'premium'             => $premium,
			'pdfa1b'              => $pdfa1b,			
			'pdfx1a'              => $pdfx1a, 				
		);

		return $pdf_arguments;
	}

	/*
	 * Checks if a configuration index is found
	 * If not, we will set up defaults defined in configuration.php if they exist
	 */
	public static function check_configuration($form_id, $template = '')
	{
		// error_log("pdf.php 0");

		global $fp_pdf_default_configuration, $fppdf;

		/*
		 * Check if configuration index already defined		 
		 */
		if(empty($fppdf->index[$form_id]))
		{
// error_log("pdf.php 1");

			/*
			 * Check if a default configuration is defined
			 */			
			if(is_array($fp_pdf_default_configuration) && sizeof($fp_pdf_default_configuration) > 0 && FPPDF_SET_DEFAULT_TEMPLATE === true)
			{
// error_log("pdf.php 2");
				/*
				 * Add form_id to the defualt configuration				 
				 */
				 $default_configuration = array_merge($fp_pdf_default_configuration, array('form_id' => $form_id));
				 
				/*
				 * There is no configuration index and there is a default index so add the defaults to this form's configuration
				 */
				 $fppdf->configuration[] = $default_configuration;
				 
				 /* get the id of the newly added configuration */
				 end($fppdf->configuration);
				 $index = key($fppdf->configuration);
				 
				 /* now add to the index */
				 $fppdf->assign_index($form_id, $index);				  
				 
			}
		}
		else
		{
// error_log("pdf.php 3");
			/* if there are multiple indexes for a form we will look for the one with the matching template */
			if(sizeof($fppdf->index[$form_id]) > 1 && $template )
			{
// error_log("pdf.php 4");
				/*
				 * Check if $_GET['aid'] present which will give us the index when multi templates assigned
				 */
				 if( !empty($_GET['aid']) )
				 {
					$aid = (int) $_GET['aid'] - 1;
					if(isset($fppdf->index[$form_id][$aid]))
					{
						// error_log("pdf.php 5");
						return $fppdf->index[$form_id][$aid];
					}					
				 }				
// error_log("pdf.php 6");
				/*
				 * If aid not present we'll match against the template
				 * This is usually the case when using a user-generated link
				 */
				$index = false;
				foreach($fppdf->index[$form_id] as $i)
				{
					if(isset($fppdf->configuration[$i]['template']) && $fppdf->configuration[$i]['template'] == $template)
					{
						// error_log("pdf.php 7");
						/* matched by template */
						return $i;
					}
				}				
			}
			// error_log("pdf.php 8");
			// poo($fppdf->index[$form_id], '$fppdf->index[$form_id]');
			/* there aren't multiples so just return first node */
			return $fppdf->index[$form_id][0];	
		}
		return $index;	
	}		
}