<?php



class Projects {

   private $binderID;
   private $wsdl;
   private $auth;
   private $entries;
   private $soapClient;

   public function __construct ( $binderID, $wsdl, $auth ) {
      $this->binderID = $binderID;
      $this->wsdl = $wsdl;
      $this->auth = $auth;
      $this->soapClient = new SoapClient( $this->wsdl, $this->auth );
   }

   public function getEntries () {
      $this->entries = array();
      if ( !$this->soapClient ) {
         throw new Exception ( "Can't create new soap client");
      }

      $results = $this->soapClient->folder_getEntries( '', $this->binderID );
      foreach( $results->entries as $entry ) { 
         foreach ( $entry as $key => $val ) { 
            if ( !is_object( $val ) ) { 
               if ( $key == 'id' ) { 
                  $e = $this->soapClient->folder_getEntry( '', $val, 0 );

                  foreach ( $e->customStringFields as $obj ) {
                     $this->entries[ $obj->name ] = $obj->value;
                  }

                  foreach ( $e->customStringArrayFields as $obj ) {
                     foreach( $obj->values as $val ) {
                        $values .= (isset( $values ) ? ", $val" : $val );
                     }
                     $this->entries[ $obj->name ] = $values;
                     unset ($values);
                  }

                  foreach ( $e->attachmentsField->attachments as $obj ) { 
                     $display .= "Screenshot: [" . $obj->href . "]\n";
                     $this->entries[ 'screenshot' ] = $obj->href;
                  }  

                  foreach ( $e->customLongArrayFields as $obj ) { 
                     foreach( $obj->values as $val ) { 
                        $tm = Projects::getTeamMember( $val );
                        $values .= (isset( $values ) ? ", $tm" : $tm );
                     }  
                     $this->entries[ $obj->name ] = $values; 
                     unset ($values);
                  }   

                  $this->entries[ 'description' ] = $e->description->text;
               }
            }   
         }   
         $entryList[] = $this->entries;
      }
      return $entryList;

   } // end_of_function getEntries()


   public function getTeamMember ( $uid ) {
      if ( is_numeric($uid) ) {
         $this->userID = $uid;
      }
      else {
         throw new Exception ( "Invalid user ID: [$uid]" );
      }

      $results = $this->soapClient->profile_getUser( '', $uid, 0 );
      return $results->title;
   } // end_of_function getTeamMember()

} // end_of_class Projects {}




class ServiceProviders {

   public function __construct ( $binderID, $wsdl, $auth ) {
      $this->binderID = $binderID;
      $this->wsdl = $wsdl;
      $this->auth = $auth;
   }

   public function getEntries () {
      $this->entries = array();
      $this->soapClient = new SoapClient( $this->wsdl, $this->auth );
      if ( !$this->soapClient ) {
         throw new Exception ( "Can't create new soap client");
      }

      $results = $this->soapClient->folder_getEntries( '', $this->binderID );
      foreach( $results->entries as $entry ) { 
         foreach ( $entry as $key => $val ) { 
            if ( !is_object( $val ) ) { 
               if ( $key == 'id' ) { 
                  $e = $this->soapClient->folder_getEntry( '', $val, 0 );

                  foreach ( $e->customStringFields as $obj ) {
                     $this->entries[ $obj->name ] = $obj->value;
                  }
                  
                  foreach ( $e->customStringArrayFields as $obj ) {
                     foreach( $obj->values as $cat ) {
                        $categories .= (isset( $categories ) ? ", $cat" : $cat );
                     }
                     //$display .= "Service Categories: [$categories]\n";
                     $this->entries[ 'categories' ] = $categories;
                     unset ( $categories );
                  }
                  
                  foreach ( $e->attachmentsField->attachments as $obj ) {
                     $this->entries[ 'logo' ] = $obj->href;
                  }
               }
            }
         }
         $entryList[] = $this->entries;
      }
      return $entryList;

   }  // end_of_function getEntries()

} // end_of_class ServiceProviders {}





?>

