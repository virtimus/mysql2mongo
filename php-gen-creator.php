<?php

if (!defined('HAVE_PHP_GEN_CREATOR')) {

abstract class  PHPGENCreator {

        public function __construct( $sqlMap =null,$parsed = false) {
        	$this->sqlMap = $sqlMap; 
        	$parents = array();
        	foreach ($this->sqlMap as $k =>$v){
        		if (isset($v['parent']))
        			$parents[($v['parent'])]=1;	
        	}       	
        	foreach ($this->sqlMap as $k =>$v){
        		if (isset($parents[($v['name'])]))
        			$this->sqlMap[$k]['.hasChildren']=1;
        	}
            if ($parsed) {
                $this->create($parsed);
            }
        }
        
        public function create($parsed) {
        	$this->parsed =&$parsed;

            $k = key($parsed);
            switch ($k) {

            case "UNION":
            case "UNION ALL":
                throw new UnsupportedFeatureException($k);
                break;
            case "SELECT":
                $this->created = $this->processSelectStatement($parsed);
                break;
            case "INSERT":
                $this->created = $this->processInsertStatement($parsed);
                break;
            case "DELETE":
                $this->created = $this->processDeleteStatement($parsed);
                break;
            case "UPDATE":
                $this->created = $this->processUpdateStatement($parsed);
                break;
            default:
                throw new UnsupportedFeatureException($k);
                break;
            }
            return $this->created;
        }		
		
protected abstract function processSelectStatement($parsed); 	

		protected function ftrim($what){
			return trim($what,"`\"'");
		}
	
		protected function &getCollInfoByProp($prop,$val){
			$ret = null;
			if ($this->sqlMap == null ) return $ret;
			foreach($this->sqlMap as $k=>$v){
				if ($v[($prop)]==$val){
					$ret = $v;
					break;
				}
			}
			return $ret;			
		}

		protected function &getCollInfoByName($cname){
			return $this->getCollInfoByProp('name',$cname);
		}

		protected function &getCollInfoByTable($tablename){
			return $this->getCollInfoByProp('table',$tablename);

			}
			
		protected function getFieldsDataFromInsertValues($f,$v){
			$ret= array();
			foreach($f['columns'] as $k =>$v2){
				$v2 = $this->ftrim($v2['base_expr']);
				$v[0]['data'][$k]['.base_expr_trim'] = $this->ftrim($v[0]['data'][$k]['base_expr']);
				$ret[$v2]=$v[0]['data'][$k];
			}
			return $ret;
		}		

		protected function getParentCollInfoByCollInfo($cInfo){
			if (isset($cInfo['parent'])){
				//cho 'isset';
				return $this->getCollInfoByName($cInfo['parent']);
			}
			else {
				//cho 'isnotset';
				return null;
			}
		}
		
		protected function &getRootCollInfoByCollInfo(&$cinfo){
			if ($cinfo == null) return null;
			if (isset($cinfo['collname'])){ // root found
				//return $cinfo['collname'];
				return $cinfo;
			} else if (isset($cinfo['.parentCollInfo'])){// allready cached
				return $this->getRootCollInfoByCollInfo($cinfo['.parentCollInfo']);
			} else {
				$pcinfo = $this->getParentCollInfoByCollInfo($cinfo);
				$cinfo['.parentCollInfo']=&$pcinfo; // some cache is goood ... :)
				return $this->getRootCollInfoByCollInfo($pcinfo);
			}
		}
		
		protected function parsePath($tpath, &$fieldsData){
			//TODO more advanced parsing of path
			$ts = explode('.',$tpath);
			$l = count($ts);
			$ta = array();
			$rf = array();
			$tf = array();
			foreach($ts as $tk => $tv){
				$tvt = $tv;
				if ($tv{0}=='[') {
					// name of referenced field
					$tn=trim($tv,'[]');
					$tvt = $fieldsData[($tn)]['.base_expr_trim'];
					$tv = $fieldsData[($tn)]['base_expr'];
						
				}
				if ($tk == 0) { // first element points root
					if ($tn!=null)
						$rf['name'] = $tn;
					$rf['value']= $tv;
					$rf['valueT']= $tvt;
				}
				if ($tk == $l-1){//last element points target
					if ($tn!=null)
						$tf['name'] = $tn;
					$tf['value']= $tv;
					$tf['valueT']= $tvt;
				}
				$ta[($tk)]=$tvt;
			}
			return array('rf'=>$rf,'tf'=>$tf,'ta'=>$ta);		
			
		}
		
		private function prepareFDForCache($fieldsData){
			return gzcompress(serialize($fieldsData));
		}
		
		private function getFDFromCache($fd){
			return unserialize(gzuncompress($fd));
		}
		
		protected function getTargetPathInfo(&$collInfo,$fieldsData=array()){
			$tpath = $collInfo['path'];
			/*foreach($fieldsData as $f => $fd){
				$tpath = str_replace('['.$f.']',$fd['.base_expr_trim'],$tpath);
			}*/
			$pres = $this->parsePath($tpath, $fieldsData);
			//$pres = array();

			$ta = &$pres['ta'];
			$rf = &$pres['rf'];
			$tf = &$pres['tf'];
			//TODO mode complex cases than just mapping by id
			$updateCond = '_id : '.$ta[0]; 
			unset($ta[0]);
			$updatePath = implode('.',$ta);
			$pInfo = array('path'=>$collInfo['path'],
					'pathProcessed'=>$tpath,
					'updateCond'=>$updateCond,// update condiftion
					'updatePath'=>$updatePath,// path for placing update
					'.collInfo'=>&$collInfo, //collInfo for reference
					'rfValue'=>$rf['value'],//root field value
					'rfValueT'=>$rf['valueT'],//trimmed
					'tfValue'=>$tf['value'],//root field value
					'tfValueT'=>$tf['valueT'],//trimmed
					);
			if (isset($rf['name'])) $pInfo['rfName']=$rf['name'];
			if (isset($tf['name'])) $pInfo['tfName']=$tf['name'];
			if (isset($collInfo['value'])){
				$pres = $this->parsePath($collInfo['value'], $fieldsData);
				if (isset($pres['tf']['name'])) $pInfo['tvName']=$pres['tf']['name'];
				$pInfo['tvValue']=$pres['tf']['value'];
			}			
			// set data cache for nested docs
			if ($collInfo['.hasChildren']==1)
				$this->fieldDataCache[($collInfo['table'])][($pInfo['tfValueT'])]=$this->prepareFDForCache($fieldsData);
			
			//TODO full parent collinfo processing
			if (isset($collInfo['.parentCollInfo']))
				if (!isset($collInfo['.parentCollInfo']['collname'])){ // parent not root - need to process pathInfo
					$ppath = $collInfo['.parentCollInfo']['path'];
					$fieldsDataP = $this->getFDFromCache($this->fieldDataCache[($collInfo['.parentCollInfo']['table'])][($pInfo['rfValueT'])]);
					//TODO full recursive processing - up now - only 1 parent level supported
					//should be replaced by call to $this->getTargetPathInfo (where from to take fieldsData ?
					
/*					$pres = $this->parsePath($ppath, $fieldsDataP);
					$ta = &$pres['ta'];
					// dig parent data switch updateCond to parent id
					$pInfo['updateCond'] = '_id : '.$ta[0];
					unset($ta[0]);
					$updatePathP = implode('.',$ta);*/
						
					$pInfoP =$this->getTargetPathInfo($collInfo['.parentCollInfo'],$fieldsDataP);
					$updatePathP = $pInfoP['updatePath'];
					$pInfo['updateCond'] = $pInfoP['updateCond'];
					
					$pInfo['updatePath'] = $updatePathP.'.'.$pInfo['updatePath'];
					
					
				}
			return $pInfo;
		} 
		

}//class


}