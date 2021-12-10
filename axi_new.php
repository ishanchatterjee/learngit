<?php

error_reporting(E_ALL | E_STRICT);
//adding comment to see the change

use Magento\Framework\App\Bootstrap;
require  '/home/kadoshop/domains/grotekadoshop.nl/public_html/app/bootstrap.php';
$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('global');
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();
$mio = $objectManager->get('Magento\Framework\Filesystem\Io\File');

//////////////////////////////////////////////////


$all_sku_stk = array();
$sql = 'select catalog_product_entity.sku, cataloginventory_stock_item.qty from catalog_product_entity join cataloginventory_stock_item on catalog_product_entity.entity_id = cataloginventory_stock_item.product_id';
$row       = $connection->fetchAll($sql);
foreach($row as $rows){
	//echo ."=>".$rows['qty'].PHP_EOL;
	$all_sku_stk[$rows['sku']] = $rows['qty'];
}


$fx = fopen('/home/kadoshop/domains/grotekadoshop.nl/public_html/all/axi.csv', 'w');
$read = array("sku","price","qty","status","id");
fputcsv($fx,$read);

$attr_set = 73;
$sql  = "SELECT sku,entity_id FROM `catalog_product_entity` WHERE  `attribute_set_id` ='" . $attr_set . "'";
$row       = $connection->fetchAll($sql);
foreach($row as $rows){
	$sku = $rows['sku'];
	$sql1 = "SELECT * FROM `catalog_product_entity_decimal` WHERE `attribute_id` = 99 AND `entity_id`='" . $rows['entity_id'] . "' AND `store_id` = 0";
	$row1       = $connection->fetchRow($sql1);
	$price = $row1['value']; $price = number_format($price, 2);
	$qty = $all_sku_stk[$sku]; $qty = intval($qty);
	$sql2 = "SELECT * FROM `catalog_product_entity_int` WHERE `attribute_id` = 273 AND `entity_id`='" . $rows['entity_id'] . "' AND `store_id` = 0";
	$row2       = $connection->fetchRow($sql2);
	$stat = $row2['value'];
	
	
	$read_admin = array($sku,$price,$qty,$stat,$rows['entity_id']);
	//echo $sku."=>".$price."=>".$qty;exit;
    fputcsv($fx,$read_admin);
}



fclose($fx);



//////////////////////////////////////////////////

function nicePriceToro($a) {
	$d = round(100 * ($a - floor($a)));
	if (($d>=1) && ($d<50) ) {
		return $d = floor($a) + 0.5;
	}
	if (($d>50) && ($d<95) ) {
		return $d = floor($a) + 0.95;
	}
	if (($d>=95) && ($d<100) ) {
		return $d = floor($a) + 0.95;
	}
	return $a;
}



$sql  = 'SELECT profit_rule FROM `feedconfig_profitmargin` where `supplier`="AXI"';
$row       = $connection->fetchOne($sql);
$adr = array();
$adr = explode("\n",$row);


$sql  = 'SELECT blockedsku FROM `feedconfig_blockedsku` where `supplier`="AXI"';
$row       = $connection->fetchOne($sql);
$adr_blk = array();
$adr_blk = explode(",",$row);



$file = '/home/kadoshop/domains/grotekadoshop.nl/public_html/axi/' . "/AXI.zip";
$cont= file_get_contents("https://www.axihandel.nl/datafeed.php?user=kn351851&key=6042d490d8573211372f6fea33c44364");
file_put_contents($file, $cont);
$zip = new ZipArchive;
$res = $zip->open($file);
if ($res === TRUE) {
    $zip->extractTo('/home/kadoshop/domains/grotekadoshop.nl/public_html/axi/');
    $f = $zip->getNameIndex(0);
    rename('/home/kadoshop/domains/grotekadoshop.nl/public_html/axi/' . $f, '/home/kadoshop/domains/grotekadoshop.nl/public_html/axi/'. 'AXI'.".xml" ) ;
    $zip->close();
} else {
    echo "error during unzip process";
}

echo "===feed downloaded==starting csv making".PHP_EOL;

$dir='/home/kadoshop/domains/grotekadoshop.nl/public_html/axi/AXI.xml';
$html   = file_get_contents($dir);
$invalid_characters = '/[^\x9\xa\x20-\xD7FF\xE000-\xFFFD]/';
$html = preg_replace($invalid_characters, '', $html);
$xml = simplexml_load_string($html);


$xml_axi = array();

foreach($xml->product as $a) {
	
	$sku = (string) $a->articleNumber;
	$sku = "XI-".$sku;
	//echo $sku."<br/>";
	
	$price = str_replace(",",".", str_replace(".","", (string) $a->price));
		$new_prc = '';
		foreach($adr as $adr1){
			$sep_str = explode(":",$adr1);
			$rng = $sep_str[0];
			$evl = str_replace("price","$price",$sep_str[1]);
			$evl = 'return '.$evl.";";
			//echo '<br/>'.$evl;
			$rng_arr = explode("-",$rng);
			$rng_l = $rng_arr[0];
			$rng_h = $rng_arr[1];
			if($price>$rng_l and $price<=$rng_h){
				 $new_prc = eval($evl);
			}
		}
	$new_prc = nicePriceToro($new_prc);
	$new_prc = number_format($new_prc, 2);
	$qty = (string) $a->stock == "true" ? 10:0 ;
	$lev = (string) $a->delivery;
	
	$status = 2;
	
	if($qty>5){
		$status = 1;
	}
	if($lev=='Per direkt leverbaar' or $lev=='Direct leverbaar' or $lev=='Voorradig 1 - 2 werkdagen'){
		$status = 1;
	}else{
		$status = 2;
	}
	if(in_array($sku,$adr_blk)){
		$status = 2;
	}
	


	$xml_axi[$sku] = $new_prc."~~".$qty."~~".$status;

}
//die;
//echo "<pre>";print_r($xml_axi);echo "</pre>";exit;
//echo $xml_axi['XI-11914']."******"

$fx = fopen('/home/kadoshop/domains/grotekadoshop.nl/public_html/var/import/new_axi_update.csv', 'w');
$read = array("sku","price","qty","status","is_in_stock","websites","msg");
fputcsv($fx,$read);
$store_id = 18;
$all_axi = array();
$file = fopen("/home/kadoshop/domains/grotekadoshop.nl/public_html/all/axi.csv","r");
$enabled_counter=0;
$disabled_counter=0;
while(!feof($file)){
    $getRow = fgetcsv($file,8196,',');
	if(trim($getRow[0])){

		$mage_sku = trim($getRow[0]);
		$mage_prc = trim($getRow[1]);
		$mage_stk = trim($getRow[2]);
		$mage_sta = trim($getRow[3]);
		$mage_id  = trim($getRow[4]);
		
		
		if(!isset($xml_axi[$mage_sku])){
			//echo $mage_sku.' sku not in feed.'.PHP_EOL;
			echo $mage_sku.' sku not present in feed'.PHP_EOL;
				echo "writing in csv".PHP_EOL;
			$websites = 'base,grotekadoshop,partybase,goedkopepartytent';
			$read_admin1= array($mage_sku,$mage_prc,$mage_stk,2,0,$websites,"Not in feed");
			fputcsv($fx,$read_admin1);
			$disabled_counter++;
			
			for ($store_id=17;$store_id<=19;$store_id++){
				$sql = 'INSERT INTO ess_products_queue (product_id, store_id) VALUES ('.$mage_id.','.$store_id.')';
				$connection->query($sql);
			}
		}else{
			
			
			
			$fed_sr = $xml_axi[$mage_sku];
			$change = true;
			$msg = '';
			$srt_sep = explode("~~",$fed_sr);
			$feed_price = $srt_sep[0];
			$feed_stk = $srt_sep[1];
			$feed_sta = $srt_sep[2];
			if($mage_prc!=$feed_price){
				$change = true;
				$msg = 'price';
			}
			if($mage_stk!=$feed_stk){
				$change = true;
				$msg.= ' stock';
			}
			if($mage_sta!=$feed_sta){
				$change = true;
				$msg.= ' status';
			}
			
			if($feed_stk==10){
				$is_in_stock = 1;
			}else{
				$is_in_stock = 0;
			}
			
			if($change){
				echo $mage_sku.' sku present in feed==> '.$feed_stk."**".$feed_sta."**".$is_in_stock.PHP_EOL;
				echo "writing in csv".PHP_EOL;
				 $websites = 'base,grotekadoshop,partybase,goedkopepartytent';
				$read_admin2 = array($mage_sku,$feed_price,$feed_stk,$feed_sta,$is_in_stock,$websites,$msg." Changed");
				$enabled_counter++;
				//echo "<pre>"; print_r($read_admin2);
				//PHP_EOL;
				
				
				fputcsv($fx,$read_admin2);
				//die;
				for ($store_id=18;$store_id<=19;$store_id++){
					$sql = 'INSERT INTO ess_products_queue (product_id, store_id) VALUES ('.$mage_id.','.$store_id.')';
					$connection->query($sql);
				}
			}

		}

		
	}
	
}

fclose($fx);
echo "csv created";

$myfile = fopen("/home/kadoshop/domains/grotekadoshop.nl/public_html/vidaxl/tool_upload/state/magmistate", "w") or die("Unable to open file!");
$txt = "idle";
fwrite($myfile, $txt);
fclose($myfile);

exec('php /home/kadoshop/domains/grotekadoshop.nl/public_html/vidaxl/tool_upload/cli/magmi.cli.php -profile="Updateaxi" -mode="update"', $output);
echo PHP_EOL."Axi Update completed..".PHP_EOL;




$msg = "Total disabled approx".$disabled_counter."\nTotal Enabled Approx ".$enabled_counter;

// use wordwrap() if lines are longer than 70 characters
$msg = wordwrap($msg,70);

// send email
mail("isha.132.87@gmail.com","AXI FEED RESPONSE ".date('d/m/Y'),$msg);

//////////////////////////////////////////////////
/// index

//$idx_ids = ['catalog_product_price','catalog_product_flat','cataloginventory_stock'];
//$indexerFactory = $objectManager->get('Magento\Indexer\Model\IndexerFactory');
//
//foreach($idx_ids as $idx)
//{
//	echo "index : " . $idx;
//	$indexer = $indexerFactory->create()->load($idx);
//	$indexer->reindexAll();
//	echo " done\n\r";
//} 

// index
