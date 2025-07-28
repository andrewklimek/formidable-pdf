<?php


class FPPDFRender
{
	/**
	 * Outputs a PDF entry from a Formidable Form
	 * var $form_id integer: The form id
	 * var $entry_id integer: The entry id
	 * var $output string: either view, save or download.  save will save a copy of the PDF to the server using the FP_PDF_SAVE_LOCATION constant
	 * var $return boolean: if set to true it will return the path of the saved PDF
	 * var $template string: if you want to use multiple PDF templates - name of the template file
	 * var $pdfname string: allows you to pass a custom PDF name to the generator e.g. 'Application Form.pdf' (ensure .pdf is appended to the filename)
	 * var $fpdf boolean: custom hook to allow the FPDF engine to generate PDFs instead of DOMPDF. Premium Paid Feature.
	 */
	public function PDF_Generator($form_id, $entry_id, $arguments = array())
	{
		/*
		 * Because we merged the create and attach functions we need a measure to only run this function once per session per entry id.
		 */
		static $pdf_creator = array();

		/*
		 * Set user-variable to output HTML instead of PDF
		 */
		 $html = (isset($_GET['html'])) ? (int) $_GET['html'] : 0;

		/*
		 * Join the form and entry IDs together to get the real ID
		 */
		$id = $form_id . $entry_id;

		/*
		 * PDF_Generator was becoming too cluttered so store all the variables in an array
		 */
		 $filename = $arguments['pdfname'];
		 $template = $arguments['template'];
		 $output = (isset($arguments['output']) && strlen($arguments['output']) > 0) ? $arguments['output'] : 'save';

		/*
		 * Check if the PDF exists and if this function has already run this season
		 */

		if(in_array($entry_id, $pdf_creator) && file_exists(FP_PDF_SAVE_LOCATION.$id.'/'. $filename))
		{
			/*
			 * Don't generate a new PDF, use the existing one
			 */
			return FP_PDF_SAVE_LOCATION.$id.'/'. $filename;
		}

		/*
		 * Add entry to PDF creation tracker
		 */
		$pdf_creator[] = $entry_id;

		/*
		 * Add filter before we load the template file so we can stop the main process
		 * Used in premium plugins
		 * return true to cancel, otherwise run.
		 */
		 $return = apply_filters('fppdfe_pre_load_template', $form_id, $entry_id, $template, $id, $output, $filename, $arguments);

		if($return !== true)
		{
			/*
			 * Get the tempalte HTML file
			 */
			$entry = $this->load_entry_data($form_id, $entry_id, $template);

			/*
			 * Output HTML version and return if user requested a HTML version
			 */
			if($html === 1)
			{
				echo $entry;
				exit;
			}

			/*
			 * If successfully got the entry then run the processor
			 */
			if(strlen($entry) > 0)
			{
				return $this->PDF_processing($entry, $filename, $id, $output, $arguments);
			}

			return false;
		}
		/*
		 * Used in extensions to return the name of the PDF file when attaching to notifications
		 */
		return apply_filters('fppdfe_return_pdf_path', $form_id, $entry_id);
	}

	/**
	 * Loads the Formidable Form output script (actually the print preview)
	 */
	private function load_entry_data($form_id, $entry_id, $template)
	{
		/* set up contstants for Formidable Pro to use so we can override the security on the printed version */
		if(file_exists(FP_PDF_TEMPLATE_LOCATION.$template))
		{
			return FPPDF_Common::get_html_template(FP_PDF_TEMPLATE_LOCATION.$template);
		}
		else
		{
			/*
			 * Check if template file exists in the plugin's core template folder
			 */
			if(file_exists(FP_PDF_PLUGIN_DIR."templates/" . $template))
			{
				return FPPDF_Common::get_html_template(FP_PDF_PLUGIN_DIR."templates/" . $template);
			}
			/*
			 * If template not found then we will resort to the default template.
			 */
			else
			{
				return FPPDF_Common::get_html_template(FP_PDF_PLUGIN_DIR."templates/" . FPPDFGenerator::$default['template']);
			}
		}
	}


	/**
	 * Creates the PDF and does a specific output (see PDF_Generator function above for $output variable types)
	 */
	public function PDF_processing($html, $filename, $id, $output = 'view', $arguments = [] )
	{

		if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
			require_once FP_PDF_PLUGIN_DIR . '/vendor/autoload.php';
		}

		$paper_size = $arguments['pdf_size'];

		if ( ! is_array( $paper_size ) ) {
			$orientation = ($arguments['orientation'] == 'landscape') ? '-L' : '';
			$paper_size = $paper_size.$orientation;
		} else {
			$orientation = ($arguments['orientation'] == 'landscape') ? 'L' : 'P';
		}

		$config = [
			'format' => $paper_size,
			'orientation' => $orientation,
			'percentSubset' => 60,
			'useSubstitutions' => true,
			'allow_output_buffering' => true,
			'enableImports' => true,
			'ignore_invalid_utf8' => true,
			'setAutoTopMargin' => 'stretch',
			'setAutoBottomMargin' => 'strech',
			'keep_table_proportions' => true,
			'use_kwt' => true,
			'useKerning' => true,
			'justifyB4br' => true,
			'watermarkImgBehind' => true,
			'showWatermarkText' => true,
			'showWatermarkImage' => true,
			// 'autoPadding' => true,// causing Undefined index: border_radius_TL_H and others in Tag.php
		];

		// Custom Fonts
		$customFontData = [];

		// Check if FP_PDF_FONT_LOCATION is defined and exists
		if (defined('FP_PDF_FONT_LOCATION') && is_dir(FP_PDF_FONT_LOCATION)) {
			// Get all .ttf files in the directory
			$fontFiles = glob(FP_PDF_FONT_LOCATION . '/*.ttf');
			
			foreach ($fontFiles as $fontFile) {
				// Extract the font name from the file (without extension)
				$fontName = strtolower(pathinfo($fontFile, PATHINFO_FILENAME));
				
				// Assume regular style ('R') for simplicity; adjust if you need to detect styles
				$customFontData[$fontName] = [
					'R' => basename($fontFile),
				];
			}
		}

		if ( $customFontData ) {
			$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
			$fontDirs = $defaultConfig['fontDir'];
			$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
			$fontData = $defaultFontConfig['fontdata'];
			// Merge custom font directory with default font directories
			$config['fontDir'] = array_merge( $fontDirs, [FP_PDF_FONT_LOCATION] );
			// Merge custom font data with default font data
			$config['fontdata'] = $fontData + $customFontData;
			if ( count( $customFontData ) === 1 ) {
				$config['default_font'] = array_key_first($customFontData);
			}
		}

		$mpdf = new \Mpdf\Mpdf($config);

		/*
		 * Display PDF is full-page mode which allows the entire PDF page to be viewed
		 * Normally PDF is zoomed right in.
		 */
		$mpdf->SetDisplayMode('fullpage');

		if(FP_PDF_ENABLE_SIMPLE_TABLES === true)
		{
			$mpdf->simpleTables = true;
		}

		/*
		 * Automatically detect fonts and substitue as needed
		 */
		if(FP_PDF_DISABLE_FONT_SUBSTITUTION === true)
		{
			$mpdf->useSubstitutions = false;
		}
		else
		{
			$mpdf->autoScriptToLang = true;// mPDF 6
			$mpdf->useSubstitutions = true;
		}

		/*
		* Set RTL languages at user request
		*/
		if($arguments['rtl'] === true)
		{
			$mpdf->SetDirectionality('rtl');
		}

		/*
		* Set up security if user requested
		*/
		if($arguments['security'] === true && $arguments['pdfa1b'] !== true && $arguments['pdfx1a'] !== true)
		{
			$password = (strlen($arguments['pdf_password']) > 0) ? $arguments['pdf_password'] : '';
			$master_password = (strlen($arguments['pdf_master_password']) > 0) ? $arguments['pdf_master_password'] : null;
			$pdf_privileges = (is_array($arguments['pdf_privileges'])) ? $arguments['pdf_privileges'] : array();

			$mpdf->SetProtection($pdf_privileges, $password, $master_password, 128);
		}

		/* PDF/A1-b support added in v3.4.0 */
		if($arguments['pdfa1b'] === true)
		{
			$mpdf->PDFA = true;
			$mpdf->PDFAauto = true;
		}
		else if($arguments['pdfx1a'] === true)  /* PDF/X-1a support added in v3.4.0 */
		{
			$mpdf->PDFX = true;
			$mpdf->PDFXauto = true;
		}

		/*
		* Check if we should auto prompt to print the document on open
		*/
		if(isset($_GET['print']))
		{
			$mpdf->SetJS('this.print();');
		}

		/* load HTML block */
		$mpdf->WriteHTML($html);

		switch($output)
		{
			case 'download':
				 $mpdf->Output($filename, 'D');
				 exit;
			break;

			case 'view':
				 $mpdf->Output(time(), 'I');
				 exit;
			break;

			case 'save':
				/*
				 * PDF wasn't writing to file with the F method - http://mpdf1.com/manual/index.php?tid=125
				 * Return as a string and write to file manually
				 */
				$pdf = $mpdf->Output('', 'S');
				return $this->savePDF($pdf, $filename, $id);
			break;
		}
	}


	/**
	 * Creates the PDF and does a specific output (see PDF_Generator function above for $output variable types)
	 * var $dompdf Object
	 */
	 public function savePDF($pdf, $filename, $id)
	 {
		/* create unique folder for PDFs */
		if(!is_dir(FP_PDF_SAVE_LOCATION.$id))
		{
			if(!mkdir(FP_PDF_SAVE_LOCATION.$id))
			{
				trigger_error('Could not create PDF folder in '. FP_PDF_SAVE_LOCATION.$id, E_USER_WARNING);
				return;
			}
		}

		$pdf_save = FP_PDF_SAVE_LOCATION.$id.'/'. $filename;

		if(!file_put_contents($pdf_save, $pdf))
		{
			trigger_error('Could not save PDF to '. $pdf_save, E_USER_WARNING);
			return;
		}
		return $pdf_save;
	}
}

