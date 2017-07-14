$(document).ready(function() {

	var customerPrefill = true;
	var contactPrefill  = true;
	var myURL		   = '/iq2/index.php/';

	var dialog_PartPricing = '';
	var QuotesTable		= '';
	var CustomersTable	 = '';
	var PartsTable		 = '';
	var tabs			   = '';

	var SUCCESS	  = 0; 
	var FAILURE	  = 1;

	var STATUS_DRAFT   = 1;
	var STATUS_PENDING = 2;

	var ItemPricing	= new Object({});


	// quick and dirty function for truncating text with ellipses...
	String.prototype.trunc = function(n) { return this.substr(0,n-1)+(this.length>n?'&hellip;':''); };
	function truncateWithEllipses(text, max) {
		return text.substr(0,max-1)+(text.length>max?'&hellip;':''); 
	}

	$(function() {
		$( "#BtoItems_expected_close_date" ).datepicker();
	});


	new Clipboard('#copy_btn');

	// ---------- set up Accordian 
	$(function() {
		$( "#coordinators_accordion" ).accordion({
		  collapsible: true,
		  heightStyle: "content"
		});
	});
	

	// ---------- set up Tabs 
	$(function() {
		$( "#QuoteView_Tabs" ).tabs({ });
	});

	last_tab_value = getCookie('tab');
	if ( last_tab_value == 0 ) {
		deleteCookie('tab');
		last_tab_value = getCookie('tab');
	}
	console.log("last tab value ["+last_tab_value+"]");
	

	$('#QuoteView_Tabs').on('tabsactivate', function(event, ui) {
		var newIndex = ui.newTab.index();
		$('#item_details').hide();
		$('[id^=item_row_]').find('td').removeClass('highlight_row'); 
	});

	if ( quoteTypeID == MANUFACTURING_QUOTE ) {
		showManufacturingTabs();
	}
	else {
		showStockTabs();
	}


	if ( last_tab_value != null ) {
		$('#QuoteView_Tabs').tabs({ active: last_tab_value });
	}



	// -----------------------------------------------
	// ---------- set up UI Dialogs ------------------
	// -----------------------------------------------
	if ($("#form_PartPricing").length == 1) {
		dialog_PartPricing = $( "#form_PartPricing" ).dialog({
			autoOpen: false,
			// height: 1100,
			width: 500,
			modal: true,
			resizable: false,
			close: function() { }
		});
	}

	var dialog_SalesHistory = $( "#form_SalesHistory" ).dialog({
			autoOpen: false,
			height: 500,
			width: 1300,
			modal: true,
			resizable: false,
			close: function() { }
	});
	
	var dialog_QuoteHistory = $( "#form_QuoteHistory" ).dialog({
			autoOpen: false,
			height: 500,
			width: 1300,
			modal: true,
			resizable: false,
			close: function() { }
	});

	var dialog_AttachFiles = $( "#form_Attachfiles" ).dialog({
			autoOpen: false,
			height: 500,
			width: 400,
			modal: true,
			resizable: false,
			close: function() { }
	});

	var dialog_PartStockCodes = $( "#form_PartStockCodes" ).dialog({
			autoOpen: false,
			height: 'auto',
			width: 750,
			modal: true,
			resizable: false,
			close: function() { }
	});

	$('#reset_form').on('click', function() {
		location.reload();
	});

	$('#add_quote').on('click', function() {
		window.location = myURL + 'quotes/create' ;
	});

  
	QuotesTable	= $('#quotes_table').DataTable({"iDisplayLength": 10, "destroy": true, "oLanguage": { "sSearch": "Filter: "} }); 
	CustomersTable = $('#table_cust_contact_lookup').DataTable({"iDisplayLength": 10, "destroy": true,  "oLanguage": { "sSearch": "Filter: "} }); 
	PartsTable	 = $('#table_inventory_part_lookup').DataTable({"iDisplayLength": 10, "destroy": true,  "oLanguage": { "sSearch": "Filter: "} }); 

	var itemID = $('#itemID').val();


	// -------- Event Handlers  -------------

	$('#new_customer').on('click', function() {
		console.log("creating a new customer");
		$('#section_CustomerContact').removeClass('hide_me');
		$('#new_customer').hide();
	});

	$('#reset_intro').on('click', function() {
		$('#ta_letter_intro').val( $('#letter_intro_default').val() );
	});

	$('#reset_conclusion').on('click', function() {
		$('#ta_letter_conclusion').val( $('#letter_conclusion_default').val() );
	});

	$('#button_CancelConfigChanges').on('click', function() { 
		window.location = myURL + 'quotes';
	});

	$('#button_SaveConfigChanges').on('click', function() {
		var intro	  = $('#ta_letter_intro').val();
		var conclusion = $('#ta_letter_conclusion').val();

		var postData = { intro: intro, conclusion: conclusion };
		console.log('postData=' +  JSON.stringify(postData) );

		var url = myURL + 'site/updateConfiguration';

		$.ajax({
			url: url,
			type: 'POST',
			data: postData,
			success: function(results)  {
				if ( results == SUCCESS ) {
					console.log('Configuration updated...');
					location.reload();
				}
				else {
					alert('Could not update configuration - see Admin.');
				}
			}


		});

	});


	$('[id^=letter_]').on('click', function() {
		var tmp	=  $(this).attr('id');
		var match  = /^letter_(\w+)_(\w+)$/.exec(tmp);

		// [0] = letter_intro_custom
		// [1] = intro, conclusion, signature
		// [2] = custom, default

		var section	= RegExp.$1;
		var custom = RegExp.$2 === 'custom' ? true : false;
		console.log('section: ['+section+'], custom? '+custom);

		if ( section == 'signature' && custom ) {
			var return_URL = $('#return_URL').val();
			var url =  myURL + 'Users/profileUpdate' + '?returnUrl=' + return_URL;
			console.log('url=' + url);
			window.location = url;
		}
		else {
			$('#ta_letter_'+section).removeAttr('readOnly');
			$('#ta_letter_'+section).css('background-color', '#ffff8c');
		}
	});


	$('#customizeLetter').on('click', function() {
		$('#div_letter_customization').toggle();
	});


	$('#lookup_by_select').on('change', function() {
		if ( $('#lookup_by_select').val() == 'customer'  ) {
			$('#span_lookup_text').show();
		}
		else if ( $('#lookup_by_select').val() == 'part'  ) {
			$('#span_lookup_text').show();
		}
		else {
			$('#span_lookup_text').hide();
			$('#div_inventory_part_lookup').hide();
			$('#div_cust_contact_lookup').hide();
		}
	});


  

	$('#find_text_button').on('click', function() {
		var lookup_type = $('#lookup_by_select').val();   // customer, part
		var lookup_text = $('#lookup_text').val();

		var last_lookup	= getCookie('lookup');
		var current_lookup = lookup_type + "|" + lookup_text;

		console.log('   last_lookup: [' + last_lookup + "]");
		console.log('current_lookup: [' + current_lookup + "]");

		var lurl = '';
		if ( lookup_type == 'customer' ) {
			lurl =  myURL + 'tools/customerLookup';
		}
		else if ( lookup_type == 'part' ) {
			lurl =  myURL + 'tools/partLookup';
		}
		else {
			return false;
		}

		var postData = { lookup_text: lookup_text };
		console.log('postData=' +  JSON.stringify(postData) );
		$('#ajax_loading_image').show();

		$.ajax({
			type: 'POST',
			url: lurl,
			data: postData,
			success: function (data) {
				var o = JSON.parse(data); 
				$('#ajax_loading_image').hide();

				if ( /customerLookup$/.exec(lurl) ) {
					$('#div_cust_contact_lookup').show();
					$('#div_inventory_part_lookup').hide();

					CustomersTable.clear().draw();
					$.each( o,  function( index, arr ) {
						CustomersTable.row.add( arr ).draw( false );
					});
				}
				else {
					$('#div_inventory_part_lookup').show();
					$('#div_cust_contact_lookup').hide();

					PartsTable.clear().draw();
					$.each( o,  function( index, arr ) {
						PartsTable.row.add( arr ).draw( false );
					});
				}
			   
				$('#table_cust_contact_lookup > tbody > tr').on('click', function() {
					$('#table_cust_contact_lookup > tbody > tr').each( function() {
						$(this).css('background-color','');
					});

					$(this).css('background-color', '#D6F9FB');	   
				});

				setupOnRowClick();
			},
			error: function (jqXHR, textStatus, errorThrown)  {
				console.log("Couldn't search; error=\n\n" + errorThrown);
				dialog_PartStockCodes.dialog( "close" );
			}
		});
	});


	
	$('#needMoreInfo').on('click', function() { 
		console.log("Asking for more info...");

		if ( !$.trim($('#needMoreInfo_Message').val()) ) {
			alert("Can't send a blank message.");
			return false;
		}

		var quoteID  = $('#Quotes_id').val(); 
		var postData = { msg: $('#needMoreInfo_Message').val(), quoteID: quoteID };

		$.ajax({
			 url: myURL + 'quotes/moreInfo',
				type: 'POST',
				data: { data: postData },
				success: function(ret) {
					if ( ret == SUCCESS ) {
						alert('Salesperson notified...');
						window.location = myURL + 'quotes/view?id=' + quoteID;

					}
					else {
						console.log("Error notifying salesperson.");
					}
				}
		});


		return false;
	});

	$('[id^=saveItemChanges_]').on('click', function() {    // Coordinator Save Changes
		var tmp	 =  $(this).attr('id');
		var match   = /^\w+_(\d+)_(\d+)_(\d+)$/.exec(tmp);
		var owner = RegExp.$1;
		var item  = RegExp.$2;
		var group = RegExp.$3;

		var postData = $('#form_'+owner+'_'+item+'_'+group).serialize();
		// console.log( 'Save Changes for: owner=['+owner+'], item=['+item+'], group=['+group+'] -  form data: ' + postData ); 
		$.ajax({
			type: "POST",
			url: myURL + 'quotes/updateMfg/' + item,
			data: postData,
			success: function(results)  {
				if ( results == SUCCESS ) {
					//alert('Manufacturing quote updated.' ); 
					console.log('Manufacturing quote updated.' ); 

					updateProposalSummary();
					addInternalMessage( GROUPS[group], 'section updated' );
					location.reload();

				}
				else {
					alert('Could not update mfg quote - See Admin.');
				}
			},
			error: function (jqXHR, textStatus, errorThrown)  {
				alert("Could not update mfg quote; error=\n\n" + errorThrown + ", jqXHR="+jqXHR);
			}
		});


	});




	$('#select_UpdateQuoteStatus').on('change', function() {
		var new_id   = $('#select_UpdateQuoteStatus option:selected').val();
		var new_text = $('#select_UpdateQuoteStatus option:selected').text();

		if ( confirm( "Are you sure you want to change the status on this quote?") ) {
			console.log('Changing quote status:  id=['+new_id+'], text=['+new_text+']');
			var postData = {
					quote_id:		quoteID,
					new_status_id:   new_id,
					new_status_text: new_text,
			};

			$.ajax({
				type: "POST",
				url: myURL + 'quotes/updateStatus',
				data: postData,
				success: function(results)  {
					if ( results == SUCCESS ) {
						addInternalMessage( null, 'Quote status set to [ ' + new_text + ' ]' );
						console.log('Quote status set to '+ new_text ); 

						location.reload();
					}
					else {
						alert('Could not change quote status - See Admin.');
					}
				},
				error: function (jqXHR, textStatus, errorThrown)  {
					alert("Could not change quote status; error=\n\n" + errorThrown + ", jqXHR="+jqXHR);
				}
			});
		}
		else {
			// reset value of selected...
			$(this).removeAttr('selected');
			$("#select_UpdateQuoteStatus option:first").attr('selected','selected');
		}
	});

	$('#button_ApproveItem').on('click', function() {
		var quoteID = $('#Quotes_id').val(); 
		if ( confirm( "Are you sure you want to approve this item?") ) {
			var postData = {
					item_id:		   currentItemID,
					item_disposition:  'Approve'
			};

			$.ajax({
					type: "POST",
					url: myURL + 'quotes/disposition?id=' + quoteID,
					data: postData,
					success: function(results)  {
						console.log('results from quote disposition=['+results+']');
						location.reload();
					}
			});
		}

		return false;
	});



	$('#button_RejectItem').on('click', function() {
		var quoteID = $('#Quotes_id').val(); 
		if ( confirm( "Are you sure you want to reject this item?") ) {
		   var postData = {
					item_id:		   currentItemID,
					item_disposition:  'Reject'
			};

			$.ajax({
					type: "POST",
					url: myURL + 'quotes/disposition?id=' + quoteID,
					data: postData,
					success: function(results)  {
						console.log('results from quote disposition=['+results+']');
						location.reload();
					}
			});
		}

		return false;
	});
	
});
