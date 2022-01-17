<?php
/**
 * * BazaarVoice Feed: 
 * * Usage: Application will query PRODUCT_MV and ITM_IMAGES and will generate a CSV file to be placed on the out folder and will generate  
 * * and XML object (see exmaple)  to feed to bazaar voice.
 * * XML Example: 
 * *  <?xml version="1.0" encoding="utf-8" standalone="yes"?>
 * *     <Feed name="Mor Furniture For Less" extractDate="2022-01-16T19:14:13-08:00" incremental="false"
 * *          xmlns="http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.6">
 * *           <!-- Brands go here -->
 * *           <Brands>
 * *               <Brand>
 * *                   <ExternalId>ITM_STYLE_.STYLE_CD</ExternalId>
 * *                   <Name>ITM_STYLE.ECOMM_DES</Name>
 * *               </Brand>
 * *           </Brands>
 * *           <!-- Categories go here -->
 * *           <Categories>
 * *               <Category>
 * *                   <ExternalId>COLLECTION_CD</ExternalId>
 * *                   <Name>PRODUCT_MV.CATEGORIES</Name>
 * *                   <ParentExternalId></ParentExternalId>
 * *                   <CategoryPageUrl>http://example.com/18f9a</CategoryPageUrl>
 * *                   <ImageUrl></ImageUrl>
 * *               </Category>
 * *           </Categories>
 * *           <!-- Products go here -->
 * *           <Products>
 * *               <Product>
 * *                   <ExternalId>PRODUCT_MV.ITM_CD</ExternalId>
 * *                   <Name>PRODUCT_MV.MERC_NAME</Name>
 * *                   <Description>PRODUCT_MV.WEB_PRODUCT_GROUP</Description>
 * *                   <CategoryExternalId>PRODUCT_MV.CATEGORIES</CategoryExternalId>
 * *                   <ProductPageUrl>PRODUCT_MV.PRODUCT_PAGE_URL</ProductPageUrl>
 * *                   <ImageUrl>PRODUCT_MV.URL</ImageUrl>
 * *                   <UPCs>
 * *                       <UPC>PRODUCT_MV.ITM_CD</UPC>
 * *                   </UPCs>
 * *                   <ModelNumbers>
 * *                       <ModelNumber></ModelNumber>
 * *                   </ModelNumbers>
 * *                   <BrandExternalId></BrandExternalId>
 * *               </Product>
 * *           </Products>
 * *      </Feed>
 * *
 * * Arguments: 
 * *
 * * Out: CSV file 
 * *-------------------------------------------------------------------------------------------------------------------------------------
 * * 01/16/21   JL  Created Script
 * *
 * *
***/

    date_default_timezone_set('America/Los_Angeles');

    include_once('../config.php');
    include_once('autoload.php');

    global $appconfig, $logger;

    $logger = new ILog($appconfig['bazaar']['logger']['username'], date('ymdhms') . ".log", $appconfig['bazaar']['logger']['log_folder'], $appconfig['bazaar']['logger']['priority']);
    $timestamp = time();
    $dt = new DateTime("now", new DateTimeZone("America/Los_Angeles")); 
    $dt->setTimestamp($timestamp); //adjust the object to correct timestamp

    $logger->debug( "Starting process: bazaar voice feed" );
    $mor = new Morcommon();
    $db = $mor->standAloneAppConnect();
    
    if ( !$db ){
        $logger->error( "Could not connect to database" );
        exit(1);
    }

    $logger->debug( "Querying items from table PRODUCT_MV and ITM_IMAGES" );
    $products = getBazaarVoiceProducts( $db );
    $logger->debug( "Finished getting bazaar product voice products" );
    //Generate XML 
    $xml = createXML( $products, $dt );
    $logger->debug( "Finished getting bazaar product voice products" );
    //Generate CSV
    $logger->debug( "Generating CSV for bazaar products" );
    $error = generateCSV( $products, $appconfig['bazaar']['out'], sprintf( $appconfig['bazaar']['filename'], date('YmdHis')), $appconfig['bazaar']['header'] );

    $logger->debug( "Finished Execution bazaar products" );



    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * generateCSV: 
     * *   Generate CSV file on $path directoy with filename and heeader 
     * * Arguments: 
     * *    data: CSV data 
     * *    path: path to where it should be saves 
     * *    filename: file name 
     * *    header: header row
     * *
     * * Return: TRUE for success false otherwise 
     * *
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function generateCSV( $data, $path, $filename, $header='' ) {
        global $appconfig, $logger;
        //Generating timestamp CSV file name
        try {
            $file = fopen( $path . $filename, "a+" );
            if ( $header !== '' ){
                fputcsv( $file, $header );
            }
            foreach( $data as $line ) {
                fputcsv( $file, $line );
            }
            fclose($file);

            return false;
        }
        catch( Exception $e ){
            $logger->debug( "Generating CSV Exception caused: " . $e->getMessage() );
            return true;
        }
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * createXML: 
     * *   Function will generate XML for bazaarvoice  
     * * Arguments: 
     * *       - product: Array with product information
     * *
     * * Return: Array with XML information  
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function createXML( $products, $dt ){
        global $appconfig, $logger;
        $out = [];

        //Iterate through the products array 
        foreach( $products as $product ){
            $xml= simplexml_load_string( "<?xml version=\"1.0\" encoding=\"utf-8\" ?> <Feed name=\"Mor Furniture For Less\" extractDate=\"" . $dt->format('Y-m-d\TH:i:sP') . "\" incremental=\"false\" xmlns=\"http://www.bazaarvoice.com/xs/PRR/ProductFeed/5.6\"></Feed> ");

            //Add childs for brands
            $brands = $xml->addChild('Brands'); 
            $brand = $brands->addChild('Brand'); 
            $brand->addChild( "ExternalId", $product['COLLECTION_CD'] );
            $brand->addChild( "Name", $product['ECOMM_DES'] );

            $categories = $xml->addChild('Categories'); 
            $category = $categories->addChild('Category'); 
            $category->addChild("ExternalId", $product['COLLECTION_CD'] );
            $category->addChild("Name", htmlspecialchars($product['CATEGORIES']) );

            $products = $xml->addChild('Products'); 
            $childProduct = $products->addChild('Product'); 
            $childProduct->addChild( "ExternalId", $product['ITM_CD'] );
            $childProduct->addChild( "Name", $product['MERCH_NAME'] );
            $childProduct->addChild( "Description", $product['WEB_PRODUCT_GROUP'] );
            $childProduct->addChild( "CategoryExternalId", htmlspecialchars($product['CATEGORIES']) );
            $childProduct->addChild( "ProductPageUrl", htmlspecialchars($product['PRODUCT_PAGE_URL']) );
            $childProduct->addChild( "ProductImageUrl", htmlspecialchars($product['PRODUCT_IMAGE_URL']) );

            $upcs = $childProduct->addChild('UPCs'); 
            $upcs->addChild( "UPC", $product['ITM_CD'] );

            $logger->debug( "Product XML: \n" . tidy_repair_string( $xml->asXML(), ['input-xml'=> 1, 'indent' => 1, 'wrap' => 0] ) . "\n" );
            array_push( $out, $xml->asXML() );
        }

        return $out;
    }

    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
    /*********************************************************************************************************************************************
     * * getBazaarVoiceProducts: 
     * *   Function will query for products and build a xml feed for bazaarvoice   
     * * Arguments: 
     * *       - db: IDBT database connectoin 
     * *
     * * Return: Array with product information 
     * *
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************
     *********************************************************************************************************************************************/
    function getBazaarVoiceProducts( $db ){
        global $appconfig, $logger;
        
        $bazaar = new Bazaar($db);
        $products = [];

        $where = "WHERE MERCH_VISIBILITY = 'CATALOG, SEARCH'";
        $result = $bazaar->query($where);
        if( $result < 0 ){
            $logger->error( "Could not query tables PRODUCT_MV, and ITM_IMAGES" );
            exit(1);
        }

        while( $bazaar->next() ){
            $tmp = [];

            $tmp['ITM_CD'] = $bazaar->get_ITM_CD();
            $tmp['MERCH_NAME'] = $bazaar->get_MERCH_NAME();
            $tmp['ECOMM_DES'] = $bazaar->get_ECOMM_DES();
            $tmp['COLLECTION_CD'] = $bazaar->get_COLLECTION_CD();
            $tmp['PRODUCT_PAGE_URL'] = $appconfig['bazaar']['bazaar_mor_product_url'] . $bazaar->get_ITM_CD();
            $tmp['PRODUCT_IMAGE_URL'] = $appconfig['bazaar']['bazaar_mor_image_url'] . $bazaar->get_URL();
            $tmp['STYLE_CD'] = $bazaar->get_STYLE_CD();
            $tmp['CATEGORIES'] = str_replace(" ", "-", $bazaar->get_CATEGORIES());
            $tmp['WEB_PRODUCT_GROUP'] = str_replace(" ", "-", $bazaar->get_WEB_PRODUCT_GROUP());

            array_push( $products, $tmp );

        }

        $logger->debug( "Dumping all products for bazaar voice: " . print_r($products, 1) );
        return $products;

    }

?>
