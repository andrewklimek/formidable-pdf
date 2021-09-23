<?php

/**
 * Plugin: Formidable Pro PDF Extended
 * File: install-update-manager.php
 * 
 * This file handles the installation and update code that ensures the plugin will be supported.
 */

/**
 * Check to see if Formidable Pro version is supported
 */
 
class FPPDF_InstallUpdater
{
	private static $directory               = FP_PDF_PLUGIN_DIR;
	private static $template_directory      = FP_PDF_TEMPLATE_LOCATION;
	private static $template_save_directory = FP_PDF_SAVE_LOCATION;
	private static $template_font_directory = FP_PDF_FONT_LOCATION;
	
	
	public static function install() {
		if(strlen(get_option('fp_pdf_extended_installed')) == 0)
		{			
			self::pdf_extended_activate();
		}
	}

	/*
	 * Check what the filesystem type is and modify the file paths
	 * appropriately.
	 */
	 public static function update_file_paths()
	 {
		global $wp_filesystem;

		/*
		 * Assume FTP is rooted to the Wordpress install
		 */ 			 	
		self::$directory               = self::get_base_directory(FP_PDF_PLUGIN_DIR);
		self::$template_directory      = self::get_base_directory(FP_PDF_TEMPLATE_LOCATION);
		self::$template_save_directory = self::get_base_directory(FP_PDF_SAVE_LOCATION);
		self::$template_font_directory = self::get_base_directory(FP_PDF_FONT_LOCATION);					 	 					 
		 
	 }
	
	/**
	 * Install everything required
	 */
	public static function pdf_extended_activate() {
	    /*
		 * Initialise the Wordpress Filesystem API
		 */		
		ob_start();
		if(FPPDF_Common::initialise_WP_filesystem_API(array('FP_PDF_DEPLOY'), 'fp-pdf-extended-filesystem') === false)
		{
		    $return = ob_get_contents();
		    ob_end_clean();			
			echo json_encode(array('form' => $return));
			exit;
		}
		ob_end_clean();					

		/*
		 * If we got here we should have $wp_filesystem available
		 */
		global $wp_filesystem;	
		
		/*
		 * We need to set up some filesystem compatibility checkes to work with the different server file management types
		 * Most notably is the FTP options, but SSH may be effected too
		 */
		self::update_file_paths();



		/**
		 * If FP_PDF_TEMPLATE_LOCATION already exists then we will remove the old template files so we can redeploy the new ones
		 */

		 if(FP_PDF_DEPLOY === true && $wp_filesystem->exists(self::$template_directory))
		 {
			/* read all file names into array and unlink from active theme template folder */
			foreach ( glob( FP_PDF_PLUGIN_DIR . 'templates/*.php') as $file ) {
				 	$path_parts = pathinfo($file);					
						if($wp_filesystem->exists(self::$template_directory.$path_parts['basename']))
						{
							$wp_filesystem->delete(self::$template_directory.$path_parts['basename']);
						}
			 }			
			if($wp_filesystem->exists(self::$template_directory.'template.css')) { $wp_filesystem->delete(self::$template_directory.'template.css'); }
		 }
		 

		/* create new directory in active themes folder*/	
		if(!$wp_filesystem->is_dir(self::$template_directory))
		{
			if($wp_filesystem->mkdir(self::$template_directory) === false)
			{
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}
		}
	
		if(!$wp_filesystem->is_dir(self::$template_save_directory))
		{
			/* create new directory in active themes folder*/	
			if($wp_filesystem->mkdir(self::$template_save_directory) === false)
			{
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}
		}
		
		if(!$wp_filesystem->is_dir(self::$template_font_directory))
		{
			/* create new directory in active themes folder*/	
			if($wp_filesystem->mkdir(self::$template_font_directory) === false)
			{
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}
		}	
		
		/*
		 * Copy entire template folder over to FP_PDF_TEMPLATE_LOCATION
		 */
		 self::pdf_extended_copy_directory( self::$directory . 'templates', self::$template_directory, false );

		if(!$wp_filesystem->exists(self::$template_directory .'configuration.php'))
		{ 
			/* copy template files to new directory */
			if(!$wp_filesystem->copy(self::$directory .'configuration.php', self::$template_directory.'configuration.php'))
			{ 
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}
		}
		
		if(!$wp_filesystem->exists(self::$template_directory.'template.css'))
		{ 
			/* copy template files to new directory */
			if(!$wp_filesystem->copy(self::$directory .'styles/template.css', self::$template_directory.'template.css'))
			{ 
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}
		}	

		if(!$wp_filesystem->exists(self::$template_save_directory.'.htaccess'))
		{		
			if(!$wp_filesystem->put_contents(self::$template_save_directory.'.htaccess', 'deny from all'))
			{
				add_action('fppdfe_notices', array("FPPDF_InstallUpdater", "fp_pdf_template_dir_err")); 	
				return 'fail';
			}	
		}	

		if(self::install_fonts(self::$directory, self::$template_directory, self::$template_font_directory) !== true)
		{
			return 'fail';
		}				 
		
		/* 
		 * Update system to ensure everything is installed correctly.
		 */

		update_option('fp_pdf_extended_installed', 'installed');			
		update_option('fp_pdf_extended_deploy', 'yes');
		delete_option('fppdfe_switch_theme');
		
		return true;	
	}
	
	/**
	 * Formidable Pro hasn't been installed so throw error.
	 * We make sure the user hasn't already dismissed the error
	 */
	public function fp_pdf_not_installed()
	{
		echo '<div class="fppdfe_message error"><p>';
		echo 'You need to install <a href="http://formidablepro.com/index.php?plugin=wafp&controller=links&action=redirect&l=formidable-pro&a=blue liquid designs" target="ejejcsingle">Formidable Pro</a> to use the Formidable Pro PDF Extended Plugin.';
		echo '</p></div>';
	}
	
	/**
	 * PDF Extended has been updated but the new template files haven't been deployed yet
	 */
	public function fp_pdf_not_deployed()
	{		
		if( (FP_PDF_DEPLOY === true) && !filter_input(INPUT_POST,'update') )
		{
			if(filter_input(INPUT_GET,"page") == 'fp_settings' && filter_input(INPUT_GET,'addon') == 'PDF')
			{
				echo '<div class="fppdfe_message error"><p>';
				echo 'You\'ve updated Formidable Pro PDF Extended but are yet to re-initialise the plugin. After initialising, please review the latest updates to ensure your custom templates remain compatible with the latest version.';
				echo '</p></div>';
				
			}
			else
			{
				echo '<div class="fppdfe_message error"><p>';
				echo 'You\'ve updated Formidable Pro PDF Extended but are yet to re-initialise the plugin. Please go to the <a href="'.FP_PDF_SETTINGS_URL.'">plugin\'s settings page</a> to initialise.';
				echo '</p></div>';
			}
		}
	}
	
	/**
	 * The Formidable Pro version isn't compatible. Prompt user to upgrade
	 */
	public function fp_pdf_not_supported()
	{
			echo '<div class="fppdfe_message error"><p>';
			echo 'Formidable Pro PDF Extended only works with Formidable Pro version 2.0 and higher. Please upgrade your copy of Formidable Pro to use this plugin.';
			echo '</p></div>';	
	}
								
	
	/**
	 * Cannot create new template folder in active theme directory
	 */
	public function fp_pdf_template_dir_err()
	{
			echo '<div class="fppdfe_message error"><p>';
			echo 'We could not create a template folder in your active theme\'s directory. Please make your theme directory writable by your web server and initialise again.';
			echo '</p></div>';
			
	}
	
	/**
	 * Cannot remove old default template files
	 */
	public function fp_pdf_deployment_unlink_error()
	{
			echo '<div class="fppdfe_message error"><p>';
			echo 'We could not remove the default template files from the Formidable Pro PDF Extended folder in your active theme\'s directory. Please manually remove all files starting with \'default-\', the template.css file and then initialise again.';
			echo '</p></div>';
	
	}		
	
	/**
	 * Cannot create new template folder in active theme directory
	 */
	public function fp_pdf_template_move_err()
	{
			echo '<div class="fppdfe_message error"><p>';
			echo 'We could not copy the contents of '.FP_PDF_PLUGIN_DIR.'templates/ to your newly-created FORMIDABLE_PDF_TEMPLATES folder. Please make this directory writable by your web server and initialise again.';
			echo '</p></div>';
	
	}
	
	
	/*
	 * Allows you to copy entire folder structures to new location
	 */
	
	public static function pdf_extended_copy_directory( $source, $destination, $copy_base = true, $delete_destination = false ) {
		global $wp_filesystem;		
		
		if ( $wp_filesystem->is_dir( $source ) ) 
		{			
			if($delete_destination === true)
			{
				/*
				 * To ensure everything stays in sync we will remove the destination file structure
				 */
				 $wp_filesystem->delete($destination, true);
			}
			 
			if($copy_base === true)
			{
				$wp_filesystem->mkdir( $destination );
			}
			$directory = $wp_filesystem->dirlist( $source );

			foreach($directory as $name => $data)
			{
							
				$PathDir = $source . '/' . $name; 
				
				if ( $wp_filesystem->is_dir( $PathDir ) ) 
				{
					self::pdf_extended_copy_directory( $PathDir, $destination . '/' . $name );
					continue;
				}
				$wp_filesystem->copy( $PathDir, $destination . '/' . $name );
			}

		}
		else 
		{
			$wp_filesystem->copy( $source, $destination );
		}	
	}
	
	/*
	 * Merge the path array back together from the matched key
	 */	
	private static function merge_path($file_path, $key)
	{
		return '/' .  implode('/', array_slice($file_path, $key)) . '/';
	}
	
	/*
	 * Get the base directory for the current filemanagement type
	 * In this case it is FTP but may be SSH
	 */
	 private static function get_base_directory($path = '')
	 {
		global $wp_filesystem;
		return str_replace(ABSPATH, $wp_filesystem->abspath(), $path);			 		
	 }	

}
