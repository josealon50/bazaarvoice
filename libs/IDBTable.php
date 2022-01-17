<?php

/**
 * Base class that implements CRUD functionality for a Oracle database table.
 *
 * Implements Create, Read (Query), Update and Delete functions for record(s) on a database table. Simplifies the 
 * implementation for specific database tables.
 * Minimum Requirements to Implement.
 *  1. Specify this as the parent class.
 *  2. Create a constructor that references the parent class construtor method.
 *  3. Assign values to the class attributes to define the table.
 *        $this->autoid    - name of column containing the auto generated id for the record. If this field is left blank 
 *                           the developer is responsible for supplying the proper values for the record keys.
 *        $this->tablename - assign the value of the database table. Best to assume this is case sensitive. USER
 *        $this->dbcolumns - assign  an array where the key is the column name and the value is the label to be used.
 *                                         ('id'=>'ID', 'name'=>'Name', 'password'=>'Password', 'dob'=>'Date of Birth').
 *        $this->dbcolumns_date - assign an array wher e each element represents columns that contain a date value.
 *                                          ('dob').
 *        $this->dbcolumns_password -  assign an array wher e each element represents columns that contain an encrypted value.
 *                                          ('password').
 * 
 *          <?php
 *               class Users extends IDBTable {
 *                       public function __construct($db) {
 *                       parent::__construct($db);
 *        
 *                     $this->tablename            = "USERS";
 *                     $this->dbcolumns            = array('id'=>'ID', 'name'=>'Name', 'password' => 'PASSWORD', 'dob => 'Date of Birth');
 *                     $this->dbcolumns_date = array('dob');
 *                     $this->dbcolumns_password = array('password');
 *        
 *              }
 *          }
 *
 *          ?>
 *
 * Instantiating the class
 *  $user = new User($con);
 *
 * Query all records
 *  $user->query();
 *  while ($user->next()) {
 *     echo $user->get_id()." ".$user->get_name()."\n";
 *   }
 *
 * Insert a record
 * $user = new User($con);
 * $user->set_name("John Doe");
 * $user->set_dob('4/15/1970', 'm/d/Y');
 * $user->insert();
 * $con->save();
 *
 * Update a record
 *  $user->query("where id = '3'");
 *  if ($user->next() {
 *     $user->set_name("Jim Doe");
 *     $user->update();
 *     $con->save();
 *   }
 *
 * Delete a recordmy
 * $user->delete("where name like 'Jack%'");
 * $con->save();
 *
 * @package iware\database  
 * @author Frank Ilagan <filagan@gmail.com>
 * @version 1.0_0
 *---------------------------------------------------------------------------------------------------------------------
 * Programmer           Date            Notes
 * Frank Ilagan         2011            Created
 * Frank Ilagan         10/10/2013      Ported for Oracle
 * Frank Ilagan         3/13/2014       Format dates to use 4 digits. Oracle default is only 2 digits causing
 *                                      intrepretaion issues in code, that is, 1950 converted to 2050.  
 * Frank Ilagan         3/19/2014       Add method to isColumn, returns true is the column exists.  
 * Frank Ilagan         3/31/2014       Commented out write to audit table.    
 * Frank Ilagan         2/25/2016       Modified update function. If column has been updated, i.e. it has a value in the
 *                                      array dbrowOldvalues then we do not need to call processData4DB.
 */
class IDBTable {
	protected $tablename;                         	//Name of the database table
	protected $dbcolumns; 		                	// Contains key=>value pair of tabl column and default Label for UI purposes  
	protected $dbcolumns_date;              		// Columns that use a DATE type
	protected $dbcolumns_password;     				// Columns that require encryption
	protected $dbcolumns_function;     				// Functions applied to a column
	protected $dbcolumns_image;						// Image array
	protected $dbrowvalues; 	                	// contains current row 
	protected $dbrowOldvalues; 	                	// contains current row previous values
	protected $dbresource;                       	// Contains the database connection
	protected $query_result;                     	// Result from Query
    protected $autoid_column;                       // 

	protected $last_sql;							// Last SQL statement Executed.

	/**
	 * Constructor
     *
     * @see IDBResource
	 */
    public function __construct($db) {
        
        $this->dbresource                = $db;          
        $this->dbcolumns                 =  array();        
        $this->dbcolumns_date            =  array();         
        $this->dbcolumns_password 		  =  array();   
        $this->dbcolumns_function 		  =  array();           
        $this->dbrowvalues               = array();
        $this->dbcolumns_image			  = array();            
        $this->dbrowOldvalues            = array();           
        $this->autoid_column             = "id";
   
        // Assume an attempt to create a new Record.
        $this->dbrowvalues[$this->autoid_column]          = "NEW";
 
    }

    public function setAutoIDColumn($strNewIDColumnName) {
        $oldkey = $this->autoid_column;
        $this->autoid_column = $strNewIDColumnName;
        $this->dbrowvalues[$this->autoid_column] = $this->dbrowvalues[$oldkey];
        unset($this->dbrowvalues[$oldkey]);
    }

    /**
     *  Cleans and process data before being used to update the record(s) in a table.
     *
     *  Calls Oracle Codec which prepends backslashes to the following characters: \x00, \n, \r, \, ', " and \x1a. 
     *  before loading into the Oracle database.
     *
     * @param String $value Data processed before loading it into the database.
     * @return String The data that has been processed.
     */
    function processData4DB($value) {
        if (strlen($value) > 0) {
            $value = stripslashes($value);
            $value = $value = str_replace("'", "''", $value);
            //$value = ESAPI.encoder().encodeForSQL( ORACLE_CODEC, $value);
        }
        return $value;
    }

	/**
	 * Internal function used by remove_column() function to update the dbcolumns class attribute.
     */
	function array_remove_key ()
	{
	  $args  = func_get_args();

	  return array_diff_key($args[0],array_flip($args[1]));
	}

	/**
	 * Function to be called prior to a query if one are more columns are to be removed from the selection list.
     *
     * @parm String[, String]... Comma separated list of column names to be removed from the selection list.
     */
	function remove_column() {

		$columns  = func_get_args();
		$this->dbcolumns = $this->array_remove_key($this->dbcolumns, $columns);
	}


	/**
     * Default function called by php for methods not explicitly implemented.
     *
     * Defines functions for getter and setter methods on db columns. 
     *
     * The function call signatures:
     * 
     *  void function set_<db column name>($value [, $option])
     *
     * $value function get_<db column name>([$option])
     *
     *  Where $value is the value to be stored or retrieved from the database.
     *               $option is used primarily for date/time data fields and specifies The format  of the date to be stored
     *                             if used in the set or the format to be retrieved when used from the get.
     *
     * @param String $method The name of the method requested. e.g. set_id($id);
     * @param Array $args Is an array containing the values passed to the requested method. Using the set_id($id) call.
     *                  $args[0] will contain the value of $id.
     *  @return Boolean true on success, false otherwise.
     */
	function __call( $method, $args )
	{

		if ( preg_match( "/set_(.*)/", $method, $found ) ) {
            if ( array_key_exists( $found[1], $this->dbcolumns ) )   {
                $value = $this->processData4DB($args[0]);

				if (!array_key_exists( $found[1], $this->dbrowOldvalues ) && array_key_exists( $found[1], $this->dbrowvalues ) )   {
					// See if the value changed, if it is different save the previous value.
					if (in_array($found[1],$this->dbcolumns_date)) {
						$d = new IDate();
		                if (count($args) > 1)  {
		                    $d->setDate($value, $args[1]); // A valid date format must be specified in $args[1]
		                }
		                else {
		                    $d->setDate($value);  // Input Date in $args[0] must be in the format IDate::DEFAULT_FORMAT
		                }

                        if (strlen($value) > 0) {
                            $value = $d->toStringOracle();
                        }
		                

		                if (count($args) > 1)  {
		                    $d->setDate($this->dbrowvalues[ $found[1] ], $args[1]); // A valid date format must be specified in $args[1]
		                }
		                else {
		                    $d->setDate($this->dbrowvalues[ $found[1] ]);  // Input Date in $args[0] must be in the format IDate::DEFAULT_FORMAT
		                }

						$oldvalue = $d->toStringOracle();

						if ($oldvalue != $value) {
		                	$this->dbrowOldvalues[ $found[1] ] = $this->dbrowvalues[ $found[1] ];
						}

					}
					else if ($this->dbrowvalues[ $found[1] ] != $value) {
	                	$this->dbrowOldvalues[ $found[1] ] = $this->dbrowvalues[ $found[1] ];
					}
				}

                // check if it is a date field that is being set.
                if (in_array($found[1],$this->dbcolumns_date)) {    
					if ($value == "" || $value == NULL) {
		                $this->dbrowvalues[ $found[1] ] = "";
					}
					else {    
		                $d = new IDate();
		                if (count($args) > 1)  {
		                    $d->setDate($value, $args[1]); // A valid date format must be specified in $args[1]
		                }
		                else {
		                    $d->setDate($value);  // Input Date in $args[0] must be in the format IDate::DEFAULT_FORMAT
		                }
		                $this->dbrowvalues[ $found[1] ] = $d->toStringOracle();
					}
                }
                else if (in_array($found[1],$this->dbcolumns_password)) {  
                    // If a SALT column exists in the table then assume new hashing method.
                    if (array_key_exists( "salt", $this->dbcolumns )) {  
                        $hashObj = new IHashObj($value);
                        $this->dbrowvalues[ $found[1] ] = $hashObj->generateHash();				   
                        $this->dbrowvalues['salt' ] = $hashObj>getSalt();
                    }
                    else {
                        $this->dbrowvalues[ $found[1] ] = md5($value);
                    }
                }
                else {
                    $this->dbrowvalues[ $found[1] ] = $value;
                }
                return true;
            }   
        }
        else if ( preg_match( "/get_(.*)/", $method, $found ) )  {
            if ( array_key_exists( $found[1], $this->dbcolumns ) ) {
                // Check if the column is a date type
                 if (in_array($found[1],$this->dbcolumns_date)) {   
                    $date = $this->dbrowvalues[ $found[1]];
                    if ($date == "" || $date == null || $date == "0000-00-00 00:00:00") {
                        return "";
                    }
                    $d = new IDate();
                    $res = $d->setDate($date, IDate::DEFAULT_FORMAT  );
                    if ($res == false) {
                        return "failed";
                    }
                    if (count($args) > 0)  {
                        return $d->toString($args[0]);  // Use the format specified in args[0]
                    }
                    else {
                        return $d->toString(); //Output the Date string using IDate::DEFAULT_FORMAT 
                    }
                 }
                 else {
                    return $this->dbrowvalues[ $found[1] ];
                }
            }
		}
        return false;
	}	
    
    /**
     * Get the Oracle error for the last SQL statement executed.
     *  Format of the error message:
     * <oracle error #> : <oracle error msg>
     * 
     * @return String The error number and the error message from the last Oracle command executed.
     */
     function getError() {
        $e = oci_error($this->dbresource->get_cnx()); //$this->attributes['cnx']); 

        if ($e === false) {
            return "";
        }
        
        return $e['code'].
                    " : ".
                    $e['message']." Last SQL ".$this->last_sql;;           
    }
         
    /**
     *  Genereates the SQL used for a query and executes the query.
     *
     * @param String $where A string containing the where clause. The string must be a valid SQL where clase. e.g "where id = '1' and name = 'JOHN'" Optional
     * @param String $postclauses A string that contains SQL clauses that appear at the end of the SELECT statement. Clauses like "order by" and "group by"
     *                             are examples of the clauses that may be part of this string. This string must be a valid SQL.
     * @return int -1 if there was a database error (usually an SQL syntax error), otherwise it returns the number sero, 0. Unlike the mysqli implementation
     *                that returns a number that is >=0 which indicating the number of records returned in the query.
     */
     function query($where="", $postclauses="") {
        $select      = "select ";
        $column_list = "";
        $from        = "from ".$this->tablename." ";
        $first = true;

        foreach ($this->dbcolumns as $col => $label) {
            if (!$first) {
                $column_list .=", ";
            }
            else  {
                $first=false;
            }
            
            // See if there is a function applied against the column
            if (isset($this->dbcolumns_function[$col])) {
            	$column_list.=$this->dbcolumns_function[$col];
            }
            else {
                // Check if $col is defined as a date
                if (in_array($col,$this->dbcolumns_date)) {
                    $column_list.="TO_CHAR(".$col.", 'DD-MON-YYYY') ".$col; ;
                }
                else {
            	   $column_list.=$col;
                }
            }
        }	
        
        $this->last_sql = $select.$column_list." ".$from.$where." ".$postclauses;
//error_log($this->last_sql."\n", 3, "fbi.log");
//echo $this->last_sql."\n";
        // Perform the query          
        $this->query_result = $this->execStmt($this->last_sql);
 
        if (!$this->query_result) {               
            // Database error   
            echo    $this->last_sql."\n"; 
            return -1;
        }
        else { 
            return oci_num_rows($this->query_result);
        }
    }
    
    /**
     * Get a record from the queried result set. Used in conjuction wth IDBTable->query(). Stores results of the record in the 
     * class attributes. Use the  get_<db column name> method to retrieve the values.
     *
     * @return Boolean false if no more records or and associative array of the retrieved data record.
     *                Array The values in the record with the column names as the index into the array.
     */
    function next() {
        $row = oci_fetch_array($this->query_result, OCI_ASSOC+OCI_RETURN_NULLS);
        
        if ($row) {
            // Populate the class dbrowvalues array.
            foreach ($this->dbcolumns as $col => $label) {
                $this->dbrowvalues[$col] = $row[$col];
            }
        }
        return $row;  // Data Returned.
    }

    /**
     * Get a the results of a query as an OPTIONS string to be used with a SELECT tag. Used in conjuction wth IDBTable->query().
     *
     * @parameter String $id The name of the column to be used as source for the selected code.
     * @parameter String $value The name of the column to be used as the source for the value to be displayed.
     * @parameter String $selected If the value in the $id column matches this value then it would be marked as selected. Default is "".
     * @return Boolean false if no records in the result 
     *                String representing the options string to be used in conjunction with the SELECT tag.
     */
    function getResultasOptions($id, $value, $selected="") {

        if (!$this->query_result) {
            return false;
        }
        
        if ($selected == "") {
            $options='<option value="" selected="selected"></option>';
        }
        else {
            $options='<option value="" ></option>';
        }

        while ($this->next()) {
            $fnid="get_".$id;
            $fnval="get_".$value;
            
            $code=$this->$fnid();
            $displayval=$this->$fnval();
            
            if ($selected == $code) {
                $options.='<option value="'.$this->$fnid().'" selected="selected">'.$this->$fnval().'</option>';
            }
            else {
                $options.='<option value="'.$this->$fnid().'">'.$this->$fnval().'</option>';
            }
        }         
        
        return $options;
    }
        
    /**
     * Get a the results of a query as an HTML table doc. Used in conjuction wth IDBTable->query().
     *
     * @parameter String $tableid The id you wish to assign to the html table.
     * @return Boolean false if no records in the result 
     *                String representing the data in an HTML table.
     */
    function getResultasTable($tableid) {

        if (!$this->query_result) {
            return false;
        }
        
        $tbl ='<table cellpadding="5" cellspacing="0" border="1" class="display" id="'.$tableid.'">';

        // Build the header
        //<thead><tr><th>ID</th><th>Name</th><th>FEC ID</th></tr></thead><tbody>';
        $tbl.='<thead><tr>';
        foreach ($this->dbcolumns as $col => $label) {
            $tbl.='<th>'.$label.'</th>';
        }
        $tbl.='</tr></thead><tbody>';

        // Build Body
         while ($this->next()) {
			
				if (array_key_exists("id",$this->dbcolumns)) {
		         	$tbl.= '<tr id="'.$this->get_id().'">';
				}
				else if (array_key_exists("ID",$this->dbcolumns)) {
		         	$tbl.= '<tr id="'.$this->get_ID().'">';
				}
				else {
		         	$tbl.= '<tr>';
				}

            foreach ($this->dbcolumns as $col => $label) {
                $fn="get_".$col;
                if (in_array($col,$this->dbcolumns_date)) {  // Check if the column name is in the array of date columns
                    $tbl.= '<td>'.$this->$fn("m/d/Y").'</td>';
                }     
                else if (array_key_exists($col,$this->dbcolumns_image)) {  // Check if the column name is in the array of images
					$arrImage = $this->dbcolumns_image[$col];
					// See if the value exists in the array
					if (array_key_exists($this->$fn(), $arrImage)) {
						$tbl.= '<td><img src="'.$arrImage[$this->$fn()].'" /></td>';
					}
					else {
                    	$tbl.= '<td>'.$this->$fn().'</td>';
					}
                }     
                else {
                    $tbl.= '<td>'.$this->$fn().'</td>';
                }
                
            }
            $tbl.= '</tr>';

        }

        $tbl.='</tbody></table>';

        return $tbl;        
    }
    
    /**
     * In order to mimic the auto increment primary key in MySQL, an Oracle Sequence must be used to
     * generate the next sequence. This method is used in conjunction with the insert() mehtod to get the 
     * id used for the insert used for the table referenced in the inheriting class for the current Oracle
     * DB session.
     *
     * @return int The id.
     */
    function getRecordID() {
        $sql="SELECT " . $this->tablename . "_SEQ.CURRVAL FROM DUAL";

        $result = $this->execStmt($sql);

        if (! $result) {
            return false;
        }

        $row = oci_fetch_array($result, OCI_ASSOC+OCI_RETURN_NULLS);
        return $row['CURRVAL'];
    }

    /**
     * Insert  a record into the database table. Uses the data stored in the class attributes for the record values. Use the set_<db col name> methods to 
     * assign values to the class attributes.
     *
     * Parameters:
     * @parameter Boolean $autoid Boolean, TRUE indicates AutoIncrement feature should be used on the coluimn ID. Default is TRUE.
     * @parameter Boolean $appupdate Boolean, TRUE indicates code should be executed that inserts into APPLICATION audit columns: created_at and updated_at.
     * 
     * @return boolean FALSE on error or the ID generated for the record created. Use IDBTable->getError to get the associated error.
     *                       TRUE if $autoid is set to FALSE 
     */
    function insert($autoid=true, $appupdate=false) {
        $preamble = "insert into ".$this->tablename." ";
        $collist = "";
        $vallist = "";
        $first = true;
        foreach ($this->dbrowvalues as $key => $val) {
            if ($key==$this->autoid_column && $autoid == true) {
            }
            else if (strlen($val) >0) {
                if (!$first) {
                    $collist .=", ";
                    $vallist .=", ";
                }
                else {
                    $first=false;
                }
                $collist .=$key;

                $vallist .= "'".$val."'";

            }

        }
        
        if ($appupdate) {
            // Add additional application fields
            if (!$first) {
                $collist .=", ";
                $vallist .=", ";
            }

            $collist .= "created_at, updated_at";
            $vallist .= "sysdate, sysdate";            
        }

        $this->last_sql = $preamble.
                          "(".$collist.") ".
                          "values ".
                          "(".$vallist.")";
          
        $result=$this->execStmt($this->last_sql);

        if ($result==false) {
            // Database error
            return false;
        }

        if ($autoid==true) {        
            $id = $this->getRecordID();
            
            if ($id !== false) {
                $this->dbrowvalues['id'] = $id;
                /*
                if ($appupdate) {
                    $this->writeAuditLog($this->tablename, "INSERT", "");  
                }       
                */

                return $id;
            }

            return false;

        }
        else {
            /*
            if ($appupdate) {
                $this->writeAuditLog($this->tablename, "INSERT", "");  
            }  
            */

            return true;
        }
        
    }
    
    /**
     * Usually used in conjuction with IDBTable->query(). Based on class attribute values, which represents the data for the record, a SQL UPDATE statement
     * is generated and executed.
     *
     * Parameters:
     * @param Boolean $appupdate TRUE indicates code should be executed that inserts into APPLICATION audit columns: created_at and updated_at. 
     *                                 Optional, default is TRUE.
     * 
     * @return int  -1 on error. Use IDBTable->getError to get the associated error..
     *                     0 if no records where updated
     *                    The number of affected records
    */
    function update($where="", $appupdate=true) {
        $preamble = "update ".$this->tablename." set ";
        if ($where === "") $where    = "where id = '".$this->dbrowvalues[$this->autoid_column]."' ";
        $updatecols = "";
        $first = true;

        foreach ($this->dbrowvalues as $key => $val) {
            if ( ! array_key_exists( $key, $this->dbrowOldvalues ) )   {
                $val = $this->processData4DB($val);    
            }        
			
            // Did not check for strlen($val)==0 because we want to be able to update a column to a blank value.
            if ($key != $this->autoid_column || ( $key == "ID" && $val != "NEW") ) {
                    if (!$first) {
                        $updatecols .=", ";
                    }
                    else  {
                        $first=false;
                    }
                    
                    if (in_array($key,$this->dbcolumns_date)) {  // Check if the column name is in the array of date columns
						if (trim($val) == "" || $val == NULL) {
							$updatecols .=$key."= NULL";
						}
						else {
                             
                            if (strlen($val) > 11) {
                                $updatecols .=$key."= TIMESTAMP '".$val."'";
                            }
                            else {
                                $updatecols .=$key."='".$val."'";
                            }
						}
						
                    }     
                    else {
                        $updatecols .=$key."='".$val."'";
                    }

            }
        }

        if ($appupdate) {
            // Add additional application fields
            if (!$first) {
                $updatecols .=", ";
            }

            $updatecols .= "updated_at=sysdate ";        
        }

        $this->last_sql =  $preamble." ".
                    $updatecols." ".
                    $where;

//error_log($this->last_sql."\n", 3, "fbi.log");
        $result=$this->execStmt($this->last_sql);

        if (!$result) {
            // An Error has accurred
            return -1;
        }
        else  {
            $count = oci_num_rows($result);
            if ($count == 0) {
                return 0;
            }
        }
        
        /*
        if ($appupdate) {
     	  $this->writeAuditLog($this->tablename, "UPDATE", "");
        }
        */

        return $count;
        
    }
    
   /**
     * Delete a record(s) from a database table. There are a couple of methods a delete could happen.
     *
     * Method 1
     *==================
     * Instantiate a Class
     * Invoke the query() method on the class.
     * Use the next() method to retrieve the record
     * Evaluate the data retrieved
     * If you issue a delete() at this point a record will be deleted using the id of the record queried,
     *
     * Method 2
     *==================
     * Instantiate a Class
     * Invoke the delete() method passing in the where clause to be used for the delete or the string "ALL" to delete all the records in the table.
     *
     *@param String  $where String containing the where clause to be used to delete the record(s).
     *                             If no argument is passed the where clause will befault to "where id = <recid>"., Default action.
     *                            A string containing the where clause. e.g. "where name = 'John'
     *                            A string "ALL" to delete all the records in the table. This is case sensitive.
     *                            Optional. 
     * 
     * @return int  -1 on error. Use IDBTable->getError to get the associated error..
     *                      0 if no records where updated
     *                      The number of affected records
    */
    function delete($where="") {
        $preamble = "delete from ".$this->tablename."  ";
        if ($where=="") {
            $where    = "where id = ".$this->dbrowvalues[$this->autoid_column];
        }
        else if ($where == "ALL") {
            $where = "";
        }

        $this->last_sql =  $preamble.$where;

        $result = $this->execStmt($this->last_sql);

        if (!$result) {
            // An Error has accurred
            return -1;
        }
        else  {
            $count = oci_num_rows($result);
            if ($count == 0) {
                return 0;
            }
        }
        
        /*
        if ($appupdate) {
		  $this->writeAuditLog($this->tablename, "DELETE", $where);
        }
        */

        return $count;
        
    }
    
    /**
     * Parse and execute and SQL statement against an Oracle database.
     *
     * @param String The sql statement to be executed,
     * @return Mixed, false if an error occurs during the parse or the execution, otherwise an 
     *         Oracle Statement Object is returned.
     */
    function execStmt($strSQL) {    
        $stmt = oci_parse($this->dbresource->get_cnx(), $strSQL);      
        if (! $stmt) {
            return false;
        }
 
        if (oci_execute($stmt)) {
            return $stmt;
        }

        return false;
    }

	/**
     * Get the database columns array defined for the table.
     *
     * @return Array Array of Key/Value pairs. Where the Key is the column name and value is the label for the column.
     */
	function getDBColumns() {
		return $this->dbcolumns;
    }

	/**
     * Get the database column name array defined for the table.
     *
     * @return Array database column names. 
     */
	function getDBColumnNames() {
		return array_keys($this->dbcolumns);
    }

	/**
     * Get the date database columns array defined for the table.
     *
     * @return Array column names. 
     */
	function getDateDBColumns() {
		return $this->dbcolumns_date;
	}

	/**
     * Get the passowrd database columns array defined for the table.
     *
     * @return Array column names. 
     */
	function getPwdDBColumns() {
		return $this->dbcolumns_password;
	}

    /**
     * For Update, Insert and Delete DML get the changes made to the record.
     * CREATE and DELETE DML will list out the values of the record. The values will be
     * listed in a string with the following format <column_name>(<column_value>),<column_name>(<column_value>]
     * UPDATE DML will show only columns that have changed. The values will be listed in a string
     * with the following format <column_name>(<old_column_value>, <new_column_value>)[,<column_name>(<old_column_value>, <new_column_value>)]
     *
     * @param $mode Type of DML performed. Must be one of the following "CREATE", "DELETE" or "UPDATE"
     * @param $info Additional info to be logged. Used primarily to show where clause of DELETE DML
     * @return String of changes to record.
     */
    function getRecordChanges($mode, $info) {

        if ($info == "") {
            $data = "";
        }
        else {
            $data = $info." : ";
        }
        $first = true;
        if ($mode == "INSERT" || $mode == "DELETE") {
            foreach ($this->dbrowvalues as $key => $val) {
                $val = $this->processData4DB($val);
                if ($first) {
                    $first = false;
                }
                else {
                    $data .= ", ";
                }

                $data .= $key."(".$val.")";

            }

        }
        else {
            // Assume an update
            foreach ($this->dbrowOldvalues as $key => $val) {
                $val = $this->processData4DB($val);
                if ($first) {
                    $first = false;
                }
                else {
                    $data .= ", ";
                }

                $data .= $key."(".$val.", ".$this->dbrowvalues[$key].")";

            }
        }

        return $data;
    }

	/**
     * Write any DML activity to the AUDIT_LOG table.
     *
     */
	function writeAuditLog($table, $mode, $info) {

		if (isset($_SESSION['userid'])) {
			$user = $_SESSION['userid'];
		}
		else {
			$user = 1;
		}

        $data = $this->getRecordChanges($mode, $info);

		if (trim($data) != "") {
		    $preamble = "insert into AUDIT_LOG ";
		    $collist = "(created_at, updated_at, user_id, activity_date, activity, dbtable, description)";
		    $vallist = "(sysdate, sysdate, '".$user."', sysdate, '".$mode."', '".$table."', '".$data."')";	

 		    $this->last_sql = $preamble.
		                      $collist.
		                      "values".
		                      $vallist;

		    $result=$this->execStmt($this->last_sql);

		}
	
    }

    /**
     * Get the last SQL Statement issued to the database
     */
    function getSQLStmt() {
        return $this->last_sql;
    }

    /**
    * Check is the column exists in the table.
    * 
    * @param $col The name of the column
    * @return $boolean true is the column exists, false otherwise.
    */
    function isColumn($col) {
        return array_key_exists($col, $this->dbcolumns);
    }
}
    
?>
