<?

//include_once('./../../php_profiler/profiler.inc');



function json_decode_nice($json, $assoc = FALSE){
	$count = 1;
	$count2 = 1;
	$json =str_replace("\r","\n",$json);
	while (($count>0) || ($count2>0)){
		$json = str_replace(" \n","\n",$json,$count);
		$json = str_replace("\t\n","\n",$json,$count2);
	}
	$json=preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $json);
    $json = str_replace(array("\n","\r"),"",$json);
	//$json = str_replace(array("\r\r","\r"),"",$json);
    $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
	//cho $json;
    return json_decode($json,$assoc);
} 
/*
 * pCache
 * 		part
 * 		parsed
 * 		next
 * 			part
 * 			value
 * 			next (or not set)
 * 				...
 * 
 */


function parseStoreCache($parsed,$sql){
	$kvl = strpos($sql,$parsed['VALUES'][0]['data'][0]['base_expr']);
	if ($kvl>0){
		$kv = substr($sql,0,$kvl);
		//$strval =  substr($sql,$kvl);
		//$a = str_getcsv ( $strval, ',', "'",  '\\' );

		$GLOBALS['pCache'][$kv]=$parsed;
		}
	
	}

function parseIfCached($sql){
	$ret = false;
	// only inserts now
	foreach($GLOBALS['pCache'] as $k =>$v){
		$l=strlen($k);
		if (substr($sql,0,$l)==$k){ // got right cache item ... probably
			$parsed = $v;
			$strval =  substr($sql,$l);
			$a = str_getcsv ( $strval, ',', "'",  '\\' );
			$l2=count($a)-1;
			$a[$l2]=str_replace(');','',$a[$l2]);						
			foreach ($parsed['VALUES'][0]['data'] as $kv => $vv){
					//$value = $
					// recurent func to process pCache 
					$parsed['VALUES'][0]['data'][$kv]['base_expr']=($vv['base_expr']{0}=="'")?"'".$a[$kv]."'":$a[$kv];
				}
			$ret = $parsed;
			break;
			} 
		}
	return $ret;
	}


function parseCached($parser,$sql,$c){
	if (($parsed=parseIfCached($sql))==false) {
		$parser->parse($sql, $c);
		$parsed = $parser->parsed;
		parseStoreCache($parsed,$sql);
		} 
	return $parsed;
	}

if (!isset($argv[1])){
	echo "Usage: php r2nconvert.php collectionName [number of 1k records in 1 file]\n";
	exit;	
	}

$fname = $argv[1];
$cmax = 0;
if (isset($argv[2]))
	$cmax = $argv[2]*1000;

$cnt = file_get_contents($fname.'.r2n');
$r2nMap = json_decode_nice(trim($cnt),true);
//cho json_last_error();
require_once(dirname(__FILE__) . '/../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../php-mongo-creator.php');

$parser = new PHPSQLParser();
$creator = new PHPMONGOCreator($r2nMap);

//$GLOBALS['prof'] = new Profiler( true ); // Output the profile information but no trace
$GLOBALS['pCache'] = array();
$n = 0;
$handle = fopen($fname.'.sql' , "r");
$handle_o = fopen($fname.'('.$n.').json' , "w+");

if ($handle) {
	$c=0;
   //$GLOBALS['prof']->startTimer( "main_loop" );
   while ((!feof($handle)) && (1/*$c<3000*/)) {
       $sql = fgets($handle);
	   if (substr($sql,0,6)=='INSERT'){
	   	    $c++;
	   		//$GLOBALS['prof']->startTimer( "main_parse" );
	   		$parsed = parseCached($parser,$sql,true);
			//$parser->parse($sql, true);
			//$GLOBALS['prof']->stopTimer( "main_parse" );
			//$GLOBALS['prof']->startTimer( "main_create" );
			$mongoStatement=$creator->create($parsed);
			//$mongoStatement=$creator->create($parser->parsed);
			//$GLOBALS['prof']->stopTimer( "main_create" );
			echo $mongoStatement."\n";
			fputs ($handle_o , $mongoStatement."\n");
			if (($cmax > 0) && ($c>$cmax)){
				echo "\n\n\n Closing file:".$fname.'('.$n.').json ...'."\n\n\n";
				fclose($handle_o);
				sleep(1);
				$n++;
				$c=0;
				$handle_o = fopen($fname.'('.$n.').json' , "w+");
			}
				
	   }
   }
   fclose($handle);
   fclose($handle_o);
    
   //$GLOBALS['prof']->stopTimer( "main_loop" );
   $handle_b = fopen('mimport_'.$fname.'.bat' , "w+");
   fputs ($handle_b ,'if "%1"=="" goto blank'."\n");
  
   for($i=0;$i<$n+1;$i++){
   		fputs ($handle_b , "mongo %1 < ".dirname(__FILE__).'/'.$fname.'('.$i.').json'."\n");
   		fputs ($handle_b , "echo \"".$fname.' - '.$i.'/'.($n)." ended ... \"\n");   	
   }

   fputs ($handle_b ,'goto done'."\n");
   fputs ($handle_b ,':BLANK'."\n");
   fputs ($handle_b ,'ECHO usage: mimport_'.$fname.' [dbname]'."\n");
   fputs ($handle_b ,':DONE'."\n");   
   fclose($handle_b);
} 


//print("<h4>The following is the profiler output</h4>");
//$GLOBALS['prof']->printTimers( true );
