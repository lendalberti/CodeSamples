<?php

class QuotesController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column2';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete, disposition', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', 
				'actions'=>array(	'index', 'indexApproval', 'view','create','update', 'search', 'partsUpdate', 'ready', 'myQuoteBoard',
									'delete', 'select', 'history', 'sales', 'DisplayInvoice_FB', 'updateMfg', 'pdf', 'email', 'postUpdateStatus'),
				'expression' => '$user->isLoggedIn'
			),
		
			array('allow', 
				'actions'=>array('admin', 'disposition'),
				'expression' => '$user->isAdmin'
			),

			array('allow', 
				'actions'=>array('config'),
				'expression' => '$user->isConfigMgr'
			),

			array('allow',
				'actions'=>array('disposition'),
				'expression' => '$user->isApprover'
			),

			array('allow', 
				'actions'=>array('manufacturing', 'notifyCoordinators', 'addMessage', 'updateStatus', 'moreInfo' ),
				'expression' => '$user->isProposalManager'
			),

			array('allow', 
				'actions'=>array('coordinator', 'itemStatus', 'myPending', 'addInternalMessage'),
				'expression' => '$user->isCoordinator'
			),

			
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}


	// -------------------------------------------------
	public function actionMyQuoteBoard() {
		echo json_encode( displayMyQuoteBoard() );
	}

		

	// -------------------------------------------------
	public function actionReady($id) {
		pPOST('actionReady()');
		pGET('actionReady()');

		if ( isset($_POST['quoteID']) ) {
			$quote_id = $_POST['quoteID'][0];
			pDebug("actionReady() - quote ID: [$quote_id]");

			$modelQuote = $this->loadModel($quote_id);
			$modelQuote->status_id = Status::SUBMITTED;
			if ( $modelQuote->save() ) {
				pDebug("actionReady() - quote [$quote_id] set to ready");
				echo Status::SUCCESS;  
			}
			else {
				pDebug("actionReady() - can't set quote [$quote_id] to ready; error=", $modelQuote->errors);
				echo Status::FAILURE;  
			}
			return;
		}
	}





	// -------------------------------------------------
	public function actionMoreInfo() {

		pDebug("QuotesController::actionMoreInfo() - _POST=", $_POST);

		$quote_id = $_POST['data']['quoteID'];
		$msg      = $_POST['data']['msg'];

		$modelQuote = $this->loadModel($quote_id);
		$modelQuote->status_id = Status::REJECTED;

		if ( $modelQuote->save() ) {
			$quote_no = $modelQuote->quote_no;
			$subject = "Quote No. $quote_no has been rejected";

			// notify salesperson with msg
			notifySalesPerson( $modelQuote, $subject, $msg );
			pDebug("QuotesController::actionMoreInfo() - Quotes updated and salesperson notified");
			echo Status::SUCCESS;
			return;
		}
		else {
			pDebug("QuotesController::actionMoreInfo() - couldn't update quotes; error=", $modelQuote->errors);
			echo Status::FAILURE;  
		}
		

	}





	//-------------------------------------------------------
	public function actionPdf($id) {
		pDebug('actionPdf() - creating PDF file for quote no. ' . $id);

		$download = isset($_GET['d']) ? true : false;

		$modelQuote = $this->loadModel($id);
		$quote_type = $modelQuote->quote_type_id;

		if ( $quote_type == QuoteTypes::STOCK ) {
			$data = $this->formatData_StockQuote( $modelQuote );
		}
		else if ( $quote_type == QuoteTypes::MANUFACTURING ) {
			$data = $this->formatData_MfgQuote( $modelQuote ); 
		}

		$this->createPDF($data, $download);
	}




	// -------------------------------------------------
	public function actionEmail($id) {
		pPOST('actionEmail()');
		pGET('actionEmail()');

		$attachments = array();

		// 1. create pdf
		$modelQuote = $this->loadModel($id);
		$quote_type = $modelQuote->quote_type_id;

		if ( $quote_type == QuoteTypes::STOCK ) {
			$data = $this->formatData_StockQuote( $modelQuote );
		}
		else if ( $quote_type == QuoteTypes::MANUFACTURING ) {
			$data = $this->formatData_MfgQuote( $modelQuote ); 
		}

		$pdf_file = $this->createPDF($data, null, true);
		$attachments[] = $pdf_file;

		// 2. find attachments
		$criteria =  new CDbCriteria();
        $criteria->addCondition("quote_id = $id" );
		$list = Attachments::model()->findAll($criteria);

		if ( $list ) {
			foreach( $list as $a ) {
				$attachments[] = $a->path . '/' . $a->filename;
			}
		}
		pDebug('Attachments: ', $attachments);

		// 3. email both pdf and attachments
		notifySalesPerson( $modelQuote, 'Customer Quote', 'Customer Quote - see attachment(s)', $attachments );

		echo Status::SUCCESS;   
		//$this->redirect(array('quotes/view?id='.$id));  
	}




	//---------------------------------------------------------------------------------
	public function createPDF($d, $download=false, $email=false) {
	    $pdf = new PDF();

	    // $pdf->AddFont('Raleway', 'raleway.php');

	    try {
		    // define colors
		    $pdf->definePallette();

		    // set up page, margin
		    $pdf->AliasNbPages();
		    $pdf->SetLeftMargin(20);
		    $pdf->AddPage( 'Portrait', 'Letter');
		    $pdf->SetAutoPageBreak(true,35); 
		    $pdf->SetTopMargin(50);

		    // User Profile
		    $u = array();
		    $u['name']   = $d['profile']['name'];
		    $u['title']  = $d['profile']['title'];
		    $u['phone']  = $d['profile']['phone'];
		    $u['fax']    = $d['profile']['fax'];
		    $u['email']  = $d['profile']['email'];
		    $u['sig']    = $d['profile']['sig'];
		    $pdf->userProfile = $u; 

		    // Page Heading
		    $pdf->displayPageHeading();

		    // --------------------------------------------- Add Watermark
		    if ( $d['status_id'] == Status::DRAFT || $d['status_id'] == Status::PENDING ) {
		    	$res = Status::model()->findByPk($d['status_id']);
  				$pdf->addWatermark( $res->name );
		    } 

		    // Company & Contact Info
		    $c = array();
		    $c['quote_no']          = $d['quote_no'];
		    $c['name']              = $d['customer']['name'];
		    $c['address1']          = $d['customer']['address1'];
		    $c['address2']          = $d['customer']['address2'];
		    $c['city']              = $d['customer']['city'];
		    $c['state']             = $d['customer']['state'];
		    $c['zip']               = $d['customer']['zip'];
		    $c['country']           = $d['customer']['country'];
		    $c['contact']['name']   = $d['customer']['contact']['name'];
		    $c['contact']['email']  = $d['customer']['contact']['email'];
		    $c['contact']['phone1'] = $d['customer']['contact']['phone1'];
		    $c['contact']['phone2'] = $d['customer']['contact']['phone2'];

		    $pdf->displayCompanyInfo($c);
		}
		catch( Exception $ex) {
			pDebug("createPDF() - ERROR: caught exception - ", $ex);
		}

	    // Introduction letter
	    $name       = explode(' ', $c['contact']['name']); 
	    $first_name = $name[0];
	    $cre = $d['created'];
	    $exp = $d['expiration_date'];

	    $userModel = Users::model()->findByPk(Yii::app()->user->id);
	    $letter_intro  = "\tDear $first_name,\n";
	    $letter_intro .= $userModel->custom_letter_introduction ? $userModel->custom_letter_introduction  : Yii::app()->params['LETTER_intro_default']; 
	    $letter_conclusion = $userModel->custom_letter_conclusion ? $userModel->custom_letter_conclusion : Yii::app()->params['LETTER_conclusion_default']; 

	    $pdf->displayLetterIntro($letter_intro);

		// create table using new PDF script
		$columns = array(); 
		$items   = array();  
		$line_num = 1;   
		$quote_total = 0;

		// header col
		$col = array();
		$col[] = array( 'text' => 'Line', 'width' => '10', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Part No.', 'width' => '33', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Manufacturer', 'width' => '38', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238','textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Date Code', 'width' => '20', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238','textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Line Note', 'width' => '38', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Quantity', 'width' => '15', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238','textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Price', 'width' => '15', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => 'Total', 'width' => '20', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => 'B', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$columns[] = $col;
		
		if ($d['items']) {
			foreach( $d['items'] as $i => $arr ) {
		    	$row = array();
		    	$row[] = $line_num;		 				
		    	foreach( $arr as $k => $v ) {
		    		if ( !is_array($v) ) {
		    			$row[] = $v; 					   
		    		}
		    		else {
	    				if ( $v[0] ) {
	    					$row[] = number_format($v[0]);
	    					setlocale(LC_MONETARY, 'en_US.UTF-8');
	    					$row[] = money_format('%.2n', $v[1]);
	    					$row[] = money_format('%.2n', ($v[0]*$v[1]) );
	    					$quote_total += ( $v[0] * $v[1] );

	    					$columns[] = $this->fillExtendedTable($row);
	    					$row = array();
	    					$line_num++;
	    				}
		    		}
		    	}
		    }
		}

	    // add BTO to items
		if ( $d['quote_type_id'] == QuoteTypes::MANUFACTURING ) {
			pDebug("createPDF() - need to add BTO=", $d['bto']);

			$charges = array( 	'assembly_charge' => 'Assembly NCNR',
								'test_bi_charge'  => 'Test BI NCNR',
								'test_hw_charge'  => 'Test Hardware NCNR',
								'test_sw_charge'  => 'Test Software NCNR'
			);

			foreach ( $charges as $k => $v ) {
				$mfg_charge = array();

				if ( $d['bto'][$k] ) {
					$mfg_charge[] = $line_num++;
					$mfg_charge[] = '';
					$mfg_charge[] = '';
					$mfg_charge[] = '';
					$mfg_charge[] = $v;
					$mfg_charge[] = '';
					$mfg_charge[] = '';
					$mfg_charge[] = money_format('%.2n', $d['bto'][$k]);
					$quote_total += $d['bto'][$k];
					$columns[] = $this->fillExtendedTable($mfg_charge); 
				}
			}
		}

		// add quote_total to last line
		$col = array();
		$col[] = array('text' => 'Quote Total', 'width' => '169', 'height' => '7', 'align' => 'R', 'font_name' => 'Arial', 'font_size' => '10', 'font_style' => '', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array('text' => money_format('%.2n',$quote_total), 'width' => '20', 'height' => '7', 'align' => 'R', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '224,238,238', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$columns[] = $col;

		// Draw Table   
		$pdf->WriteTable($columns);

		// --------------------------------------------- Add Watermark
    	if ( $d['status_id'] == Status::DRAFT || $d['status_id'] == Status::PENDING ) {
    		// $res = Status::model()->findByPk($d['status_id']);
    		// $pdf->addWatermark( $res->name );
    		$pdf->addWatermark( $d['status_name'] );
    		pDebug("Adding watermark to quote: " + $d['status_name']);
    	}


	    $notes = array();
	    $lead_times = '';
	    $coordinators_notes = '';
	    $notes[] = array('Quote Expiration' => $d['expiration_date']);

	    pDebug("QuotesController::createPDF() - d=", $d);

	    if ($d['terms_conditions'])        $notes[] = array('Terms & Conditions'      => strip_tags($d['terms_conditions']) );   
	    if ($d['customer_acknowledgment']) $notes[] = array('Customer Acknowledgment' => strip_tags($d['customer_acknowledgment']) );   
	    if ($d['risl'])                    $notes[] = array('RISL'                    => strip_tags($d['risl']) );  
	    if ($d['additional_notes'])        $notes[] = array('Additional Notes'        => strip_tags($d['additional_notes']) );  
	  

		if ( $d['quote_type_id'] == QuoteTypes::MANUFACTURING ) {
		    if ( $d['bto']['assembly_lead'] )  $lead_times .= "Assembly: " . $d['bto']['assembly_lead'] . " weeks.\n";
		    if ( $d['bto']['test_bi_lead'] )   $lead_times .= "Test BI:: " . $d['bto']['test_bi_lead'] . " weeks.\n";
		    if ( $d['bto']['test_hw_lead'] )   $lead_times .= "Test Hardware: " . $d['bto']['test_hw_lead'] . " weeks.\n";
		    if ( $d['bto']['test_sw_lead'] )   $lead_times .= "Test Software: " . $d['bto']['test_sw_lead'] . " weeks.";
		    $notes[] = array('Manufacturing Lead Time' => $lead_times);

		    if ( $d['bto']['assembly_notes'] ) $coordinators_notes .= "Assembly: " . $d['bto']['assembly_notes']."\n";
		    if ( $d['bto']['test_notes'] )     $coordinators_notes .= "Test: " . $d['bto']['test_notes']."\n";
		    if ( $d['bto']['quality_notes'] )  $coordinators_notes .= "Quality: " . $d['bto']['quality_notes']."\n";
		    $notes[] = array( 'Manufacturing Notes' => $coordinators_notes );
	    }

	    pDebug("createPDF() - notes=", $notes);
    	$pdf->displayNotes($notes);

	    $pdf->displayLetterConclusion($letter_conclusion);
	    $pdf->displayProfile();

	    if ( $download ) {
	    	$pdf->Output("MyQuote_" . $d['quote_no'] . ".pdf", "D"); // D: send to the browser and force a file download with the name given by name.
	    }
	    else if ( $email ) {
	    	$pdf->Output("/tmp/MyQuote_" . $d['quote_no'] . ".pdf", "F"); // F: save to a local file with the name given by name (may include a path).
	    	return "/tmp/MyQuote_" . $d['quote_no'] . ".pdf";
	    }
	    else {
	    	$pdf->Output("MyQuote_" . $d['quote_no'] . ".pdf", "I"); // I: send the file inline to the browser. The PDF viewer is used if available.
	    }
	    
	}




    private function fillExtendedTable( $row ) {
		$col[] = array( 'text' => $row[0], 'width' => '10', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[1], 'width' => '33', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[2], 'width' => '38', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[3], 'width' => '20', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[4], 'width' => '38', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[5], 'width' => '15', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[6], 'width' => '15', 'height' => '5', 'align' => 'C', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		$col[] = array( 'text' => $row[7], 'width' => '20', 'height' => '5', 'align' => 'R', 'font_name' => 'Arial', 'font_size' => '8', 'font_style' => '', 'fillcolor' => '255,255,255', 'textcolor' => '0,0,0', 'drawcolor' => '0,0,0', 'linewidth' => '0.4', 'linearea' => 'LTBR');
		return $col;
	}


	//-------------------------------------------------------
	public function actionUpdateMfg($id) {
		pDebug("QuotesController::actionUpdateMfg() - id=[$id], _POST=", $_POST);
		
		$item_id = $id;

		if ( isset($_POST['Assembly']) ) {
			try {
				$itemModel = BtoItems::model()->findByPk($item_id);
				$itemModel->assembly_charge         = $_POST['Assembly']['charge'];
				$itemModel->assembly_lead           = $_POST['Assembly']['lead'];
				$itemModel->assembly_location       = $_POST['Assembly']['location'];
				$itemModel->assembly_bond_diagram   = $_POST['Assembly']['bond_diagram'] == 'on' ? 1 : 0;
				$itemModel->assembly_notes          = $_POST['Assembly']['notes'];
				$itemModel->assembly_internal_notes = $_POST['Assembly']['internal_notes'];

				if ( $itemModel->save() ) {
					pDebug("QuotesController::actionUpdateMfg() - Assembly updating item id:[$item_id]");
					echo Status::SUCCESS;  
				}
				else {
					pDebug("QuotesController::actionUpdateMfg() - Error in Assembly updating item id:[$item_id]; error=", $itemModel->errors);
					echo Status::FAILURE;  
				}
			}
			catch (Exception $ex) {
				pDebug("actionItemStatus() - ERROR: Assembly updating item caught exception: ", $ex );
				echo Status::FAILURE;  
			}
		}
		else if ( isset($_POST['Test']) ) {
			pDebug("QuotesController::actionUpdateMfg() - item_id=[$item_id],  _POST=", $_POST);

			try {
				$itemModel = BtoItems::model()->findByPk($item_id);
				$itemModel->test_bi_charge      = $_POST['Test']['bi_charge'];
				$itemModel->test_bi_lead        = $_POST['Test']['bi_lead'];
				$itemModel->test_sw_charge      = $_POST['Test']['sw_charge'];
				$itemModel->test_sw_lead        = $_POST['Test']['sw_lead'];
				$itemModel->test_hw_charge      = $_POST['Test']['hw_charge'];
				$itemModel->test_hw_lead        = $_POST['Test']['hw_lead'];
				$itemModel->test_notes          = $_POST['Test']['notes'];
				$itemModel->test_internal_notes = $_POST['Test']['internal_notes'];

				if ( $itemModel->save() ) {
					pDebug("QuotesController::actionUpdateMfg() - Test updating item id:[$item_id]");
					echo Status::SUCCESS;  
				}
				else {
					pDebug("QuotesController::actionUpdateMfg() - Error in Test updating item id:[$item_id]; error=", $itemModel->errors);
					echo Status::FAILURE;  
				}
			}
			catch (Exception $ex) {
				pDebug("actionItemStatus() - ERROR: Test updating caught exception: ", $ex );
				echo Status::FAILURE;  
			}

		}
		else if ( isset($_POST['Quality']) ) {
			pDebug("QuotesController::actionUpdateMfg() - _POST=", $_POST);

			# holdItem_12_1_3
			preg_match( '/^(\w+)Item_(\d+)_(\d+)_(\d+)$/', $_POST['Quality']['status_id'], $matches );
			$action  = $matches[1];
			$user    = $matches[2];
			$item    = $matches[3];
			$group   = $matches[4];

			try {
				$itemModel = BtoItems::model()->findByPk($item_id);
				$itemModel->quality_notes          = $_POST['Quality']['notes'];
				$itemModel->quality_internal_notes = $_POST['Quality']['internal_notes'];

				if ( $itemModel->save() ) {
					pDebug("QuotesController::actionUpdateMfg() - Quality updating item id:[$item_id]");
					echo Status::SUCCESS;  
				}
				else {
					pDebug("QuotesController::actionUpdateMfg() - Error in Quality updating item id:[$item_id]; error=", $itemModel->errors);
					echo Status::FAILURE;  
				}
			}
			catch (Exception $ex) {
				pDebug("actionItemStatus() - ERROR: Quality updating caught exception: ", $ex );
				echo Status::FAILURE;  
			}

					

			echo Status::SUCCESS;  
		}
		else {
			echo Status::FAILURE;
		}


	}




	//-------------------------------------------------------
	public function actionHistory() {
		$quotes = array();
		$model  = new Quotes();

		$pn   = $_GET['pn'];
		$ajax = $_GET['ajax'];

		if ( $pn && $ajax) {
			pDebug("actionHistory() - returning Quote History for: pn=[$pn], ajax=[$ajax]");

			$history_table = <<<EOT
				<table id='quotes_table'>
				<thead>
					<tr>
						<th>Ouote ID</th>
						<th>Created</th>
						<th>Type</th>
						<th>Mfr</th>
						<th>Date Code</th>

						<th>Cust Name</th>
						<th>Location</th>
						<th>Contact</th>
						<th>Sales Person</th>

						<th>Status</th>
						<th>NoBid or Lost Reason</th>
						<th>Qty</th>
						<th>Unit Price</th>

					</tr>
				</thead>

				<tbody>
EOT;

			setlocale(LC_MONETARY, 'en_US');
			$quotes = $this->getQuoteHistory($pn);

			foreach ( $quotes as $arr ) {

				foreach( $arr as $q ) {
					pDebug("q=", $q);

					$history_table .= "<tr>";
					$history_table .= "<td>".$q['Opportunity_ID']."</td>";
					$history_table .= "<td>". Date('m-d-y', strtotime($q['Date']) ) ."</td>";
					$history_table .= "<td>".$q['Type']."</td>";
					$history_table .= "<td>".$q['Mfr']."</td>";
					$history_table .= "<td>".$q['Date_Code']."</td>";

					$history_table .= "<td>".$q['Customer']."</td>";
					$history_table .= "<td>".$q['Location']."</td>";
					$history_table .= "<td>".$q['Contact']."</td>";
					$history_table .= "<td>".$q['Sales_Person']."</td>";

					$history_table .= "<td>".$q['Status']."</td>";
					$history_table .= "<td>".$q['No_Bid_Lost_Reason']."</td>";
					$history_table .= "<td>".number_format($q['Quantity'])."</td>";
					$history_table .= "<td>".money_format('%.2n', $q['Unit_Price'])."</td>";
					$history_table .= "</tr>";
				}

			} 
					
			$history_table .= '</tbody></table>';
			echo json_encode($history_table);
		}
		else {
			if ( $pn ) {
				$quotes = Quotes::model()->getQuoteHistory($pn);
			}

			$this->render('history',array(
				'model'  => $model,
				'quotes' => $quotes,
			));
		}
	} // END_OF_FUNCTION actionHistory()



	// ------------------------------------------------------------------
	private function getQuoteHistory( $pn ) {
		if ( !$pn ) {
			return null;
		}

		$quotes = array();
		
		$sql = "SELECT * FROM pivotal_quote_history WHERE Part_Number = '$pn'";
		$pivotal_quotes = Yii::app()->db->createCommand($sql)->queryAll();

		pDebug("getQuoteHistory() - pivotal quotes=", $pivotal_quotes);
		$quotes[] = $pivotal_quotes;

        $criteria =  new CDbCriteria();
        $criteria->addCondition("requested_part_number = '$pn'" );
        $criteria->order = 'created_date DESC';
        $bto_items = BtoItems::model()->find($criteria);
        pDebug("getQuoteHistory() - bto_item count=", count($bto_items));

        if ( $bto_items ) {
        	$tmp = array();
	        foreach ( $bto_items as $bto ) {
	        	$tmp['Opportunity_ID']      = $bto->quote->quote_no;
				$tmp['Date']  			    = $bto->quote->created_date;
				$tmp['Type']                = $bto->quote->quoteType->name;

				$tmp['Mfr'] 			    = $bto->DieManufacturer->name;
				$tmp['Date_Code']  			= 'n/a';
				$tmp['Customer'] 			= $bto->quote->customer->name;
				$tmp['Address_1']         	= $bto->quote->customer->address1;
				$tmp['Address_2']         	= $bto->quote->customer->address2;
				$tmp['City']        		= $bto->quote->customer->city;
				$tmp['State']            	= $bto->quote->customer->state->short_name;
				$tmp['Country']           	= $bto->quote->customer->country->short_name;
				$tmp['Zip']               	= $bto->quote->customer->zip;
				$tmp['Location']			= $bto->quote->customer->city . ", " .$bto->quote->state->short_name;
				$tmp['Contact']           	= $bto->quote->contact->fullname;
				$tmp['Email']             	= $bto->quote->contact->email;
				$tmp['Phone']           	= $bto->quote->contact->phone1;
				$tmp['Sales_Person']        = $bto->quote->owner->fullname;
				$tmp['Status']              = $bto->quote->status->name;
				$tmp['No_Bid_Lost_Reason']  = $bto->quote->lostReason->name . ", " . $bto->quote->noBidReason->name;

				$tmp['Part_Number']       	= $bto->requested_part_number;
				$tmp['Quantity']          	= $bto->quantity; 
				$tmp['Unit_Price']        	= $bto->pricing; 
				pDebug("getQuoteHistory() - bto quotes=", $tmp);
				
				$quotes[] = $tmp;
	        }
	    }

        $criteria =  new CDbCriteria();
        $criteria->addCondition("part_no = '$pn'" );
		$criteria->order = 'created_date DESC';
        $stock_items = StockItems::model()->findAll($criteria);
        pDebug("getQuoteHistory() - stock_item count=", count($stock_items));
        
        if ( $stock_items ) {
	    	$tmp = array();
	        foreach ( $stock_items as $st ) {
	        	$tmp['Opportunity_ID']      = $st->quote->quote_no;
				$tmp['Date']  			    = $st->quote->created_date;
				$tmp['Type']                = $st->quote->quoteType->name;
				$tmp['Mfr'] 			    = $st->manufacturer->name;
				$tmp['Date_Code']  			= 'n/a';
				$tmp['Customer'] 			= $st->quote->customer->name;
				$tmp['Address_1']         	= $st->quote->customer->address1;
				$tmp['Address_2']         	= $st->quote->customer->address2;
				$tmp['City']        		= $st->quote->customer->city;
				$tmp['State']            	= $st->quote->customer->state->short_name;
				$tmp['Country']           	= $st->quote->customer->country->short_name;
				$tmp['Zip']               	= $st->quote->customer->zip;
				$tmp['Location']			= $st->quote->customer->city . ", " . $st->quote->customer->state->short_name;
				$tmp['Contact']           	= $st->quote->contact->fullname;
				$tmp['Email']             	= $st->quote->contact->email;
				$tmp['Phone']           	= $st->quote->contact->phone1;
				$tmp['Sales_Person']        = $st->quote->owner->fullname;
				$tmp['Status']              = $st->quote->status->name;
				$tmp['No_Bid_Lost_Reason']  = $st->quote->lostReason->name . ", " . $st->quote->noBidReason->name;
				$tmp['Part_Number']       	= $st->part_no;
				$tmp['Quantity']          	= getStockQuantity($st);
				$tmp['Unit_Price']        	= getStockPricing($st);
				pDebug("getQuoteHistory() - stock quotes=", $tmp);

				$quotes[1][0] = $tmp;
		    }
		}

        pDebug("getQuoteHistory() - All quotes=", $quotes);
        return $quotes;
	}


	//-------------------------------------------------------
	public function actionSales() {

		$pn   = $_GET['pn'];
		$ajax = $_GET['ajax'];
		pDebug("actionSales() - getting Sales history for part no. $pn (ajax=$ajax)");


		if ( $pn && $ajax) {
			pDebug("actionSales() - returning Sales history for: pn=[$pn], ajax=[$ajax]");

			$sales_table = <<<EOT
				<table id='sales_table'>
				<thead>
					<tr>
						<th>Order Status</th>
						<th>Order Date</th>
						<th>Salesperson</th>
						<th>Customer Code</th>
						<th>Customer</th>
						<th>Ship Date</th>
						<th>Ship to Customer</th>
						<th>Ship to City</th>
						<th>Invoice Date</th>
						<th>Invoice</th>
						<th>Sales Order</th>
						<th>Line Number</th>
						<th>Purchase Order No.</th>
						<th>Part No.</th>
						<th>Quantity</th>
						<th>Unit Price</th>
						<th>Net Amount</th>
					</tr>
				</thead>

				<tbody>
EOT;

			setlocale(LC_MONETARY, 'en_US');
			$sales = Quotes::model()->getSalesHistory($pn);    

			foreach ( $sales as $s ) {
				$sales_table .= "<tr>";
				$sales_table .= "<td>".$s->Order_Status."</td>";
				$sales_table .= "<td>".$this->fixDateDisplay($s->Order_Date)."</td>";
				$sales_table .= "<td>".$s->Sales_Person_Name."</td>";
				$sales_table .= "<td>".$s->Customer_ID."</td>";
				$sales_table .= "<td>".$s->Customer_Name."</td>";
				$sales_table .= "<td>".$this->fixDateDisplay($s->Ship_Date)."</td>";
				$sales_table .= "<td>".$s->Ship_To_Customer_Name."</td>";
				$sales_table .= "<td>".$s->Ship_To_City."</td>";
				$sales_table .= "<td>".$this->fixDateDisplay($s->Invoice_Date)."</td>";

				$url = CController::createUrl('quotes/displayInvoice_FB') . '?invoice=' . $s->Invoice;
				$sales_table .= "<td>". "<a target='_blank' href='$url'>" . $s->Invoice . "</a></td>";

				$sales_table .= "<td>".$s->Sales_Order."</td>";
				$sales_table .= "<td>".$s->Line_Number."</td>";
				$sales_table .= "<td>".$s->Customer_Purchase_Order_ID."</td>";
				$sales_table .= "<td>".$s->Part_Number."</td>";
				$sales_table .= "<td>".number_format($s->QTY_Invoiced)."</td>";
				$sales_table .= "<td>".money_format('%.2n', $s->Unit_Price)."</td>";  
				$sales_table .= "<td>".money_format('%.2n', $s->Net_Amount)."</td>";  
				$sales_table .= "</tr>";
			} 
					
			$sales_table .= '</tbody></table>';
			echo json_encode($sales_table);
		}

	}



	public function actionDisplayInvoice_FB() {
			pDebug("Quotes::actionDisplayInvoice_FB() -  _GET:", $_GET );

			$this->renderPartial('displayPdf_FB',array(
				'invoice' => $_GET['invoice'],
			));
	}



	public function actionConfig() {
		pDebug("actionConfig() - _GET=", $_GET);

		$model = new Quotes;
		$this->render('config',array(
			'model'=>$model,
		));
	}


	// ------------------------------------------------------- Won - Lost - NoBid
	public function actionPostUpdateStatus() {
		pDebug("actionPostUpdateStatus() - _POST=", $_POST); 

		if ( isset($_POST['quote_id']) && isset($_POST['status_id']) ) {
			$quote_id  = $_POST['quote_id'];
			$status_id = $_POST['status_id'];
		}
		else {
			echo Status::FAILURE; 
		}

		$model = Quotes::model()->findByPk($quote_id);
		$model->status_id = $status_id;
		if ( $model->save() ) {
			pDebug("actionPostUpdateStatus() - quote [$quote_id] status changed to: ", $model->status->name);
			echo Status::SUCCESS; 
			return;	
		}

		echo Status::FAILURE; 
		return;	
	}



	// ------------------------------------------------------- update Quote Status by Proposal Manager
	public function actionUpdateStatus() {
		pDebug("actionUpdateStatus() - Quote Status being updated by Proposal Manager; _POST=", $_POST); 

		$quote_id          = $_POST['quote_id'];
		$new_status_id     = $_POST['new_status_id'];
		$new_status_text   = $_POST['new_status_text'];

		$model = Quotes::model()->findByPk($quote_id);
		$model->status_id = $new_status_id;

		// change approval_process_id based on status
		if ( $new_status_id == Status::APPROVED || $new_status_id == Status::REJECTED ) {
			$model->approval_process_id = ApprovalProcess::COMPLETED;
		}																
		else  {
			$model->approval_process_id = ApprovalProcess::STARTED;
		}

		if ( $model->save() ) {
			pDebug("actionUpdateStatus() - quote status changed to: $new_status_id ($new_status_text)");
			notifySalespersonStatusChange($model);
			echo Status::SUCCESS; 
		}
		else {
			pDebug("actionUpdateStatus() - Error - can't change quote status: ", $model->errors);
			echo Status::FAILURE; 
		}

		return;
	}


	public function actionItemStatus() {
		pDebug("actionItemStatus() - _POST=", $_POST); 

		$item_id   = $_POST['itemID'];
		$group_id  = $_POST['groupID'];
		$action    = $_POST['action'];

		$status['Hold']    = Status::PENDING;    // 2
		$status['Approve'] = Status::APPROVED;   // 8
		$status['Reject']  = Status::REJECTED;   // 9

		$criteria =  new CDbCriteria();
		$criteria->addCondition("bto_item_id = $item_id");
		$criteria->addCondition("group_id = $group_id");

		try {
			$model = BtoItemStatus::model()->find( $criteria );
			$model->status_id = $status[$action];

			if ( $model->save() ) {
				pDebug("actionItemStatus() - bto_item_id=[$item_id], group_id=[$group_id], action=[$action]" );
				echo Status::SUCCESS; 
			}
			else {
				pDebug("actionItemStatus() - ERROR: could not change status for item_id: [$item_id], group_id=[$group_id], action=[$ction]; error:", $model->errors );
				echo Status::FAILURE; 
			}
		}
		catch( Exception $e) {
			pDebug("actionItemStatus() - Exception: ", $e->errorInfo );
			echo Status::FAILURE; 
		}


	}






	public function actionSelect() {
		pDebug("actionSelect() - _GET=", $_GET);

		 // us_states, countries, regions, customer_types, users, customers, tiers, territories
		if ( isset($_GET['q']) ) {
			$q = $_GET['q'];

			if ( in_array( $q, array('regions', 'customer_types', 'tiers', 'territories' )) ) {
				$list = array();
				$sql  = "SELECT * FROM $q ORDER BY name";
				$command = Yii::app()->db->createCommand($sql);
				$results = $command->queryAll();
				// pDebug("actionSelect() - sql=[$sql], results:", $results );

				foreach( $results as $r ) {
					$list[] = array( 'id' => $r['id'], 'label' => $r['name'] );
				}
				// pDebug("actionSelect() - list:", $list); 
				echo json_encode($list);
			}
			else if ( in_array( $q, array( 'us_states', 'countries' )) ) {
				$list = array();
				$sql  = "SELECT * FROM $q ORDER BY long_name";
				$command = Yii::app()->db->createCommand($sql);
				$results = $command->queryAll();
				// pDebug("actionSelect() - sql=[$sql], results:", $results );

				foreach( $results as $r ) {
					$list[] = array( 'id' => $r['id'], 'label' => $r['long_name'] );
				}
				// pDebug("actionSelect() - list:", $list); 
				echo json_encode($list);
			}
			else if ( in_array( $q, array( 'users' )) ) {
				$list = array();
				$sql  = "SELECT * FROM $q ORDER BY first_name";
				$command = Yii::app()->db->createCommand($sql);
				$results = $command->queryAll();
				// pDebug("actionSelect() - sql=[$sql], results:", $results );

				foreach( $results as $r ) {
					$list[] = array( 'id' => $r['id'], 'label' => $r['first_name'].' '.$r['last_name'] );
				}
				// pDebug("actionSelect() - list:", $list); 
				echo json_encode($list);
			}
			else if ( in_array( $q, array('customers' )) ) {
				$list = array();
				$sql  = "SELECT * FROM $q ORDER BY name";
				$command = Yii::app()->db->createCommand($sql);
				$results = $command->queryAll();
				// pDebug("actionSelect() - sql=[$sql], results:", $results );

				foreach( $results as $r ) {
					$list[] = array( 'id' => $r['id'], 'label' => $r['name'].' ('.$r['cust_code'].') ');
				}
				// pDebug("actionSelect() - list:", $list); 
				echo json_encode($list);
			}
			else if ( in_array( $q, array('industries' )) ) {
				$list = array();
				$sql  = "SELECT * FROM $q ORDER BY name";
				$command = Yii::app()->db->createCommand($sql);
				$results = $command->queryAll();
				// pDebug("actionSelect() - sql=[$sql], results:", $results );

				foreach( $results as $r ) {
					$list[] = array( 'id' => $r['id'], 'label' => $r['name'] );
				}
				// pDebug("actionSelect() - list:", $list); 
				echo json_encode($list);
			}
			else {
				pDebug("actionSelect() - list:", $list);
				$this->redirect(array('index'));
			}
		}

	}     



	public function actionDisposition($id)     { 
		pTrace( __METHOD__ );
		pDebug('actionDisposition() = _POST:', $_POST);

		$quote_id = $id;

		// TODO: refactor all this...
		$item_id          = $_POST['item_id'];
		$item_disposition = $_POST['item_disposition'];

		$modelStockItems = StockItems::model()->findByPk($item_id);

		if ( $item_disposition == 'Approve' ) {
			$modelStockItems->status_id = Status::APPROVED;  				 // set Item status

			if ( $modelStockItems->save() ) {
				$quoteModel = $this->loadModel($quote_id);
				$quoteModel->status_id = $this->getUpdatedQuoteStatus($quote_id);   // set Quote status

				if ( $quoteModel->save() ) {
					pDebug("actionDisposition() - Quote status updated to: " . $quoteModel->status->name . "; calling notifySalespersonStatusChange()...");
					notifySalespersonStatusChange($quoteModel,$modelStockItems );

					echo Status::SUCCESS;
				}
				else {
					pDebug("actionDisposition() - Couldn't update Quote status: ", $quoteModel->errors );
					echo Status::FAILURE;
				}
			}
			else {
				pDebug("actionDisposition() - couldn't save new model; errors: ", $modelStockItems->errors);
				echo Status::FAILURE;
			}
		}
		else if ( $item_disposition == 'Reject' ) {
			$modelStockItems->status_id = Status::REJECTED;
			if ( $modelStockItems->save() ) {
				pDebug("actionDisposition() - item [$item_id] in quote [$id] rejected.");
				$quoteModel = $this->loadModel($quote_id);
				$quoteModel->status_id = $this->getUpdatedQuoteStatus($quote_id);

				if ( $quoteModel->save() ) {
					pDebug("actionDisposition() - Quote status updated to: " . $quoteModel->status->name . "; calling notifySalespersonStatusChange()...");
					//notifySalespersonStatusChange($quoteModel, $modelStockItems);
					echo Status::SUCCESS;
				}
				else {
					pDebug("actionDisposition() - Couldn't update Quote status: ", $quoteModel->errors );
					echo Status::FAILURE;
				}
			}
			else {
				pDebug("actionDisposition() - couldn't save new model; errors: ", $modelStockItems->errors);
				echo Status::FAILURE;
			}
		}

	}



	// ------------------------------------- AutoCompletion Search...
    public function actionSearch()     {        
 		pTrace( __METHOD__ );
        $term = isset( $_GET['term'] ) ? trim(strip_tags($_GET['term'])) : null; if ( !$term ) return null;
        
		$tmp = Customers::model()->findAll( array('condition' => "name LIKE '%$term%' OR address1 LIKE '%$term%' OR cust_code LIKE '%$term%' OR zip LIKE '%$term%'  OR city LIKE '%$term%' ") );
		foreach( $tmp as $c ) {
			//pDebug("actionSearch() - crm:", $c->attributes);
			$results[] = array( 'label' => $c->cust_code . ' - ' . $c->name . ", (crm) " . $c->address1, 'value' => $c->id );
		}

		$tmp = Contacts::model()->findAll( array('condition' => "first_name LIKE '%$term%' OR last_name LIKE '%$term%' OR email LIKE '%$term%' OR title LIKE '%$term%' OR title LIKE '%$term%' OR phone1 LIKE '%$term%'  OR phone2 LIKE '%$term%'  OR zip LIKE '%$term%' "));
		foreach( $tmp as $c ) {
			//pDebug("actionSearch() - contact:", $c->attributes);
			$results[] = array( 'label' => $c->first_name . " " . $c->last_name, 'value' => $c->id );
		}

		array_multisort($results);
		echo json_encode($results);
    }



    // ------------------------------------- AutoCompletion Search using new api...
    public function actionSearch_JUNK()     {        
       	$term = isset( $_GET['term'] ) ? trim(strip_tags($_GET['term'])) : null; if ( !$term ) return null;
       	$item = urlencode($term);
       	$results = array();

       	foreach( array( 'id', 'name', 'contact' ) as $what ) {
       		$url  = "http://invapi.rocelec.com/customer/$what/$item";	  
       		$tmp = json_decode( file_get_contents($url) );
			pDebug("Search result for $what=", $tmp);

			foreach( $tmp->customer as $c ) {
				$results[] = array( 'label' => $c->customer . ", " . $c->customerName  . " (" . $c->contact . ")", 'value' => $c->customer ); // use customer code as id
			}
       	}

       	$sql = "SELECT * FROM pivotal_quote_history WHERE Customer LIKE '%$item%' OR Contact LIKE '%$item%' GROUP BY Customer";
		$pivotal_quotes = Yii::app()->db->createCommand($sql)->queryAll();
		foreach( $pivotal_quotes as $pq ) {
			

			preg_match('/^.+\s\((.{6}\s*)\).*$/', $pq['Customer'], $matches);
			$cust_code = isset($matches[1]) ? $matches[1] : 'PQ_'.$pq['id'];


			pDebug("PQ: Customer=[". $pq['Customer'] ."], Search result=", $pq);

			$results[] = array( 'label' => "$cust_code, (" . $pq['Opportunity_id'] . ") " . $pq['Customer'] . " (" . $pq['Contact'] . ")", 'value' => $cust_code );
		}




       	array_multisort($results);
		//pDebug('AutoCompletion results=', $results);
		echo json_encode($results);
    }








    // ------------------------------------- 
	public function actionView($id) {
		pTrace( __METHOD__ );

		$quote_id    = $id;

		$data['model'] = $this->loadModel($id);
		//pDebug('actionView() - Viewing quote model:', $data['model']->attributes );

		$customer_id = $data['model']->customer_id;
		$contact_id  = $data['model']->contact_id;
		$quote_type  = $data['model']->quote_type_id;

		$data['customer'] = Customers::model()->findByPk($customer_id);
		$data['contact']  = Contacts::model()->findByPk($contact_id);
		$data['status']   = Status::model()->findAll();

		if ( $quote_type == QuoteTypes::MANUFACTURING ) {
			$data['coordinators'] = Coordinators::model()->getCoordinatorList();

			$criteria =  new CDbCriteria();
			$criteria->addCondition("quote_id = $quote_id");
			$data['BtoItems_model'] = BtoItems::model()->find( $criteria );
			
			// pDebug("actionView() - BtoItems_model->attributes:", $data['BtoItems_model']->attributes );

			$data['bto_messages'] = BtoMessages::model()->getAllMessageSubjects($id);

			$criteria =  new CDbCriteria();
			$criteria->addCondition("bto_item_id = " . $data['BtoItems_model']->id );
			$data['BtoItemStatus'] = BtoItemStatus::model()->findAll( $criteria );

		}
		else {
			$criteria =  new CDbCriteria();
			$criteria->addCondition("quote_id = $quote_id");
			$data['StockItems_model'] = StockItems::model()->find( $criteria );

			$data['items'] = $this->getStockItemsByQuote($quote_id);
		}

		$data['selects']      = Quotes::model()->getAllSelects();
		// $data['attachments']  = Attachments::model()->getAllAttachments($id);
		$data['attachments']  = getQuoteAttachments($quote_id);

		$this->render('view',array(
			'data'=>$data,
		));
	}

	
	
	public function actionCreate() {
		pTrace( __METHOD__ );
		
		if ( isset($_POST['Customers']) && isset($_POST['Contacts']) && isset($_POST['Quotes'])   ) {
			pDebug("Quotes::actionCreate() - _POST values from serialized form values:", $_POST);

			$customer_id = $_POST['Customers']['id'];
			$contact_id  = $_POST['Contacts']['id'];
			$quote_type  = $_POST['Quotes']['quote_type_id'];

			if ( !$customer_id ) {				// create new customer, get id
				try {
					$modelCustomers = new Customers;
					$modelCustomers->attributes             = $_POST['Customers'];

					if ( preg_match( '/^\d+$/', $_POST['Customers']['country_id'] ) ) {
						$modelCustomers->country_id				= $_POST['Customers']['country_id'];
					}
					else {
						$modelCustomers->country_id				= getCountryID($_POST['Customers']['country_id']);
					}
					
					$modelCustomers->region_id				= $_POST['Customers']['region_id'];
					$modelCustomers->customer_type_id		= $_POST['Customers']['customer_type_id'];
					$modelCustomers->territory_id			= $_POST['Customers']['territory_id'];
					$modelCustomers->tier_id			    = $_POST['Customers']['tier_id'];
					$modelCustomers->strategic				= $_POST['Customers']['strategic'];
					$modelCustomers->address1				= $_POST['Customers']['address1'];
					$modelCustomers->city					= $_POST['Customers']['city'];
					$modelCustomers->industry_id			= $_POST['Customers']['industry_id'];
					$modelCustomers->cust_code	            = $_POST['Customers']['cust_code'];
					pDebug("Quotes::actionCreate() - trying to create new customer with theses attributes:", $modelCustomers->attributes );

					if ( $modelCustomers->save() ) {
						$customer_id = $modelCustomers->id; 
						pDebug("Quotes::actionCreate() - created new customer with the following attributes:", $modelCustomers->attributes );
						// create contact if needed in next section
						
					}
					else {
						pDebug("Quotes::actionCreate() - ERROR: couldn't create new customer with the following attributes:", $modelCustomers->attributes );
						pDebug("Quotes::actionCreate() - Error Message", $modelCustomers->errors );
						echo Status::FAILURE; 
						return;
					}
				}
				catch( Exception $ex ) {
					pDebug("actionCreate() - Exception: ", $ex );
					echo Status::FAILURE;
				}
			}

			if (!$contact_id ) {					// create new contact, get id
				$modelContacts = new Contacts;
				$modelContacts->attributes      = $_POST['Contacts'];
				if ( $modelContacts->save() ) {
					$contact_id = $modelContacts->id; 
					pDebug("Quotes::actionCreate() - created new contact with the following attributes:", $modelContacts->attributes );
				}
				else {
					pDebug("Quotes::actionCreate() - ERROR: couldn't create new contact with the following attributes:", $modelContacts->attributes );
					pDebug("Quotes::actionCreate() - ERROR:", $modelContacts->errors );
					echo Status::FAILURE; 
					return;
				}
			}

			$modelQuotes                  = new Quotes;
			$modelQuotes->attributes      = $_POST['Quotes'];
			$modelQuotes->customer_id     = $customer_id;
			$modelQuotes->contact_id      = $contact_id;
			$modelQuotes->quote_no        = $this->getQuoteNumber();
			$modelQuotes->status_id       = Status::DRAFT;
			$modelQuotes->owner_id        = Yii::app()->user->id;
			$modelQuotes->created_date    = Date('Y-m-d 00:00:00');
			$modelQuotes->updated_date    = Date('Y-m-d 00:00:00');
			$modelQuotes->expiration_date = $this->getQuoteExpirationDate();

			$modelQuotes->quote_type_id   = QuoteTypes::TBD;
			
			$url = "<span class='my_url'>http://www.rocelec.com/terms</span>";

			$modelQuotes->terms_conditions_id     = null;
			$modelQuotes->customer_acknowledgment = "";
			$modelQuotes->risl                    = "";
			$modelQuotes->manufacturing_lead_time = "";
			$modelQuotes->additional_notes        = "";
			
			pDebug("Quotes::actionCreate() - saving Quote with the following attributes", $modelQuotes->attributes );
			try {
				if ( $modelQuotes->save() ) {
					pDebug("Quotes::actionCreate() - Quote No. " . $modelQuotes->quote_no . " saved; quote ID=" . $modelQuotes->id );
					Customers::model()->addContact($customer_id,$contact_id);
					echo $modelQuotes->id . '|' . $modelQuotes->quote_no;
				}
				else {
					pDebug("actionCreate() - Error on modelQuotes->save(): ", $modelQuotes->errors);
					echo Status::FAILURE;
				}
			}
			catch (Exception $e) {
				pDebug("actionCreate() - Exception: ", $e );
				echo Status::FAILURE;
			}
		}
		else {
			$data['selects'] = Quotes::model()->getAllSelects();

			$data['sources'] = Sources::model()->findAll( array('order' => 'name') );
			$this->render('create',array(
				'data'=>$data,
			));
		}
	}


	public function actionPartsUpdate() 	{
		pTrace( __METHOD__ );
		$arr = array();

		try {
			
			if ( isset($_POST['item_id']) ) {   												// editing Inventory Item
				pDebug( "actionPartsUpdate() - editing Inventory item: _POST=", $_POST ); 

				$quote_id       = $_POST['quote_id'];
				$modelStockItem = StockItems::model()->findByPk( $_POST['item_id']);

				/*
					if editing an item that has been rejected, then set it back to 'DRAFT';
					then, check to see if there are any more Rejected or Pending items
					- if neither, change status respectively
				*/

				if ( $modelStockItem->status_id == Status::REJECTED ) {
					$modelStockItem->setAttribute( 'status_id', Status::DRAFT );
				}


				preg_match('/^item_price_(.+)$/', $_POST['item_volume'], $match); 
				$volume = $match[1]; 
				pDebug("volume=[$volume]");

				$modelStockItem->setAttribute( 'quote_id', $quote_id );

				$modelStockItem->setAttribute( 'qty_1_24', '' );
				$modelStockItem->setAttribute( 'qty_25_99', '' );
				$modelStockItem->setAttribute( 'qty_100_499', '' );
				$modelStockItem->setAttribute( 'qty_500_999', '' );
				$modelStockItem->setAttribute( 'qty_1000_Plus', '' );
				$modelStockItem->setAttribute( 'qty_Base', '' );
				$modelStockItem->setAttribute( 'qty_Custom', '' ); 
				$modelStockItem->setAttribute( 'qty_'. $volume, $_POST['item_qty'] );
				$modelStockItem->setAttribute( 'line_note', $_POST['item_line_note'] );

				pDebug( "actionPartsUpdate() - updating Inventory Item with the following attributes: ", $modelStockItem->attributes );

				if ( $modelStockItem->save() ) {
					pDebug("actionPartsUpdate() - Inventory Item updated.");

					$quoteModel = $this->loadModel($quote_id);
					$quoteModel->status_id = $this->getUpdatedQuoteStatus($quote_id);

					if ( $quoteModel->save() ) {
						pDebug("actionPartsUpdate() - Quote status updated to: " . $quoteModel->status_id );
					}
					else {
						pDebug("actionPartsUpdate() - Couldn't update Quote status: ", $quoteModel->errors );
					}
				}
				else {
					pDebug("actionPartsUpdate() - Inventory Item NOT updated; error=", $modelStockItem->errors);
				}
			}
			else {  																			// adding Inventory Item
				pDebug('actionPartsUpdate() - _POST:', $_POST);

				$modelStockItem = new StockItems;
				$modelStockItem->attributes = $_POST;

				// TODO - refactor this
				if ( $_POST['lifecycle'] == 'Active' ) {
					$lifecycle = Lifecycles::ACTIVE;
				}
				else if ( $_POST['lifecycle'] == 'Obsolete' ) {
					$lifecycle = Lifecycles::OBSOLETE;
				}
				else if ( $_POST['lifecycle'] == 'Aftermarket' ) {
					$lifecycle = Lifecycles::AFTERMARKET;
				}
				else {
					$lifecycle = Lifecycles::N_A;
				}

				pDebug("BEFORE: modelStockItem status=" . $modelStockItem->status_id);
				$modelStockItem->setAttribute( 'lifecycle_id', $lifecycle ); 
				$modelStockItem->setAttribute( 'status_id', $_POST['approval_needed'] == 1 ? Status::PENDING : Status::DRAFT ); 
				$modelStockItem->line_note = isset($_POST['line_note']) ? $_POST['line_note'] : 'n/a';
				pDebug("AFTER: modelStockItem status=" . $modelStockItem->status_id);

				if ( $_POST['comments'] ) {
					$modelStockItem->setAttribute( 'comments', $this->getTimeStamp() . "\n....." . $_POST['comments'] );
				}

				pDebug( "actionPartsUpdate() - updating StockItems model with the following attributes: ", $modelStockItem->attributes );
				if ( $modelStockItem->save() ) {
					$stockItem_ID = $modelStockItem->getPrimaryKey();
					$modelQuote = Quotes::model()->findByPk( $_POST['quote_id'] );
					$modelQuote->quote_type_id = QuoteTypes::STOCK;   					// update Quote type

					pDebug("BEFORE: modelQuote status=" . $modelQuote->status_id);
					if ( $_POST['approval_needed'] ) {
						$modelQuote->status_id = Status::PENDING;
						notifyApprovers($modelQuote);
					}
					else {
						$modelQuote->status_id = Status::DRAFT;
					}
					pDebug("AFTER: modelQuote status=" . $modelQuote->status_id);


					if ( $modelQuote->save() ) {
						$arr[] = array( 'item_id' => $stockItem_ID );

						/*
							TODO: check out why this is causing an error in function checkCustomPrice() - see iq2_main.js
						
									Sending json: (Len D'Alberti)
									[{"item_id":"51"}]

									Your Customer Quote could NOT be updated - see Admin (checkCustomPrice)

									ERROR=SyntaxError: Unexpected token I
									VM2125:1394 jqXHR{"readyState":4,"responseText":"Invalid address: [{\"item_id\":\"50\"}]","status":200,"statusText":"OK"}
									Navigated to http://lenscentos/iq2/index.php/quotes/update/7

						*/
					}
					else {
						pDebug("actionPartsUpdate() - quote NOT saved; error=", $modelQuote->errors);
					}
				}
				else {
					pDebug("actionPartsUpdate() - item NOT saved; error=", $modelStockItem->errors);
				}

			}
		}
		catch (Exception $e) {
			pDebug("actionPartsUpdate() - Exception: ", $e->errorInfo );
		}

		pDebug('Sending json:', json_encode($arr) );  //  [{"item_id":"51"}]
		echo json_encode($arr);
		return;

	}


	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate_ORIGINAL($id) 	{
		pTrace( __METHOD__ );
		$quote_id   = $id;
		$quoteModel = $this->loadModel($quote_id);

		if ( $quoteModel->owner_id != Yii::app()->user->id && !Yii::app()->user->isAdmin && !Yii::app()->user->isProposalManager ) {
			return;
		}
		
		if ( $_POST ) {  // TODO: refactor this, as this really should be BtoItems Update
			// validate source id > 0
			if ( $_POST['Quotes']['source_id'] == 0 ) {
				echo Status::FAILURE;
				return;
			}

			if ( $_POST['quoteTypeID'] == QuoteTypes::MANUFACTURING ) {
				if ( isset($_POST['BtoItems']) ) {
					$item_id = $_POST['BtoItems']['id'];

					try { 
						pDebug("Quotes::actionUpdate() - saving item_id:[$item_id] for Manufacturing quote: [$id]");

						$itemsModel                        = BtoItems::model()->findByPk($item_id);
						$itemsModel->quote_id              = $quote_id;
						$itemsModel->order_probability_id  = $_POST['BtoItems']['order_probability_id'];
						$itemsModel->requested_part_number = $_POST['BtoItems']['requested_part_number'];
						$itemsModel->generic_part_number   = $_POST['BtoItems']['generic_part_number'];
						$itemsModel->quantity              = str_replace( array(','), "", $_POST['BtoItems']['quantity'] );  // remove commas
						$itemsModel->die_manufacturer_id   = $_POST['BtoItems']['die_manufacturer_id'];
						$itemsModel->package_type_id       = $_POST['BtoItems']['package_type_id'];
						$itemsModel->lead_count            = $_POST['BtoItems']['lead_count'];
						$itemsModel->process_flow_id       = $_POST['BtoItems']['process_flow_id'];
						$itemsModel->testing_id            = $_POST['BtoItems']['testing_id'];
						$itemsModel->ncnr                  = $_POST['BtoItems']['ncnr'];
						$itemsModel->wip_product           = $_POST['BtoItems']['wip_product'];
						$itemsModel->line_note             = $_POST['BtoItems']['line_note'];

						pDebug("Quotes::actionUpdate() - item attributes:", $itemsModel->attributes );
					
						if ( $itemsModel->save() ) {
							pDebug("Quotes::actionUpdate() - Manufacturing quote saved.");
							echo Status::SUCCESS;
							return;
						}
						else {
							pDebug("Quotes::actionUpdate() - Error: manufacturing quote NOT saved, error=", $itemsModel->errors);
							echo Status::FAILURE;
							return;
						}
					}
					catch( Exception $ex ) {
						pDebug("actionUpdate() - Exception: ", $ex );
						echo Status::FAILURE;
						return;
					}
				}
			}

			// validate customer - if missing id, then assume it's a new customer, check for required fields
			if ( $_POST['Customers']['id'] ) {
				$customer_id =  $_POST['Customers']['id'];
			}
			else {
				if ( 	$_POST['Customers']['name'] && 
						$_POST['Customers']['address1'] && 
						$_POST['Customers']['city'] && 
						$_POST['Customers']['country_id'] ) {
					// create new customer
					$customerModel = new Customers();
					$customerModel->attributes =  $_POST['Customers'];
					if ($customerModel->save()) {
						pDebug('Saved new customer: ', $customerModel->attributes);
						$customer_id = $customerModel->id;
					}
					else {
						pDebug("actionUpdate() - can't save new contact; error=", $customerModel->errors );
						echo Status::FAILURE;
					}
				}
				else {
					echo Status::FAILURE;
				}
			}

			// validate contact - if missing id, then assume it's a new contact, check for required fields
			if ( $_POST['Contacts']['id'] ) {
				$contact_id =  $_POST['Contacts']['id'];
			}
			else {
				if ( 	$_POST['Contacts']['first_name'] &&
						$_POST['Contacts']['last_name'] && 
						$_POST['Contacts']['email'] &&
						$_POST['Contacts']['title'] &&
						$_POST['Contacts']['phone1'] ) {
					
					// create new contact
					$contactModel = new Contacts();
					$contactModel->attributes =  $_POST['Contacts'];
					if ($contactModel->save()) {
						pDebug('Saved new contact: ', $contactModel->attributes);
						$contact_id = $contactModel->id;
					}
					else {
						pDebug("actionUpdate() - can't save new contact; error=", $contactModel->errors );
						echo Status::FAILURE;
					}
				}
				else {
					echo Status::FAILURE;
				}
			}

			$quoteModel->attributes        = $_POST['Quotes'];

			$newStatus = $this->getUpdatedQuoteStatus($quote_id); 
			if ( $newStatus ) {
				$quoteModel->status_id = $newStatus;
				pDebug("Quotes::actionUpdate() - quote status changed to: [".$quoteModel->status->name);
			}

			$quoteModel->salesperson_notes = $_POST['Quotes']['salesperson_notes'];
			pDebug("actionUpdate() - Updating quote with these attributes:", $quoteModel->attributes);

			try {
				$res = $quoteModel->save();
			}
			catch (Exception $e) {
				pDebug("actionUpdate() - Exception: ", $e->errorInfo );
				echo Status::FAILURE;
				return;
			}

			echo Status::SUCCESS;
			return;
		}
		else {
			pDebug("actionUpdate() - _GET=", $_GET);

			$data['model'] = $this->loadModel($quote_id);
			$customer_id   = $data['model']->customer_id;
			$contact_id    = $data['model']->contact_id;

			// ------------------------------ get customer
			$data['customer'] = Customers::model()->findByPk($customer_id);
			
			// ------------------------------ get contact
			$data['contact']  = Contacts::model()->findByPk($contact_id);
			
			// ------------------------------ get items
			$data['items']   = array();
			$data['items']   = $this->getStockItemsByQuote($quote_id);
			$data['selects'] = Quotes::model()->getAllSelects();
			//pDebug("actionUpdate() - selects=",$data['selects'] );

			$data['model']   = $this->loadModel($quote_id);
			$data['sources'] = Sources::model()->findAll( array('order' => 'name') );
			$data['status']  = Status::model()->findAll();

			$tmp = Terms::model()->findAll();
			foreach( $tmp as $tc ) {
				$data['terms_select'][$tc->id] = $tc->name;
			}

			$criteria =  new CDbCriteria();
			$criteria->addCondition("quote_id = $quote_id");
			$data['BtoItems_model'] = BtoItems::model()->find( $criteria );

			$this->render('update',array(
				'data'=>$data,
			));
		}
	}  // END_OF_FUNCTION actionUpdate()


	public function actionUpdate($id) 	{
		pPOST('QuotesController::actionUpdate()');
		pGET('QuotesController::actionUpdate()');

		$quote_id   = $id;
		$quoteModel = $this->loadModel($quote_id);
		$myQuote    = $quoteModel->owner_id == Yii::app()->user->id ? true : false;

		if (  !$myQuote && !Yii::app()->user->isAdmin ) { // no edits allowed outside of owner and Admin
			return;
		}
		
		if ( isset( $_POST['Quotes'] ) ) {  
			if ( $_POST['Quotes']['source_id'] == 0 ) {
				pDebug("QuotesController::actionUpdate() - validation error; missing Opportunity Source.");
				echo Status::FAILURE;
				return;
			}

			// update BTO inventory item
			if ( $_POST['quoteTypeID'] == QuoteTypes::MANUFACTURING ) {
				if ( isset($_POST['BtoItems']) ) {
					$item_id = $_POST['BtoItems']['id'];

					try { 
						pDebug("Quotes::actionUpdate() - saving item_id:[$item_id] for Manufacturing quote: [$id]");

						$itemsModel                        = BtoItems::model()->findByPk($item_id);
						$itemsModel->quote_id              = $quote_id;
						$itemsModel->order_probability_id  = $_POST['BtoItems']['order_probability_id'];
						$itemsModel->requested_part_number = $_POST['BtoItems']['requested_part_number'];
						$itemsModel->generic_part_number   = $_POST['BtoItems']['generic_part_number'];
						$itemsModel->quantity              = str_replace( array(','), "", $_POST['BtoItems']['quantity'] );  // remove commas
						$itemsModel->die_manufacturer_id   = $_POST['BtoItems']['die_manufacturer_id'];
						$itemsModel->package_type_id       = $_POST['BtoItems']['package_type_id'];
						$itemsModel->lead_count            = $_POST['BtoItems']['lead_count'];
						$itemsModel->process_flow_id       = $_POST['BtoItems']['process_flow_id'];
						$itemsModel->testing_id            = $_POST['BtoItems']['testing_id'];
						$itemsModel->ncnr                  = $_POST['BtoItems']['ncnr'];
						$itemsModel->wip_product           = $_POST['BtoItems']['wip_product'];
						$itemsModel->line_note             = $_POST['BtoItems']['line_note'];

						$itemsModel->number_of_years       = $_POST['BtoItems']['number_of_years'];
						$itemsModel->expected_close_date   = Date('Y-m-d 00:00:00', strtotime($_POST['BtoItems']['expected_close_date']) );  

						pDebug("Quotes::actionUpdate() - item attributes:", $itemsModel->attributes );
					
						if ( $itemsModel->save() ) {
							pDebug("Quotes::actionUpdate() - Manufacturing quote saved.");
							echo Status::SUCCESS;
							return;
						}
						else {
							pDebug("Quotes::actionUpdate() - Error: manufacturing quote NOT saved, error=", $itemsModel->errors);
							echo $itemsModel->errors;
							return;
						}
					}
					catch( Exception $ex ) {
						pDebug("actionUpdate() - Exception: ", $ex );
						echo Status::FAILURE;
						return;
					}
				}
			}
			else if ( $_POST['quoteTypeID'] == QuoteTypes::STOCK || $_POST['quoteTypeID'] == QuoteTypes::TBD) {
				try {
					// save Customer information
					$c = $_POST['Customers'];
					pDebug("Customer _POST values for id: " . $c['id'], $c);

					if ( $c['id'] ) {
						$customerModel = Customers::model()->findByPk( $c['id'] );
						pDebug("Customers model attributes BEFORE values: ", $customerModel->attributes );
					}
					else {
						pDebug("Customers model - new.");
						$customerModel = new Customers;
					}

					$customerModel->attributes = $c;
					pDebug("Customer model attributes AFTER values: ", $customerModel->attributes );
					
					if ( $customerModel->save() ) {
						pDebug("Customer model saved with updated values." );
					}
					else {
						pDebug("Customer model NOT updated; error=", $customerModel->errors );
						echo Status::FAILURE;
						return;
					}

					// save Contact information
					$c = $_POST['Contacts'];
					pDebug("Contacts _POST values for id: " . $c['id'], $c);

					if ( $c['id'] ) {
						$contactModel = Contacts::model()->findByPk( $c['id'] );
						pDebug("Contacts model attributes BEFORE values: ", $contactModel->attributes );
					}
					else {
						pDebug("Contacts model - new.");
						$contactModel = new Contacts;
					}

					$contactModel->attributes = $c;
					pDebug("Contacts model attributes AFTER values: ", $contactModel->attributes );
					
					if ( $contactModel->save() ) {
						pDebug("Contacts model saved with updated values." );
					}
					else {
						pDebug("Contacts model NOT updated; error=", $contactModel->errors );
						echo Status::FAILURE;
						return;
					}

					// save quote terms
					$q = $_POST['Quotes'];
					pDebug("Quotes _POST values for id: " . $q['id'], $q);

					$quoteModel = Quotes::model()->findByPk($q['id']);
					pDebug("Quotes model attributes BEFORE values: ", $quoteModel->attributes );

					$quoteModel->attributes = $q;
					pDebug("Quotes model attributes AFTER values: ", $quoteModel->attributes );

					if ( $quoteModel->save() ) {
						pDebug("Quotes model saved with updated values." );
					}
					else {
						pDebug("Quotes model NOT updated; error=", $quoteModel->errors );
						echo Status::FAILURE;
						return;
					}
				}
				catch( Exception $ex ) {
					pDebug("Quotes:actionDelete() - Exception caught: ",  $ex->errorInfo  );
					echo Status::FAILURE;
				}

				echo Status::SUCCESS;
				return;
			}
		}
		else {
			$data['model'] = $this->loadModel($quote_id);
			$customer_id   = $data['model']->customer_id;
			$contact_id    = $data['model']->contact_id;

			// ------------------------------ get customer
			$data['customer'] = Customers::model()->findByPk($customer_id);
			
			// ------------------------------ get contact
			$data['contact']  = Contacts::model()->findByPk($contact_id);
			
			// ------------------------------ get items
			$data['items']   = array();
			$data['items']   = $this->getStockItemsByQuote($quote_id);
			$data['selects'] = Quotes::model()->getAllSelects();
			//pDebug("actionUpdate() - selects=",$data['selects'] );

			$data['model']   = $this->loadModel($quote_id);
			$data['sources'] = Sources::model()->findAll( array('order' => 'name') );
			$data['status']  = Status::model()->findAll();

			$tmp = Terms::model()->findAll();
			foreach( $tmp as $tc ) {
				$data['terms_select'][$tc->id] = $tc->name;
			}

			$criteria =  new CDbCriteria();
			$criteria->addCondition("quote_id = $quote_id");
			$data['BtoItems_model'] = BtoItems::model()->find( $criteria );

			$this->render('update',array(
				'data'=>$data,
			));
		}

	}  // END_OF_FUNCTION actionUpdate()



	// ------- private functions        
	private function getTimeStamp() {
		$u = Yii::app()->user->fullname;
		$t = Date('D Y-m-d h:i:s');

		return "[$t] $u";
	}

	private function getUpdatedQuoteStatus( $quote_id ) {
		if ( Yii::app()->user->isAdmin || Yii::app()->user->isProposalManager ) { 
			return null;
		}

		// any Rejected items?
		$rejected_items = getRejectedItemCount($quote_id);

		// any Pending items?
		$pending_items = getPendingItemCount($quote_id);

		if ( !$rejected_items && !$pending_items ) {  // set back to Draft
			$new_quote_status = Status::DRAFT;
		}
		
		if ($rejected_items) {
			$new_quote_status = Status::REJECTED;
		}

		// Pending trumps Rejected
		if ($pending_items) { 						 // set back to Pending
			$new_quote_status = Status::PENDING;
		}

		return $new_quote_status;
	}

	
	private function fixDateDisplay($d) {
		// convert "Apr 13 2015 12:00:00:000AM" into "Apr.13.2015"
		return Date('M.d.Y', strtotime($d));
	}



	// -------------------------------------------------
	private function formatData_MfgQuote( $m ) {
		$data          = array();
		$modelCustomer = Customers::model()->findByPk($m->customer_id);  
		
		// start filling $data for pdf
		$data['terms_conditions']        = $m->termsConditions->data;
		$data['expiration_date']         = $m->expiration_date;
		$data['additional_notes']        = $m->additional_notes;
		$data['customer_acknowledgment'] = $m->customer_acknowledgment;
		$data['risl']                    = $m->risl;
		//$data['terms']                   = $tc->content;
		$data['manufacturing_lead_time'] = $m->manufacturing_lead_time;
		$data['quote_no']                = $m->quote_no;
		$data['quote_id']                = $m->id;
		$data['quote_type_id']           = $m->quote_type_id;
		$data['status_id']               = $m->status_id;
		$data['status_name']             = $m->status->name;

		$st                              = UsStates::model()->findByPk($modelCustomer->state_id);
		$co                              = Countries::model()->findByPk($modelCustomer->country_id); 
		$data['customer']['name']        = $modelCustomer->name;
		$data['customer']['address1']    = $modelCustomer->address1;
		$data['customer']['address2']    = $modelCustomer->address2;
		$data['customer']['city']        = $modelCustomer->city;
		$data['customer']['state']       = $st->short_name;
		$data['customer']['zip']         = $modelCustomer->zip;
		$data['customer']['country']     = $co->long_name;
		$data['customer']['quote_id']    = $id;

		$data['customer']['contact']['name']   = $m->contact->fullname; 
		$data['customer']['contact']['email']  = $m->contact->email; 
		$data['customer']['contact']['phone1'] = $m->contact->phone1;
		$data['customer']['contact']['phone2'] = $m->contact->phone2; 
	
		$u = Users::model()->findByPk($m->owner_id);
		$data['profile']['name']  = $u->fullname;
		$data['profile']['title'] = $u->title;
		$data['profile']['phone'] = $u->phone;
		$data['profile']['fax']   = $u->fax;
		$data['profile']['email'] = $u->email;
		$data['profile']['sig']   = $u->sig;

		$data['items'] = getQuoteItems($m);
		$data['bto']   = getBtoItemDetails($m);

		pDebug("formatData_MfgQuote() - items:", $data['items']);
		pDebug("formatData_MfgQuote() - bto:", $data['bto']);

		return $data;
	}


	// -------------------------------------------------
	private function formatData_StockQuote( $m ) {
		$data          = array();
		$modelCustomer = Customers::model()->findByPk($m->customer_id);  

		// start filling $data for pdf
		$data['terms_conditions']        = $m->termsConditions->data;
		$data['expiration_date']         = $m->expiration_date;
		$data['additional_notes']        = $m->additional_notes;
		$data['customer_acknowledgment'] = $m->customer_acknowledgment;
		$data['risl']                    = $m->risl;
		//$data['terms']                   = $tc->content;
		$data['manufacturing_lead_time'] = $m->manufacturing_lead_time;
		$data['quote_no']                = $m->quote_no;
		$data['status_id']               = $m->status_id;

		$st                              = UsStates::model()->findByPk($modelCustomer->state_id);
		$co                              = Countries::model()->findByPk($modelCustomer->country_id); 
		$data['customer']['name']        = $modelCustomer->name;
		$data['customer']['address1']    = $modelCustomer->address1;
		$data['customer']['address2']    = $modelCustomer->address2;
		$data['customer']['city']        = $modelCustomer->city;
		$data['customer']['state']       = $st->short_name;
		$data['customer']['zip']         = $modelCustomer->zip;
		$data['customer']['country']     = $co->long_name;
		$data['customer']['quote_id']    = $id;

		$data['customer']['contact']['name']   = $m->contact->fullname; 
		$data['customer']['contact']['email']  = $m->contact->email; 
		$data['customer']['contact']['phone1'] = $m->contact->phone1;
		$data['customer']['contact']['phone2'] = $m->contact->phone2; 
	
		$u = Users::model()->findByPk($m->owner_id);
		$data['profile']['name']  = $u->fullname;
		$data['profile']['title'] = $u->title;
		$data['profile']['phone'] = $u->phone;
		$data['profile']['fax']   = $u->fax;
		$data['profile']['email'] = $u->email;
		$data['profile']['sig']   = $u->sig;

		$data['items'] = getQuoteItems($m); 
		// pDebug("formatData_StockQuote() - items:", $data['items']);

		return $data;
	}
  


	// -----------------------------------------------------------------------------
	private function getBtoItemsByQuote( $quote_id ) {
		$sql = "SELECT * FROM bto_items WHERE  quote_id = $quote_id";
		$command = Yii::app()->db->createCommand($sql);
		$items = $command->queryAll();

		pDebug("Quotes::getStockItemsByQuote() - items:", $items );
		return $items;
	}



	// -----------------------------------------------------------------------------
	private function getStockItemsByQuote( $quote_id ) {
		$sql = "SELECT * FROM stock_items WHERE  quote_id = $quote_id";
		$command = Yii::app()->db->createCommand($sql);
		$results = $command->queryAll();

		foreach( $results as $i ) {
			// pDebug('Quotes:getStockItemsByQuote() - results from stock_items:', $i );
			foreach( array('1_24', '25_99', '100_499', '500_999', '1000_Plus', 'Base', 'Custom') as $v ) {
				if ( fq($i['qty_'.$v]) != '0' ) {

					// TODO - refactor
					if ( $i['lifecycle_id'] == Lifecycles::ACTIVE ) {
						$lifecycle = 'Active';
					}
					else if ( $i['lifecycle_id'] == Lifecycles::OBSOLETE ) {
						$lifecycle = 'Obsolete';
					} 
					else {
						$lifecycle = 'n/a';
					}
		 			
		 			$items[] = array( 	"id"            	=> $i['id'], 
 										"available"     	=> $i['qty_Available'], 
 										"status_id"         => $i['status_id'], 
 										"part_no"       	=> $i['part_no'], 
 										"lifecycle"     	=> $lifecycle,
 										"manufacturer"  	=> $i['manufacturer'], 
 										"date_code"     	=> $i['date_code'], 
 										"bin"     	        => $i['bin'], 
 										"qty"           	=> fq($i["qty_$v"]), 
 										"volume"        	=> $v, 
 										"price"         	=> fp($i["price_$v"]), 
 										"total"         	=> fp(calc($i,$v)), 
 										"line_note"      	=> $i['line_note'] );
		 		}
			}
		}

		pDebug('Quotes::getStockItemsByQuote() - final items:', $items );
		return $items;
	}



	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id) 	{
		pTrace( __METHOD__ );
		pDebug("QuotesController::actionDelete() - _GET=", $_GET);
		pDebug("QuotesController::actionDelete() - _POST=", $_POST);

		if ( $_POST['data'][0] == $id ) {

			try {
				if ( $this->loadModel($id)->delete() ) {
					pDebug("Quotes:actionDelete() - quote id $id deleted...");
					echo Status::SUCCESS;
				}
				else {
					pDebug("Quotes:actionDelete() - ERROR: can't delete quote id $id; error=", $model->errors  );
					echo Status::FAILURE;
				}
			}
			catch (Exception $ex) {
				pDebug("Quotes:actionDelete() - Exception caught: ",  $ex->errorInfo  );
				echo Status::FAILURE;
			}
		}
		return;
	}

	public function actionIndexApproval() 	{
		pTrace( __METHOD__ );
		pDebug('actionIndexApproval() - _GET=', $_GET);

		$criteria = new CDbCriteria();

		if ( Yii::app()->user->isApprover || Yii::app()->user->isAdmin ) {
			$page_title = "Stock Quotes Needing Approval";
			$criteria->addCondition("status_id = " . Status::PENDING);
			$criteria->addCondition("quote_type_id = " . QuoteTypes::STOCK);

			$criteria->order = 'id DESC';
			$model = Quotes::model()->findAll( $criteria );

			$this->render( 'index', array(
				'model' => $model,
				'page_title' => $page_title,
			));
		}
		else {

		}
	}


	private function findMyMfgQuotes($pending_only=null) {
		$my_id        = Yii::app()->user->id;
		$my_quotes    = array();
		
		$coordinator_ids = array();

		$criteria = new CDbCriteria();
		$criteria->addCondition("quote_type_id = " . QuoteTypes::MANUFACTURING);
		if ( $pending_only ) {
			$criteria->addCondition("status_id = " . Status::PENDING);
		}
		$quoteModel = Quotes::model()->findAll( $criteria );

		foreach( $quoteModel as $quote ) {
			foreach( $quote->btoItems as $item ) {
				foreach( $item->btoItemStatuses as $stat ) {
					pDebug( $quote->id . ": stat->coordinator_id: [" . $stat->coordinator_id . "], myID=[$my_id]");

					if ( $my_id == $stat->coordinator_id ) {
						$my_quotes[] = $quote->id;
					}
				}
			}
			
		}

		pDebug("Quote ids: ", $my_quotes);
		return $my_quotes;
	}



	public function actionMyPending() 	{
		pTrace( __METHOD__ );
		pDebug('actionMyPending() - _GET=', $_GET);

		$quote_type = QuoteTypes::MANUFACTURING;
		$page_title = "My Pending Manufacturing Quotes";

		$my_quotes = $this->findMyMfgQuotes();
		if ( count($my_quotes) > 0 ) {

			$criteria = new CDbCriteria();
			$criteria->addCondition("quote_type_id = " . $quote_type ); 
			$criteria->addCondition("id IN (" .  implode(",", $my_quotes)  . ")" ); 

			$criteria->order = 'id DESC';
			$model = Quotes::model()->findAll( $criteria );
			$this->render( 'index', array(
				'quote_type' => $quote_type,
				'model'      => $model,
				'page_title' => $page_title,
			));

		}
		else {
			$this->render( 'no_pending' );
		}
	}




	public function actionIndex() 	{
		pTrace( __METHOD__ );
		pDebug('actionIndex() - _GET=', $_GET);

		$criteria = new CDbCriteria();
		
		if ( !Yii::app()->user->isAdmin ) {
			if (Yii::app()->user->isProposalManager || Yii::app()->user->isCoordinator) {

				$quote_type = QuoteTypes::MANUFACTURING;
				$page_title = "Manufacturing Quotes";

				if ( isset($_GET['my']) ) {
					$my_quotes = $this->findMyMfgQuotes();
					if ( count($my_quotes) > 0 ) {
						$criteria->addCondition("id IN (" .  implode(",", $my_quotes)  . ")" ); 
					}
				}
				
				$criteria->addCondition("quote_type_id = " . $quote_type ); 
			}
			else {
				$page_title = "My Quotes";
				$criteria->addCondition("owner_id = " . Yii::app()->user->id);
			}
		}
		else {
			$page_title = "All Stock & Manufacturing Quotes";
		}
		
		$criteria->order = 'id DESC';
		$model = Quotes::model()->findAll( $criteria );

		$this->render( 'index', array(
			'quote_type' => $quote_type,
			'model'      => $model,
			'page_title' => $page_title,
		));
	}



	public function actionManufacturing() {
		pTrace( __METHOD__ );
		pDebug('actionManufacturing() - _GET=', $_GET);

		$quote_type = QuoteTypes::MANUFACTURING;
		$status     = Status::PENDING;

		$criteria = new CDbCriteria();

		if ( Yii::app()->user->isProposalManager || Yii::app()->user->isAdmin ) {
			$page_title = "Mfg Quotes Needing Approval";
			$criteria->addCondition("quote_type_id = $quote_type");
			$criteria->addCondition("status_id = $status");

			$criteria->order = 'id DESC';
			$model = Quotes::model()->findAll( $criteria );

			$this->render( 'index', array(
				'quote_type' => $quote_type,
				'model'      => $model,
				'page_title' => $page_title,
			));
		}
		else {

		}

	}


	// isCoordinator
	public function actionCoordinator() {
		pTrace( __METHOD__ );
		pDebug('actionCoordinator() - _GET=', $_GET);

		if ( Yii::app()->user->isCoordinator || Yii::app()->user->isAdmin ) { 
			$quote_type = QuoteTypes::MANUFACTURING;
			$status     = Status::PENDING;
			$page_title = "Manufacturing Quotes";

			$my_id = Yii::app()->user->id;
			$sql = <<<EOT

SELECT 
		q.id, 
		q.quote_no,      
		qt.name as quote_type, 
		s.name as status,
		l.name as level, 
		concat(u.first_name, ' ', u.last_name) as owner_name,  
		cu.name as customer_name, 
		concat(con.first_name, ' ', con.last_name) as contact_name
  FROM
  		quotes AS q
  			JOIN bto_messages  m ON m.quote_id  = q.id 
  			JOIN coordinators  a ON a.user_id   = m.to_user_id
  			JOIN users u  		 ON u.id        = m.to_user_id
  			JOIN levels l 		 ON l.id        = q.level_id
  			JOIN quote_types qt  ON qt.id       = q.quote_type_id
  			JOIN customers cu    ON cu.id  = q.customer_id
  			JOIN contacts con    ON con.id = q.contact_id
  			JOIN status s        ON s.id   = q.status_id
 WHERE
 		m.to_user_id = $my_id

EOT;

			$command = Yii::app()->db->createCommand($sql);
			$results = $command->queryAll();
			pDebug("results=", $results);

			$this->render( 'index_coordinator', array(
				'quote_type' => $quote_type,
				'model'      => $results,
				'page_title' => $page_title,
			));
		}

	}

	// &&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&
	//	  needs to be refactored (see below)
	// &&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&
	public function actionAddMessage() {
		pDebug("Quotes::actionAddMessage() - _POST=", $_POST); 
	
		if ( $_POST['quoteID'] === '' || $_POST['text_Subject'] === '' || $_POST['text_Message'] === '' ) {
			pDebug("Quotes::actionAddMessage() - missing _POST variables...");
			echo Status::FAILURE;
		}

		try {
			$criteria = new CDbCriteria();
			$criteria->addCondition("quote_id = " . $_POST['quoteID'] );
			
			$modelItems =  BtoItems::model()->find($criteria);
			// $modelItems->save();
			// pDebug("Quotes::actionAddMessage() - modelItems->save()");

			// save comment
			$modelMessages = new BtoMessages;
			$modelMessages->quote_id     = $_POST['quoteID'];
			$modelMessages->bto_item_id  = $modelItems->id;
			$modelMessages->from_user_id = Yii::app()->user->id;
			$modelMessages->to_user_id   = Yii::app()->user->id;  // TODO: required field; adding a message for all
			$modelMessages->subject      = $_POST['text_Subject'];
			$modelMessages->message      = $_POST['text_Message'];
			$modelMessages->save();
			pDebug("Quotes::actionAddMessage() - modelMessages->save()");

		}
		catch( Exception $ex ) {
			pDebug("Quotes::actionAddMessage() - Exception: ", $ex );
			echo Status::FAILURE;
		}
		echo Status::SUCCESS;
	}


	// &&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&
	//    needs to be refactored (see above)
	// &&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&
	public function actionAddInternalMessage() {
		pDebug("Quotes::actionAddInternalMessage() - _POST=", $_POST); 

		try {
			$criteria = new CDbCriteria();
			$criteria->addCondition("quote_id = " . $_POST['quoteID'] );
			
			$modelItems = BtoItems::model()->find($criteria);
			$item_id    = $modelItems->id;
			$quote_id   = $_POST['quoteID'];
			$my_id      = Yii::app()->user->id;
			$subject    = $_POST['text_Subject'];
			$message    = $subject;

			// save message
			$modelMessages = new BtoMessages;
			$modelMessages->quote_id     = $quote_id;
			$modelMessages->bto_item_id  = $item_id;
			$modelMessages->from_user_id = $my_id;
			$modelMessages->subject      = $subject;
			$modelMessages->message      = $message;
			
			if ( $modelMessages->save() ) {
				pDebug("Quotes::actionAddInternalMessage() - message added.");
				echo Status::SUCCESS;
				return;
			}
			else {
				pDebug("Quotes::actionAddInternalMessage() - Couldn't add message; error=", $modelMessages->errors);
				echo Status::FAILURE;
				return;
			}

		}
		catch( Exception $ex ) {
			pDebug("Quotes::actionAddInternalMessage() - Exception: ", $ex );
			echo Status::FAILURE;
		}
		
	}


	public function actionNotifyCoordinators() {
		pDebug("Quotes::actionNotifyCoordinators() - _POST=", $_POST); 
		
		$toBeNotified = array();
		foreach( array( $_POST['coordinator_Assembly'],$_POST['coordinator_Test'],$_POST['coordinator_Quality'] ) as $id ) {
			if ( $id ) {
				$toBeNotified[] = $id;
			}
		}
		pDebug("Quotes::actionNotifyCoordinators() - to be notified:", $toBeNotified);

		if ( $_POST['quoteID'] === '' || $_POST['text_Subject'] === '' || $_POST['text_Message'] === '' || count($toBeNotified) === 0 ) {
			pDebug("Quotes::actionNotifyCoordinators() - missing _POST variables...");
			echo Status::FAILURE;
		}

		try {
			$criteria = new CDbCriteria();
			$criteria->addCondition("quote_id = " . $_POST['quoteID'] );
			
			$modelItems =  BtoItems::model()->find($criteria);
			$modelItems->coordinators_notified = true;
			$modelItems->save();

			$item_id = $modelItems->id;
			$group_coordinators['item_id']           = $item_id;
			$group_coordinators[Groups::ASSEMBLY] = $_POST['coordinator_Assembly'];
			$group_coordinators[Groups::TEST]     = $_POST['coordinator_Test'];
			$group_coordinators[Groups::QUALITY]  = $_POST['coordinator_Quality'];
			$this->updateBtoItemStatus($group_coordinators);

			// save comment
			foreach( $toBeNotified as $user_id ) {
				$modelMessages = new BtoMessages;
				$modelMessages->quote_id     = $_POST['quoteID'];
				$modelMessages->bto_item_id  = $item_id;
				$modelMessages->from_user_id = Yii::app()->user->id;
				$modelMessages->to_user_id   = $user_id;
				$modelMessages->subject      = $_POST['text_Subject'];
				$modelMessages->message      = $_POST['text_Message'];
				$modelMessages->save();
				notifyCoordinator($modelMessages);
			}
			$modelQuotes = Quotes::model()->findByPk( $_POST['quoteID'] );
			$modelQuotes->approval_process_id = ApprovalProcess::STARTED;
			if ( $modelQuotes->save() ) {
				pDebug("actionNotifyCoordinators() - process approval stage set to: " . $modelQuotes->approvalProcess->name );
			}
			else {
				pDebug("actionNotifyCoordinators() - ERROR: couldn't set process approval stage; error=", $modelQuotes->errors );
			}

		}
		catch( Exception $ex ) {
			pDebug("Quotes::actionNotifyCoordinators() - Exception: ", $ex );
			echo Status::FAILURE;
		}
		echo Status::SUCCESS;
	}


	// -------------------------------------------------
	private function updateBtoItemStatus( $g ) {
		foreach( array(Groups::ASSEMBLY, Groups::TEST, Groups::QUALITY) as $g_id ) {
		
			$model = new BtoItemStatus;
			$model->bto_item_id		= $g['item_id'];
			$model->status_id 		= Status::PENDING; 
			$model->group_id 		= $g_id;
			$model->coordinator_id 	= $g[$g_id];
			$model->save();

			pDebug('updateBtoItemStatus() - model->attributes saved: ', $model->attributes );
		}
	}

	

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$model=new Quotes('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['Quotes']))
			$model->attributes=$_GET['Quotes'];

		$this->render('admin',array(
			'model'=>$model,
		));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return Quotes the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=Quotes::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param Quotes $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='quotes-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

	
	// --------------------------------------------------------
	private function getQtyPriceTotal( $i ) { 
		$data = '<table id="table_Parts">';
		if ( fq($i['qty_1_24']) != '0' ) {
 			$data .=  "<tr>  <td> ".fq($i['qty_1_24'])."</td>        <td><span class='volume'>1-24</span>"      .fp($i['price_1_24'])."</td>      <td> ".fp(calc($i,'1_24'))."</td>   </tr>"; 
 		}

 		if ( fq($i['qty_25_99']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_25_99'])."</td>        <td><span class='volume'>25-99</span>"     .fp($i['price_25_99'])."</td>     <td> ".fp(calc($i,'25_99'))."</td>   </tr>"; 
 		}

 		if ( fq($i['qty_100_499']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_100_499'])."</td>      <td><span class='volume'>100-499</span>"   .fp($i['price_100_499'])."</td>   <td> ".fp(calc($i,'100_499'))."</td>   </tr>"; 
 		}

 		if ( fq($i['qty_500_999']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_500_999'])."</td>      <td><span class='volume'>500-999</span>"   .fp($i['price_500_999'])."</td>   <td> ".fp(calc($i,'500_999'))."</td>   </tr>"; 
 		}

 		if ( fq($i['qty_1000_Plus']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_1000_Plus'])."</td>    <td><span class='volume'>1000+</span>"     .fp($i['price_1000_Plus'])."</td> <td> ".fp(calc($i,'1000_Plus'))."</td>   </tr>"; 
 		}

		if ( fq($i['qty_Base']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_Base'])."</td>    <td><span class='volume'>Base</span>"     .fp($i['price_Base'])."</td> <td> ".fp(calc($i,'Base'))."</td>   </tr>"; 
 		}

		if ( fq($i['qty_Custom']) != '0' ) {
 			$data .=  "<tr> <td> ".fq($i['qty_Custom'])."</td>    <td><span class='volume'>Custom</span>"     .fp($i['price_Custom'])."</td> <td> ".fp(calc($i,'Custom'))."</td>   </tr>"; 
 		}
 		$data .= "</table>";

 		return $data;
	}


	// --------------------------------------------------------
	private function getQuoteNumber() {
		$id = Yii::app()->db->createCommand()->select('max(id) as max')->from('quotes')->queryScalar() + 1;
		$id = $id ? $id : 1;
		return Date('Ymd-') . sprintf("%04d", $id);
	}


	// -----------------------------------------------------------
    private function getQuoteExpirationDate() {
        // quote expiration 30 days from today
        $exp = "+30 days";
        return Date( 'Y-m-d 00:00:00', strtotime($exp) );
    }

}
