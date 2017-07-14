<?php

include (APPPATH.'libraries/TeamingServiceSoapService.php');
include (APPPATH.'config/kablink_cfg.php');

/*****************************************************************
 *    Function: getFolderEntryList()  
 * Description: Retrieve folder entry numbers by binder ID
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: binder ID     
 *     @return: array of entry numbers for success,
 *              false for failure
 *****************************************************************/
function getFolderEntryList( $binderID ) {
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $msg    = $result->folder_getEntries( "", $binderID );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return "SOAP Fault: ( $fault->faultstring )"; //false;
   }

   // print "msg: "; print_r( $msg ); exit;   

   foreach ( $msg as $entries ) {
      foreach ( $entries as $arr ) {
         foreach ( $arr as $key => $val ) {
            if ( !is_object($val) ) {
               if ( $key == 'id' ) {
                  $entry_list[] = $val;
               }
            }
         }
      }
   }
   return $entry_list;

} // END_OF_FUNCTION getFolderEntryList()


/*****************************************************************
 *    Function: getTeamMemberName()  
 * Description: Retrieve team member's name by uid
 *      Author: ljd     
 *  Updated on: 10/24/08  
 *      @param: user id     
 *     @return: text username 
 *****************************************************************/
function getTeamMemberName ( $uid ) { 
      if ( !is_numeric($uid) ) { 
         return "n/a";
      }   

      $my_auth  = array ('login' => AUTH_USER,
                         'password' => AUTH_PWD );

      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $profile = $result->profile_getUser( "", $uid, false );

      return $profile->title;

} // END_OF_FUNCTION getTeamMemberName()



/*****************************************************************
 *    Function: getTeamMemberProfile()  
 * Description: Retrieve team member's name by uid
 *      Author: ljd     
 *  Updated on: 11/23/08  
 *      @param: user id     
 *     @return: user profile
 *****************************************************************/
function getTeamMemberProfile ( $uid ) { 
      if ( !is_numeric($uid) ) { 
         return "n/a";
      }   

      $my_auth  = array ('login' => AUTH_USER,
                         'password' => AUTH_PWD );

      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $profile = $result->profile_getUser( "", (int)($uid), false );

      return $profile;

} // END_OF_FUNCTION getTeamMemberProfile()







/*****************************************************************
 *    Function: getFolderEntry()  
 * Description: Retrieve one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getFolderEntry( $entryID ) {
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $entryObj = $result->folder_getEntry( "", (int)($entryID), false );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }
    
   return $entryObj;
   
} // END_OF_FUNCTION getFolderEntry()




/*****************************************************************
 *    Function: getDefinitionsAsXML()   
 * Description: Retrieve local form definitions 
 *      Author: ljd      
 *  Updated on: 10/24/08  
 *      @param: definition ID     
 *     @return: definition object as XML if success
 *              false for failure
 *****************************************************************/
function getDefinitionsAsXML( $definitionID ) { 
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $def    = $result->definition_getDefinitionAsXML( "", $definitionID ) ; 
    
   }   
   catch ( SoapFault $fault ) { 
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false; 
   }   

   return $def; 

 } // END_OF_FUNCTION getDefinitionsAsXML()




/*****************************************************************
 *    Function: getDefinitions()   
 * Description: Retrieve local form definitions
 *      Author: ljd      
 *  Updated on: 10/24/08  
 *      @param: definition ID     
 *     @return: definition object as XML if success
 *              false for failure
 *****************************************************************/
function getDefinitions( ) {  
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $def    = $result->definition_getDefinitions( "" ) ;  
    
   }   
   catch ( SoapFault $fault ) { 
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }   

   return $def; 

 } // END_OF_FUNCTION getDefinitions() 




/*****************************************************************
 *    Function: getProjectPage()  
 * Description: Retrieve one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getProjectPage( $entryID ) {
	
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   $eo = getFolderEntry( $entryID );
   //print "eo:<br/><pre/>"; print_r( $eo );
   

	$proj['project_name']  = $eo->customStringFields[0]->value;
	$epoch = strtotime($eo->creation->date);
	$proj['creation']      = date( 'M d, Y', $epoch );
	
	$proj['definitionId']  = $eo->definitionId;
	$proj['owner']         = $eo->creation->principal;
	$proj['description']   = $eo->description->text;
	$proj['averageRating'] = $eo->averageRating;
	
  //$proj['screenshot']    = '';
	$proj['team_members']  = '';
	$proj['languages']     = '';
	$proj['company_name']  = '';
	$proj['categories']    = '';
	$proj['type']          = '';
	$proj['dev_status']    = '';
	$proj['licenses']      = '';
	
	$proj['kablink_versions']  = '';
	$proj['intended_audience'] = '';
	
	$proj['doc_files']      = '';
	$proj['src_files']      = '';
	$proj['bin_files']      = '';
	$proj['comments']       = ( isset($eo->customStringFields[1]) 
	                              ? $eo->customStringFields[1]->value : '' );
   
   
	foreach ( $eo->customLongArrayFields as $obj ) {
		foreach ( $obj->values as $id ) {
		   $proj['team_members'][$id] = getTeamMemberName( $id );
		}
	}
	
	foreach ( $eo->customStringArrayFields as $obj ) {
	   foreach ( $obj->values as $val ) {
         
			$proj[$obj->name][] = $val;
		}
	}

	$display_order = array( 'project_name', 'description', 'company_name', 'owner', 'creation', 
								 //'screenshot', 
								   'languages', 'kablink_versions', 'licenses', 'dev_status',   
								   'categories', 'type', 'intended_audience', 'doc_files', 'src_files',
								   'bin_files', 'team_members', 'comments' 
				          );
 		
   $statList = getCaptions( 'new_project' );
   // add some exceptions for fields not found in definitions 
 	$statList['creation']    = "Project Added"; 
	$statList['owner']       = "Contact";
	$statList['description'] = "Description";
   
   foreach ( $display_order as $field ) {

		$value = '';
		$val = '';			
		$caption = ( isset($statList[$field]) ? $statList[$field] : 'n/a' );

		if ( is_array($proj[$field]) ) {						
			foreach( $proj[$field] as $val ) {	
			   //print "field=[$field], val=[$val]<br/>";									
				if ( isset($statList[$val]) ) {
					$value .= ( $value ? ", " . $statList[$val] : $statList[$val] );
				}
				else {
					$value .= ( $value ? ", $val" : $val );
				}
			}						
		}
		else {
			$value   = $proj[$field];
		}
					
		if ( isset($statList[$value]) ) {
			$value = $statList[$value];			
		}

		$proj['caption'][] = $caption; 
		if ( $field == 'team_members' ) {   
         $tmp = '';
         if ( $proj['team_members'] ) {
            foreach ( $proj['team_members'] as $id => $name ) {
               $tmp .= "<a href=\"/project/member/$id\">$name</a>&nbsp;&nbsp;&nbsp;";  
            }
            $proj['value'][] = $tmp; 
         }
         else {
            $proj['value'][] = $value;
         }
      }
      else if ( preg_match( '/doc_files|src_files|bin_files/', $field ) ) {
         $tmp = '';
         foreach( explode( ",", $value ) as $fileName ) {
            $fileName = trim($fileName);
            foreach ( $eo->attachmentsField->attachments as $obj ) { 
               if ( $fileName == $obj->fileName ) {
                 $tmp .= '<a href="' . $obj->href . '" target="_blank">' . 
                                       $fileName . '</a>&nbsp;&nbsp;&nbsp;';
               }
            }
         }
         $proj['value'][] = $tmp; 
      }
      else {
         $proj['value'][] = $value;
      }
      
      
      
	}
	
	return $proj;

} // END_OF_FUNCTION getProjectPage()


/*****************************************************************
 *    Function: getServicePage()  
 * Description: Retrieve one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getServicePage( $entryID ) {

	$my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   $eo = getFolderEntry( $entryID );
   foreach( $eo->customStringFields as $obj ) {
      $service[ $obj->name ] = $obj->value;
   }


   $epoch = strtotime($eo->creation->date);
	$service['creation']      = date( 'M d, Y', $epoch );
	
	$service['definitionId']  = $eo->definitionId;
	$service['averageRating'] = $eo->averageRating;
   
   foreach ( $eo->customStringArrayFields as $obj ) {
	   foreach ( $obj->values as $val ) {
			$service[$obj->name][] = $val;
		}
	}
	
	$display_order = array( 'company_name', 'company_desc','address', 'city_state_zip', 'telephone', 
	                        'email', 'web_page', 'contact',   'desc_integration', 'desc_look_feel',
	                         'desc_training', 'desc_software', 'kablink_versions',
	                         'averageRating', 'creation'
	                      );	

   $statList = getCaptions( 'new_service_provider' );

   // add some exceptions for fields not found in definitions 
 	$statList['creation']      = "Registered Since"; 
	$statList['contact']       = "Contact";
	$statList['averageRating'] = "Average Rating";
   
   foreach ( $display_order as $field ) {
				 $value = '';
				 $val = '';			
				 $caption = ( isset($statList[$field]) ? $statList[$field] : 'n/a' );
	   if ( isset($service[$field]) ) {
		   if ( is_array($service[$field]) ) {						
			   foreach( $service[$field] as $val ) {										
			   	if ( isset($statList[$val]) ) {
			   		$value .= ( $value ? ", " . $statList[$val] : $statList[$val] );
			   	}
			   	else {
			   		$value .= ( $value ? ", $val" : $val );
			   	}
			   }						
		   }
		   else {
			   $value   = ( isset($service[$field]) ? $service[$field] : 'n/a' );
		   }
      }				
					
		if ( isset($statList[$value]) ) {
			$value = $statList[$value];			
		}

		$service['caption'][] = $caption;
		$service['value'][] = $value;
   
   }

   return $service;


} // END_OF_FUNCTION getServicePage()


/*****************************************************************
 *    Function: getEditProjectPage()  
 * Description: Retrieve one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getEditProjectPage( $entryID ) {
	
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   $eo = getFolderEntry( $entryID );
   
   //print "<pre/>folder entry $entryID<br/>"; print_r($eo); exit;
   
   if ( !$eo ) {
      return false;
   }

   foreach ( $eo->customStringFields as $i ) {
      $proj[$i->name] = $i->value;
   }

	//$proj['project_name']  = $eo->customStringFields[0]->value;
	$epoch = strtotime($eo->creation->date);
	$proj['creation']      = date( 'M d, Y', $epoch );
	
	$proj['definitionId']  = $eo->definitionId;
	$proj['owner']         = $eo->creation->principal;
	$proj['description']   = $eo->description->text;
	$proj['averageRating'] = $eo->averageRating;
	
   //$proj['screenshot']    = '';
	$proj['team_members']  = '';
	$proj['languages']     = '';
	$proj['company_name']  = '';
	$proj['categories']    = '';
	$proj['type']    = '';
	$proj['dev_status']    = '';
	$proj['licenses']      = '';
	
	$proj['kablink_versions']  = '';
	$proj['intended_audience'] = '';
	
	$proj['uid_list'] = array();
	
	$proj['doc_files']      = '';
	$proj['src_files']      = '';
	$proj['bin_files']      = '';
	
   //$proj['comments']      = ( isset($eo->customStringFields[1]) 
	                           //   ? $eo->customStringFields[1]->value : '' );
	
   //foreach ( $eo->attachmentsField->attachments as $obj ) { 
   //   $proj['screenshot'][] = $obj->href;
  // }
   
	foreach ( $eo->customLongArrayFields as $obj ) {
		foreach ( $obj->values as $id ) {
		   $proj['uid_list'][]     = $id;
			$proj['team_members'][] = getTeamMemberName( $id );
		}
	}
	
	foreach ( $eo->customStringArrayFields as $obj ) {
	   foreach ( $obj->values as $val ) {
         
			$proj[$obj->name][] = $val;
		}
	}

	$display_order = array( 'project_name', 'description', 'company_name', 'owner',
	                        'creation', //'screenshot', 
	                        'languages', 'kablink_versions',
	                        'licenses', 'dev_status', 'categories', 'type',
	                        'intended_audience', 'doc_files', 'src_files', 'bin_files',
	                        'team_members', 'comments' 
				          );
	
 	$statList = getCaptions( 'new_project' );
 	// add some exceptions for fields not found in definitions 
 	$statList['creation']    = "Project Added"; 
	$statList['owner']       = "Contact";
	$statList['description'] = "Description"; 
	$statList['doc_files'] = 'Documentation';
	$statList['src_files'] = 'Source Files';
	$statList['bin_files'] = 'Binary Files';
  
   foreach ( $display_order as $field ) {
   			 if ( isset($name) ) {
   			 	unset ($name);
   			 }
				 if ( isset($value) ) {
				 	unset ($value);
				 }
				 //$val = '';			
				 $caption = $statList[$field];
				 
		if ( isset( $proj[$field] ) ) {	 
		   if ( is_array($proj[$field]) ) {						
			   foreach( $proj[$field] as $val ) {
			   	$name[] = $val;	
				   if ( is_array($val) ) {
                 $value[] = $val;
				      
				   }
				   else {	
				      if ( isset($statList[$val]) ) {
				   	   $value[] = $statList[$val];				
				      }
				      else {
				   	   $value[] = $val;
				      }
				   }				
		   	}	   					
   		}   
	   	else {   
	   		$value = $proj[$field];
	   		$name = $proj[$field];
	   		if ( isset($statList[$value]) ) {
	   			$value = $statList[$value];			
	   		}			
	   	}

	   	$proj['form'][$field] = array( 'name' => $name, 'caption' => $caption, 'value' => $value );
	   }	
	}
	//print "EditProjectPage object:<br/><pre/>"; print_r($proj); exit;
	return $proj;

} // END_OF_FUNCTION getEditProjectPage()



//
/*****************************************************************
 *    Function: getEditServicePage()  
 * Description: Retrieve one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getEditServicePage( $entryID ) {
	
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   $eo = getFolderEntry( $entryID );
   
   if ( !$eo ) {
      return false;
   }

   foreach( $eo->customStringFields as $obj ) {
      $service[ $obj->name ] = $obj->value;
   }

	$epoch = strtotime($eo->creation->date);
	$service['creation']      = date( 'M d, Y', $epoch );
	
	$service['definitionId']  = $eo->definitionId;
	$service['averageRating'] = $eo->averageRating;

	foreach ( $eo->customStringArrayFields as $obj ) {
	   foreach ( $obj->values as $val ) {  
			$service[$obj->name][] = $val;
		}
	}

	$display_order = array( 'company_name', 'address', 'city_state_zip', 'telephone', 'email',
	                        'web_page', 'contact', 'company_desc', 'service_categories', 
	                        'desc_integration', 'desc_look_feel', 'desc_training', 'desc_software',
	                        'kablink_versions', 'averageRating', 'creation'
	                      );			     

 	$statList = getCaptions( 'new_service_provider' );
 	// add some exceptions for fields not found in definitions 
 	$statList['creation']    = "Registered Since"; 
	$statList['owner']       = "Contact";
	$statList['averageRating'] = "Average Rating";

    
   foreach ( $display_order as $field ) {
   			 if ( isset($name) ) {
   			 	unset ($name);
   			 }
				 if ( isset($value) ) {
				 	unset ($value);
				 }
				 //$val = '';			
				 $caption = $statList[$field];
	   if ( isset($service[$field]) ) {
		   if ( is_array($service[$field]) ) {						
			   foreach( $service[$field] as $val ) {
			   	$name[] = $val;										
			   	if ( isset($statList[$val]) ) {
			   		$value[] = $statList[$val];				
			   	}
			   	else {
			   		$value[] = $val;
			   	}				
			   }						
		   }
		   else {
			   $value = $service[$field];
			   $name = $service[$field];
			   if ( isset($statList[$value]) ) {
			   	$value = $statList[$value];			
			   }			
		   }

		   $service['form'][$field] = 
		            array( 'name' => $name, 'caption' => $caption, 'value' => $value );
		}			
	}
	
	return $service;

} // END_OF_FUNCTION getEditServicePage()




/*****************************************************************
 *    Function: getAddProjectPage()  
 * Description: Retrieve blank folder entry 
 *      Author: ljd     
 *  Updated on: 10/29/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getAddProjectPage( ) {
	
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

	$proj['project_name']  = '';
	$proj['creation']      = '';
	
	$proj['definitionId']  = '';
	$proj['owner']         = '';
	$proj['averageRating'] = '';
	
   //$proj['screenshot']    = '';
	$proj['team_members']  = '';
	$proj['languages']     = '';
	$proj['company_name']  = '';
	$proj['categories']    = '';
	$proj['type']          = '';
	$proj['dev_status']    = '';
	$proj['licenses']      = '';
	
	$proj['kablink_versions']  = '';
	$proj['intended_audience'] = '';
	//$proj['screenshot'] = '';
	$proj['team_members'] = '';

	$field_list = array( 'project_name', 'description', 'company_name', 'owner', 'creation', 
								//'screenshot', 
								'languages', 'kablink_versions', 'licenses', 'dev_status',   
								'categories', 'type', 'intended_audience', 'team_members' 
				          );
				          
 	$ADD = getCaptions( 'new_project' );
 	// add some exceptions for fields not found in definitions 
 	$ADD['creation']    = "Project Added"; 
	$ADD['owner']       = "Contact";
	$ADD['description'] = "Description"; 	
 		
   foreach ( $field_list as $field ) {
      $proj['form'][$field] = array( 'caption' => $ADD[$field] );	
	}

	return $proj;

} // END_OF_FUNCTION getAddProjectPage()


/*****************************************************************
 *    Function: getAddServicePage()  
 * Description: Retrieve blank folder entry 
 *      Author: ljd     
 *  Updated on: 10/29/08  
 *      @param: entry ID     
 *     @return: hash of object of type FolderEntry if success,
 *              false for failure
 *****************************************************************/
function getAddServicePage( ) {

   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );
                      
                      
	$display_order = array( 'company_name', 'company_desc', 'address', 'city_state_zip', 'telephone', 
	                        'email', 'web_page', 'contact', 'service_categories', 
	                        'desc_integration', 'desc_look_feel', 'desc_training', 'desc_software',
	                        'kablink_versions', 'averageRating', 'creation'
	                      );			     

 	$ADD = getCaptions( 'new_service_provider' );
 	// add some exceptions for fields not found in definitions 
 	$ADD['creation']    = "Today's Date"; 
	$ADD['owner']       = "Contact";
	$ADD['averageRating'] = "Average Rating";
   $ADD['service_categories'] = 'Service Categories';
   
   foreach ( $display_order as $field ) {
      $service['form'][$field] = array( 'caption' => $ADD[$field] );	
	}
		
	return $service;

} // END_OF_FUNCTION getAddServicePage()





/*****************************************************************
 *    Function: getUserList()  
 * Description: Retrieve list of users 
 *      Author: ljd     
 *  Updated on: 11/17/08   
 *      @param:     
 *     @return: array of users if success 
 *              false for failure
 *****************************************************************/
function getUserList( ) {  
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $users    = $result->profile_getPrincipals( "", 0, 10000 ) ; 
    
   }    
   catch ( SoapFault $fault ) { 
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return FALSE;
   }   

   return $users; 

 } // END_OF_FUNCTION getUserList()



/*****************************************************************
 *    Function: deleteFolderEntry()  
 * Description: Delete one folder entry 
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry ID     
 *     @return: true for success; false for failure
 *****************************************************************/
function deleteFolderEntry( $entryID ) {
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $status = $result->folder_deleteEntry( "", (int)($entryID) );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }

   return true;

} // END_OF_FUNCTION  deleteFolderEntry()



/*****************************************************************
 *    Function: addFolderEntry()  
 * Description: Add folder entry
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entryObject, binderID
 *     @return: true for success; false for failure
 *****************************************************************/
function addFolderEntry ( $entryObj, $binderID ) {

   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   $entryObj->parentBinderId = $binderID;

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $status = $result->folder_addEntry( "", $entryObj, "" );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }

   return true;

} // END_OF_FUNCTION addFolderEntry()

 

/*****************************************************************
 *    Function: uploadFile()  
 * Description: Upload a file into folder
 *      Author: ljd      
 *  Updated on: 10/16/08  
 *      @param: accessToken entryID, dataItemName file type
 *     @return: true for success; false for failure
 *****************************************************************/
function uploadFile ( $entryID, $dataItem, $file, $type ) { 
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $status = $result->folder_uploadFileStaged( 
                     "", (int)($entryID), $dataItem, $file, "$entryID/$type/$file" );
   }   
   catch ( SoapFault $fault ) { 
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return $fault->faultstring; //false;
   }    

   return true;

} // END_OF_FUNCTION uploadFile()




/*****************************************************************
 *    Function: modifyFolderEntry()  
 * Description: Modify contents of a folder entry
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: entry object     
 *     @return: true for success; false for failure
 *****************************************************************/
function modifyFolderEntry( $entryObj ) {

   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );

   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $status = $result->folder_modifyEntry( "", $entryObj );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }

   return true;

} // END_OF_FUNCTION modifyFolderEntry()





/*****************************************************************
 *    Function: errLOG()
 * Description: Log an error/debug message in a file
 *              defined by ERROR_LOG
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: message text
 *     @return: n/a
 *****************************************************************/
function errLOG ( $msg ) { 

   $ts =  date( 'D M j H:i:s' );

   error_log ( "$ts - $msg \n", 3, ERROR_LOG );
   return;

} // END_OF_FUNCTION errLOG()


 

/*****************************************************************
 *    Function: add_project()
 * Description: add new project
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: POST form values
 *     @return: true/false
 *****************************************************************/
function add_project ( ) { 

   $entryObj = convertHashToProjectObject( $_POST ); 
   
   $status = addFolderEntry ( $entryObj, PROJECT );
   return $status;

} // END_OF_FUNCTION add_project()
   

/*****************************************************************
 *    Function: edit_project()
 * Description: edit project
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: POST form values
 *     @return: true/false
 *****************************************************************/
function edit_project ( ) {
	
   $entryObj = convertHashToProjectObject( $_POST );
   
   $status = modifyFolderEntry ( $entryObj );
   return $status;

} // END_OF_FUNCTION edit_project()

/*****************************************************************
 *    Function: add_comment
 * Description: add comment to project
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: entry Id, comment 
 *     @return: true, false
 *****************************************************************/
function add_comment( $entry_id, $comment ) {
	
   if ( !strlen( $comment ) ) {	
      print "comment looks blank...";
      return true;                // ignore submit if blank 
   }

	$now = date( "D M j G:i:s T Y", time() );
	$who = 'admin';
	
   $eo = getFolderEntry( $entry_id );
   //print "eo:<br/><pre/>"; print_r( $eo ); exit;

 	$new_comment = "<br/><b>Submitted by: [ $who ] on $now</b><br/>$comment<br/>"; 

   if ( isset($eo->customStringFields[1]) ) {
      $all_comments = $eo->customStringFields[1]->value . $new_comment;
   }
   else { // need to create a new object
      $eo->customStringFields[1] = new CustomStringField();
      $eo->customStringFields[1]->type = 'text'; 
      $eo->customStringFields[1]->name = 'comments';
      $all_comments = $new_comment;
   }

   $eo->customStringFields[1]->value = $all_comments;
   //print "eo:<br/><pre/>"; print_r( $eo ); exit;
   $status = modifyFolderEntry ( $eo );

   return $status;      
      

} // END_OF_FUNCTION add_comment()







/*****************************************************************
 *    Function: delete_project()
 * Description: call deleteFolderEntry(), deletes project
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: entry ID
 *     @return: true/false
 *****************************************************************/
function delete_project ( $id ) { 
   
   return deleteFolderEntry ( $id );   // could call this directly
                                       // in the Project controller
                                       // but will do it here just
                                       // to be consistent with the
                                       // other api calls. 
} // END_OF_FUNCTION delete_project()



/*****************************************************************
 *    Function: add_service()
 * Description: add new service
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: POST form values
 *     @return: true/false
 *****************************************************************/
function add_service ( ) { 

   $entryObj = convertHashToServiceObject( $_POST ); 
   
   $status = addFolderEntry ( $entryObj, SERVICE_PROVIDER );
   return $status;

} // END_OF_FUNCTION add_service()
   

/*****************************************************************
 *    Function: edit_service()
 * Description: edit service
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: POST form values
 *     @return: true/false
 *****************************************************************/
function edit_service ( ) {
	
   $entryObj = convertHashToServiceObject( $_POST );
   
   $status = modifyFolderEntry ( $entryObj );
   return $status;

} // END_OF_FUNCTION edit_service()


/*****************************************************************
 *    Function: delete_service()
 * Description: call deleteFolderEntry(), deletes service
 *      Author: ljd     
 *  Updated on: 10/31/08  
 *      @param: entry ID
 *     @return: true/false
 *****************************************************************/
function delete_service ( $id ) { 
   
   return deleteFolderEntry ( $id );   // could call this directly
                                       // in the Service controller
                                       // but will do it here just
                                       // to be consistent with the
                                       // other api calls. 
} // END_OF_FUNCTION delete_service()



/*****************************************************************
 *    Function: remove_file()
 * Description: remove a file from a project
 *      Author: ljd     
 *  Updated on: 11/27/08  
 *      @param: entry_id, file
 *     @return: true/false
 *****************************************************************/
function remove_file ( $entry_id, $file ) { 
   $my_auth  = array ('login' => AUTH_USER,  
                      'password' => AUTH_PWD );
   try {
      $result = new TeamingServiceSoapService( WSDL, $my_auth );
      $status = $result->folder_removeFile( "", (int)($entry_id), $file );
   }
   catch ( SoapFault $fault ) {
      errLOG ( "SOAP Fault: ( $fault->faultstring )" );
      return false;
   }
   
   @unlink( BASEPATH . "uploads/project/$entry_id/$type/$file" ); // try to delete it in case it was missed 
   return true;         
            
} // END_OF_FUNCTION remove_file()   
            
            


/*****************************************************************
 *    Function: list_project_files()
 * Description: get a list of files in a project
 *      Author: ljd     
 *  Updated on: 11/27/08  
 *      @param: entry_id, type
 *     @return: list of files
 *****************************************************************/
function list_project_files( $entry_id, $type ) {             
     
   $results = getFolderEntry( (int)($entry_id) ); 
   if ( !$results ) {
      return false; 
   }
   
   $files = array();    
   foreach (  $results->customStringArrayFields as $i => $obj ) { 
      switch( $obj->name ) { 
         case $type . '_files':
               foreach ( $obj->values as $f ) { 
                  $files[] = $f; 
               }
               break;
      }   
   }   

   return $files;
            
            
} // END_OF_FUNCTION list_project_files()          


/*****************************************************************
 *    Function: convertHashToProjectObject()  
 * Description: create a Project Folder Entry object from hash values
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: hash values from form entry
 *     @return: object
 *****************************************************************/
function convertHashToProjectObject ( $form ) { 


   ///////////////////////////////////////////////////////////
   // Create a new folder entry object
   $obj = new FolderEntry();
   
   
   ///////////////////////////////////////////////////////////
   // Programming Languages
   $obj->customStringArrayFields[0] = new CustomStringArrayField();
   $obj->customStringArrayFields[0]->values =  $form['languages'] ; 
   $obj->customStringArrayFields[0]->name = 'languages';
   $obj->customStringArrayFields[0]->type = 'selectbox';
   
   ///////////////////////////////////////////////////////////
   // Company Name
   $obj->customStringArrayFields[1] = new CustomStringArrayField();
   $obj->customStringArrayFields[1]->values = array($form['company_name']) ; 
   $obj->customStringArrayFields[1]->name = 'company_name';
   $obj->customStringArrayFields[1]->type = 'selectbox';
   
   ///////////////////////////////////////////////////////////
   // Categories
   $obj->customStringArrayFields[2] = new CustomStringArrayField();
   $obj->customStringArrayFields[2]->values = $form['categories'] ;   
   $obj->customStringArrayFields[2]->name = 'categories';
   $obj->customStringArrayFields[2]->type = 'selectbox';
   
   ///////////////////////////////////////////////////////////
   // Type 
   $obj->customStringArrayFields[3] = new CustomStringArrayField();
   $obj->customStringArrayFields[3]->values = array($form['type']) ;   
   $obj->customStringArrayFields[3]->name = 'type';
   $obj->customStringArrayFields[3]->type = 'selectbox';   
   
   ///////////////////////////////////////////////////////////
   // Development Status
   $obj->customStringArrayFields[4] = new CustomStringArrayField();
   $obj->customStringArrayFields[4]->values = array($form['dev_status']) ;  
   $obj->customStringArrayFields[4]->name = 'dev_status';
   $obj->customStringArrayFields[4]->type = 'selectbox';
   
   ///////////////////////////////////////////////////////////
   // Licenses
   $obj->customStringArrayFields[5] = new CustomStringArrayField();
   $obj->customStringArrayFields[5]->values =  $form['licenses'] ;   
   $obj->customStringArrayFields[5]->name = 'licenses';
   $obj->customStringArrayFields[5]->type = 'selectbox';

   ///////////////////////////////////////////////////////////
   // Kablink Versions Supported
   $obj->customStringArrayFields[6] = new CustomStringArrayField();
   $obj->customStringArrayFields[6]->values =  $form['kablink_versions'] ;    
   $obj->customStringArrayFields[6]->name = 'kablink_versions';
   $obj->customStringArrayFields[6]->type = 'selectbox';

   ///////////////////////////////////////////////////////////
   // Intended Audience
   $obj->customStringArrayFields[7] = new CustomStringArrayField();
   $obj->customStringArrayFields[7]->values = $form['intended_audience'];  
   $obj->customStringArrayFields[7]->name = 'intended_audience';
   $obj->customStringArrayFields[7]->type = 'selectbox';

   ///////////////////////////////////////////////////////////
   // Team Member List
   $obj->customStringArrayFields[8] = new CustomStringArrayField();

   $tmp_array = explode( ',', $form['uid_list'] );  
   array_walk($tmp_array, 'trim_value');
  
   $obj->customStringArrayFields[8]->values = $tmp_array;  
   $obj->customStringArrayFields[8]->name = 'team_members';
   $obj->customStringArrayFields[8]->type = 'user_list';


   ///////////////////////////////////////////////////////////
   // Project Name
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[0]->value = $form['project_name'];  
   $obj->customStringFields[0]->name = 'project_name';
   $obj->customStringFields[0]->type = 'text';

   ///////////////////////////////////////////////////////////
   // Comments 
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[1]->value = ( isset($form['comments']) ? $form['comments'] : '' );  
   $obj->customStringFields[1]->name = 'comments';
   $obj->customStringFields[1]->type = 'text';


   ///////////////////////////////////////////////////////////
   // Project Description
   $obj->description = new Description();
   $obj->description->format = 1;
   $obj->description->text = $form['description']; 

   ///////////////////////////////////////////////////////////
   // Binder ID 
   $obj->parentBinderId = PROJECT;
   
   ///////////////////////////////////////////////////////////
   // Entry ID 
   $obj->id = ( isset($form['entry_id']) ? $form['entry_id'] : 0 );

   ///////////////////////////////////////////////////////////
   // Screenshot

   // ToDo Upload Docs, Source Files, Binary Files

  
   return $obj;

} // END_OF_FUNCTION convertHashToProjectObject() 

//


/*****************************************************************
 *    Function: convertHashToServiceObject()  
 * Description: create a Service Provider Folder Entry 
 *              object from hash values
 *      Author: ljd     
 *  Updated on: 10/16/08  
 *      @param: hash values from form entry
 *     @return: object
 *****************************************************************/
function convertHashToServiceObject ( $form ) { 

   ///////////////////////////////////////////////////////////
   // Create a new folder entry object
   $obj = new FolderEntry();
   
   
   ///////////////////////////////////////////////////////////
   // Kablink Versions Supported
   $obj->customStringArrayFields[0] = new CustomStringArrayField();
   $obj->customStringArrayFields[0]->values =  $form['kablink_versions'] ; 
   $obj->customStringArrayFields[0]->name = 'kablink_versions';
   $obj->customStringArrayFields[0]->type = 'selectbox';
   
   
   ///////////////////////////////////////////////////////////
   // City, State, Zip 
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[0]->value = $form['city_state_zip'];  
   $obj->customStringFields[0]->name = 'city_state_zip';
   $obj->customStringFields[0]->type = 'text';

   ///////////////////////////////////////////////////////////
   // desc_software
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[1]->value = ( isset($form['desc_software']) ? $form['desc_software'] : '' );  
   $obj->customStringFields[1]->name = 'desc_software';
   $obj->customStringFields[1]->type = 'text';

   ///////////////////////////////////////////////////////////
   // company_desc
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[2]->value = $form['company_desc'];  
   $obj->customStringFields[2]->name = 'company_desc';
   $obj->customStringFields[2]->type = 'text';

   ///////////////////////////////////////////////////////////
   // email
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[3]->value = $form['email'];  
   $obj->customStringFields[3]->name = 'email';
   $obj->customStringFields[3]->type = 'text';

   ///////////////////////////////////////////////////////////
   // address
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[4]->value = $form['address'];  
   $obj->customStringFields[4]->name = 'address';
   $obj->customStringFields[4]->type = 'text';

   ///////////////////////////////////////////////////////////
   // company_name
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[5]->value = $form['company_name'];  
   $obj->customStringFields[5]->name = 'company_name';
   $obj->customStringFields[5]->type = 'text';

   ///////////////////////////////////////////////////////////
   // desc_look_feel
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[6]->value = ( isset($form['desc_look_feel']) ? $form['desc_look_feel'] : '' );
   $obj->customStringFields[6]->name = 'desc_look_feel';
   $obj->customStringFields[6]->type = 'text';

   ///////////////////////////////////////////////////////////
   // desc_integration
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[7]->value = ( isset($form['desc_integration']) ? $form['desc_integration'] : '' ); 
   $obj->customStringFields[7]->name = 'desc_integration';
   $obj->customStringFields[7]->type = 'text';

   ///////////////////////////////////////////////////////////
   // web_page
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[8]->value = $form['web_page'];  
   $obj->customStringFields[8]->name = 'web_page';
   $obj->customStringFields[8]->type = 'text';

   ///////////////////////////////////////////////////////////
   // telephone
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[9]->value = $form['telephone'];  
   $obj->customStringFields[9]->name = 'telephone';
   $obj->customStringFields[9]->type = 'text';

   ///////////////////////////////////////////////////////////
   // contact
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[10]->value = $form['contact'];  
   $obj->customStringFields[10]->name = 'contact';
   $obj->customStringFields[10]->type = 'text';

   ///////////////////////////////////////////////////////////
   // desc_training
   $obj->customStringFields[] = new CustomStringField();
   $obj->customStringFields[11]->value = ( isset($form['desc_training']) ? $form['desc_training'] : '' );
   $obj->customStringFields[11]->name = 'desc_training';
   $obj->customStringFields[11]->type = 'text';


   ///////////////////////////////////////////////////////////
   // Service Categories
   //$obj->customStringArrayFields[1] = new CustomStringArrayField();
   //$obj->customStringArrayFields[1]->values = $form['service_categories'] ; 
   //$obj->customStringArrayFields[1]->name = 'service_categories';
   //$obj->customStringArrayFields[1]->type = 'selectbox';
   
 







   ///////////////////////////////////////////////////////////
   // Binder ID 
   $obj->parentBinderId = SERVICE_PROVIDER;
   
   ///////////////////////////////////////////////////////////
   // Entry ID 
   $obj->id = ( isset($form['entry_id']) ? $form['entry_id'] : 0 );

   ///////////////////////////////////////////////////////////
   // Screenshot

   // ToDo Upload Docs, Source Files, Binary Files


   return $obj;

} // END_OF_FUNCTION convertHashToServiceObject() 


//


/*****************************************************************
 *    Function: xxxxx
 * Description: 
 *      Author: ljd
 *  Updated on: 
 *      @param: 
 *     @return: 
 ****************************************************************/
function xxxxx ( $a, $b ) {

} // END_OF_FUNCTION xxxxx()



/*****************************************************************
 *    Function: display_entry
 * Description: checks whether this project should be displayed
 *      Author: ljd
 *  Updated on: 10/29/08
 *      @param: entry, category||type, phase
 *     @return: true or false
 ****************************************************************/
function  display_entry( $entry, $id, $phase, $browse_by ) {   

   if ( !isset($entry['entry_' . $browse_by]) || !isset($entry['entry_status']) ) {
      return false;
   }
 
   if ( in_array($id, $entry['entry_' . $browse_by]) ) {
      if ( in_array($phase, $entry['entry_status']) || $phase == "status_all")  {
         return true;
      }
   }

   return false; 

} // END_OF_FUNCTION display_entry()


/*****************************************************************
 *    Function: getFirstProjectCategory()
 * Description: 
 *      Author: ljd
 *  Updated on: 11/05/08
 *      @param: none
 *     @return: first category value
 ****************************************************************/
function getFirstProjectCategory( ) { 
   $catList = getCaptions( 'new_project', 'category_' );
   
   foreach ( $catList as $key => $val ) {
      return $key; // return first one found 
   }  
} // END_OF_FUNCTION getFirstProjectCategory () 


/*****************************************************************
 *    Function: getFirstServiceCategory()
 * Description: 
 *      Author: ljd
 *  Updated on: 11/05/08
 *      @param: none
 *     @return: first category value
 ****************************************************************/
function getFirstServiceCategory( ) { 
   $catList = getCaptions( 'new_service', 'category_' );
   
   foreach ( $catList as $key => $val ) {
      return $key; // return first one found 
   }  
} // END_OF_FUNCTION getFirstServiceCategory () 



/*****************************************************************
 *    Function: getCaptions()
 * Description: 
 *      Author: ljd
 *  Updated on: 11/05/08
 *      @param: entry type, fieldname
 *     @return: sorted hash of captions
 ****************************************************************/
function getCaptions( $name, $needle='.+' ) { 

   $obj = getDefinitions();
   foreach ( $obj->definitions as $def ) { 
      if ( $def->name == $name ) { 
         $definitionID = $def->id;
         break;
      }   
   }   

   $xml = getDefinitionsAsXML( $definitionID );
   $hash = _convert_xml_to_hash( $xml );
   foreach ( $hash as $key => $val ) {
      if ( preg_match( "/^$needle/", $key ) ) { 
         $captions[$key] = $val;
      }   
   }   
 
   if ( isset($captions) ) {
      if ( is_array( $captions ) ) {
         ksort ($captions);
      }
   }
   else {
      $captions = array( 'n/a', 'n/a' );
   }
   
   return $captions;
   
} // END_OF_FUNCTION getCaptions() 


/*****************************************************************
 *    Function: _convert_xml_to_hash()
 * Description: 
 *      Author: ljd
 *  Updated on: 11/05/08
 *      @param: 
 *     @return: 
 ****************************************************************/
function _convert_xml_to_hash( $xml ) { 
   $pattern = '/\"caption\" value\=\"([^"]+)\"\/\>.*\n*.*\<property name=\"name\" value=\"([^"]+)\"/';
   preg_match_all( $pattern, $xml, $matches );
   
   $i = 0;
   while ( isset( $matches[1][$i] ) ) { 
      $hash[ $matches[2][$i] ] =  $matches[1][$i]; $i++;
   }

   return $hash;

} // END_OF_FUNCTION _convert_xml_to_hash() 







?>
