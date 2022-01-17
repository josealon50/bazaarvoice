<?php

class Bazaar extends IDBTable {
	public function __construct($db) {
		parent::__construct($db);
        $this->tablename        = 'PRODUCT_MV JOIN ITM_IMAGES ON ITM_IMAGES.ITM_CD = PRODUCT_MV.ITM_CD AND SEQ_NO = 1 JOIN ITM_STYLE ON ITM_STYLE.STYLE_CD = PRODUCT_MV.STYLE_CD';
		$this->dbcolumns        = array(
                                            'ITM_CD' => 'ITM_CD',
                                            'MERCH_NAME'=> 'MERCH_NAME',
                                            'ECOMM_DES' => 'ECOMM_DES',
                                            'COLLECTION_CD' => 'COLLECTION_CD',
                                            'URL' => 'URL',
                                            'CATEGORIES' => 'CATEGORIES',
                                            'WEB_PRODUCT_GROUP' => 'WEB_PRODUCT_GROUP',
                                            'STYLE_CD' => 'STYLE_CD'
									);

		$this->dbcolumns_date	 = array();

        $this->dbcolumns_function = array( 
                                            "ITM_CD"  => "PRODUCT_MV.ITM_CD"  
                                        ,   'STYLE_CD' => 'PRODUCT_MV.STYLE_CD'
                                        ,   'ECOMM_DES' => 'PRODUCT_MV.ECOMM_DES'
        );  

		$this->errorMsg 			= "";

	}
}

?>
