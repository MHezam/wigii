<?php
if(file_exists(CLIENT_CONFIG_PATH.'CustomizedElementEvaluator.php')) include_once(CLIENT_CONFIG_PATH.'CustomizedElementEvaluator.php'); /* loads parent class */
/**
 *  Rise.wigii.org NCD Web Service
 *  Wigii is developed to inspire humanity. To Humankind we offer Gracefulness, Righteousness and Goodness.
 *  
 *  Wigii is free software: you can redistribute it and/or modify it 
 *  under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, 
 *  or (at your option) any later version.
 *  
 *  Wigii is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *  See the GNU General Public License for more details.
 *
 *  A copy of the GNU General Public License is available in the Readme folder of the source code.  
 *  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @copyright  Copyright (c) 2016  Wigii.org
 *  @author     <http://www.wigii.org/system>      Wigii.org 
 *  @link       <http://www.wigii-system.net>      <https://github.com/wigii/wigii>   Source Code
 *  @license    <http://www.gnu.org/licenses/>     GNU General Public License
 */
 
 /**
  * Rise.wigii.org NCD Web Service, intended to be called through the JS client wigii-rise-ncd
  * Created by Camille Weber on 05.09.2016
  * Modified by Camille Weber on 18.04.2018 to include Move Forward project
  */
class RiseNCDElementEvaluator extends CustomizedElementEvaluator
{		
	private $_debugLogger;
	private $_executionSink;
	
	// Dependency injection
	
	private function debugLogger()
	{
		if(!isset($this->_debugLogger))
		{
			$this->_debugLogger = DebugLogger::getInstance("RiseNCDElementEvaluator");
		}
		return $this->_debugLogger;
	}
	private function executionSink()
	{
		if(!isset($this->_executionSink))
		{
			$this->_executionSink = ExecutionSink::getInstance("RiseNCDElementEvaluator");
		}
		return $this->_executionSink;
	}
	
	private $rootPrincipal;
	public function setRootPrincipal($rootPrincipal){
		$this->rootPrincipal = $rootPrincipal;
	}
	
	/**
	 * @return Principal
	 */
	protected function getRootPrincipal() {
		return $this->rootPrincipal;
	}
	
	// Rise.wigii.org NCD Web Service
	
	/**
	 * Returns Rise NCD Web Service version
	 * FuncExp signature : <code>riseNcd_version()</code><br/>
	 * @return String className followed by Wigii-system version 
	 */
	public function riseNcd_version($args) {
		return $this->evaluateFuncExp(fx('concat', get_class($this), ' ', VERSION_LABEL));
	}	

	/**
	 * Pings Rise NCD Web Service and returns current date, current username and current Wigii-system version.
	 * FuncExp signature : <code>riseNcd_ping()</code><br/>
	 * @return String
	 */
	public function riseNcd_ping($args) {
		return $this->evaluateFuncExp(fx('concat', fx('txtDate'), "  Hello, ", fx('sysUsername'), "! I am the ", VERSION_LABEL, " to assist you in your programming learning."));
	}
	
	/**
	 * Stores JSON data into a shared storage place, identified by an element ID 
	 * FuncExp signature : <code>riseNcd_storeData(elementId,keyField,jsonData)</code><br/>
	 * Where arguments are :
	 * - Arg(0) elementId: Int. Element ID located in https://rise.wigii.org/#NCD/Espace/, accessible by the user in which to store the JSON data
	 * - Arg(1) keyField: String. The name of the field in the object to be considered as a primary key. If not provided, always appends to existing data.
	 * - Arg(2) data: String. The JSON string to merge with existing data. If not provided, assumes the data is in the POST.
	 * @return Boolean returns true 
	 * @throws FuncExpEvalException in case of error.
	 */
	public function riseNcd_storeData($args) {
		$p = $this->getPrincipal();
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("riseNcd_storeData takes three arguments: the element ID, the key field and the JSON data", FuncExpEvalException::INVALID_ARGUMENT);
		$elementId = $this->evaluateArg($args[0]);
		if($nArgs>1) $keyField = $this->evaluateArg($args[1]);
		else $keyField = null;
		if($nArgs>2) {
			$data = $this->evaluateArg($args[2]);
			if(!empty($data)) {
				$data=json_decode($data);
				if(json_last_error()) throw new FuncExpEvalException("invalid json data.\n".$data, FuncExpEvalException::INVALID_ARGUMENT);
			}			
		}
		else {
			$data = ServiceProvider::getWigiiBPL()->dataFetchFromPost($p, $this, wigiiBPLParam('type','json'));
		}
		// Stores data
		if(!empty($data)) {
			// gets storage container
			$fsl = fsl(fs('dataStorage'));
			$element = sel($p, elementP($elementId,$fsl), dfasl(dfas("NullDFA")));
			if(!isset($element)) throw new FuncExpEvalException("no existing or accessible element was found with ID ".$elementId, FuncExpEvalException::INVALID_ARGUMENT);
			$element = $element->getDbEntity();
			// Extracts existing data
			$existingData = $element->getFieldValue('dataStorage');
			/*
			$html2text = new Html2text();
			$html2text->setHtml($existingData);
			$existingData =  $html2text->getText();
			*/
			$temp=json_decode($existingData);
			if(json_last_error()) throw new FuncExpEvalException("invalid existing json data. Please correct stored data in element $elementId\n".$existingData, FuncExpEvalException::INVALID_ARGUMENT);
			$existingData = $temp;
			
			// Merges existing data with new data
			// if no keyField:
			/* posted data is a single object and existing data is a single object or empty => replace with posted object
			 * posted data is a single object and existing data is an array => append posted data to the array
			 * posted data is an array and existing data is a single object or empty => replace with posted array
			 * posted data is an array and existing data is an array => replace with posted array
			 */
			 if(empty($keyField)) {
				if(is_array($data)) $existingData = $data;
				elseif(is_array($existingData)) $existingData[] = $data;
				else $existingData = $data;
			 }
			 // if keyField: merges incoming array or single object with existing array
			else {
				// builds indexed data
				$indexedData = array();
				if(is_array($existingData)) {
					foreach($existingData as $obj) {
						$indexedData[$obj->{$keyField}] = $obj;
					}
				}
				else $indexedData[$existingData->{$keyField}] = $existingData;
				
				// replaces existing data with new data based on keyField
				if(is_array($data)) {
					foreach($data as $obj) {
						$indexedData[$obj->{$keyField}] = $obj;
					}
				}
				else $indexedData[$data->{$keyField}] = $data;
				
				// rebuilds list
				$existingData = array_values($indexedData);
			}
			
			// Persists new data
			$element->setFieldValue(json_encode($existingData),'dataStorage');
			ServiceProvider::getElementService()->updateElement($p,$element,$fsl);
		}
		return true;
	}
	
	/**
	 * Gets JSON data from a shared storage place, identified by an element ID 
	 * FuncExp signature : <code>riseNcd_getData(elementId)</code><br/>
	 * Where arguments are :
	 * - Arg(0) elementId: Int. Element ID located in https://rise.wigii.org/#NCD/Espace/, accessible by the user from which to get the JSON data
	 * @return String the JSON data
	 * @throws FuncExpEvalException in case of error.
	 */
	public function riseNcd_getData($args) {
		$p = $this->getPrincipal();
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("riseNcd_storeData takes one argument: the element ID", FuncExpEvalException::INVALID_ARGUMENT);
		$elementId = $this->evaluateArg($args[0]);
		
		// Gets storage container
		$element = sel($p, elementP($elementId,fsl(fs('dataStorage'))), dfasl(dfas("NullDFA")));
		if(!isset($element)) throw new FuncExpEvalException("no existing or accessible element was found with ID ".$elementId, FuncExpEvalException::INVALID_ARGUMENT);
		$element = $element->getDbEntity();
		// Returns existing data
		$returnValue = $element->getFieldValue('dataStorage');
		/*
		$html2text = new Html2text();
		$html2text->setHtml($returnValue);
		$returnValue =  $html2text->getText();
		*/
		return $returnValue;	
	}
	
	/**
	 * Creates a new shared storage place into a given folder.
	 * FuncExp signature : <code>riseNcd_createDataStorage(groupId,description)</code><br/>
	 * Where arguments are :
	 * - Arg(0) groupId: Int. Group ID located in https://rise.wigii.org/#NCD/Espace/, accessible by the user in which create a data storage element.
	 * - Arg(1) description: String. A short description of the data storage goal and purpose
	 * @return Int returns the ID of the created element
	 * @throws FuncExpEvalException in case of error.
	 */
	public function riseNcd_createDataStorage($args) {
		$p = $this->getPrincipal();
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("riseNcd_createDataStorage takes two arguments: the group ID in which to create the element and a description", FuncExpEvalException::INVALID_ARGUMENT);
		$groupId = $this->evaluateArg($args[0]);
		if($nArgs>1) $description = $this->evaluateArg($args[1]);
		else $description = null;
		
		// Creates data storage data
		$element = sel($p, newElement($groupId),dfasl(
			dfas("ElementSetterDFA", "setCalculatedFieldSelectorMap", cfsMap(
				cfs(fs("initiation"),$description),
				cfs(fs("dataStorage"),"[]"),
				cfs(fs_e("state_blocked"),true)
			)),
			dfas("ElementDFA","setMode",1),
			dfas("NullDFA")
		));
		return $element->getId();		
	}
	
	// Projet ATELIER ENCODE / PARTAGE
	
	/**
	 * Sauvegarde un article du projet partage de l'Atelier Encode 
	 * FuncExp signature : <code>partage_sauverArticle(repositoryId,withContent,jsonArticle)</code><br/>
	 * Where arguments are :
	 * - Arg(0) repositoryId: Int. Element ID localisé à https://rise.wigii.org/#NCD/Espace/, contenant la base de données des articles du projet partage
	 * - Arg(1) withContent: Boolean. Si vrai, alors les articles contiennent du contenu, sinon uniquement les infos de l'article sont mis à jour.
	 * - Arg(2) jsonArticle: String. Une liste d'article sérialisée en JSON.
	 * @return Boolean returns true 
	 * @throws FuncExpEvalException in case of error.
	 */
	public function partage_sauverArticle($args) {
		$p = $this->getPrincipal();
		$timestamp = floor(microtime(true)*1000);
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("partage_sauverArticle prend trois arguments: l'ID de l'élément stockant les articles, si du contenu doit être sauvé et les articles en JSON", FuncExpEvalException::INVALID_ARGUMENT);
		$repositoryId = $this->evaluateArg($args[0]);
		$keyField = 'id';
		if($nArgs>1) $withContent = ($this->evaluateArg($args[1])==true);
		else $withContent = false;
		if($nArgs>2) {
			$data = $this->evaluateArg($args[2]);
			if(!empty($data)) {
				$data=json_decode($data);
				if(json_last_error()) throw new FuncExpEvalException("invalid json data.\n".$data, FuncExpEvalException::INVALID_ARGUMENT);
			}			
		}
		else {
			$data = ServiceProvider::getWigiiBPL()->dataFetchFromPost($p, $this, wigiiBPLParam('type','json'));
		}
		// Stores data
		if(!empty($data)) {
			// gets storage container
			$fsl = fsl(fs('dataStorage'));
			$element = sel($p, elementP($repositoryId,$fsl), dfasl(dfas("NullDFA")));
			if(!isset($element)) throw new FuncExpEvalException("no existing or accessible element was found with ID ".$repositoryId, FuncExpEvalException::INVALID_ARGUMENT);
			$element = $element->getDbEntity();
			// Extracts existing data
			$existingData = $element->getFieldValue('dataStorage');			
			$temp=json_decode($existingData);
			if(json_last_error()) throw new FuncExpEvalException("invalid existing json data. Please correct stored data in element $repositoryId\n".$existingData, FuncExpEvalException::INVALID_ARGUMENT);
			$existingData = $temp;
			
			// Merges existing data with new data
			
			// builds indexed data
			$indexedData = array();
			if(is_array($existingData)) {
				foreach($existingData as $obj) {
					$indexedData[$obj->{$keyField}] = $obj;
				}
			}
			else $indexedData[$existingData->{$keyField}] = $existingData;
			
			// replaces existing data with new data based on keyField
			if(is_array($data)) {
				foreach($data as $obj) {
					$existingArticle = $indexedData[$obj->{$keyField}];
					if($existingArticle) {
						// can change only if principal owns this article
						if($p->getRealUsername() == $existingArticle->auteur) {
							// mise à jour de l'article complet
							if($withContent) $indexedData[$obj->{$keyField}] = $obj;
							// mise à jour du titre et de la date
							else {
								$existingArticle->titre = $obj->titre;
								$existingArticle->dateModification = $obj->dateModification;
							}
						}						
					}
					else $indexedData[$obj->{$keyField}] = $obj;
				}
			}
			else {
				$existingArticle = $indexedData[$data->{$keyField}];
				if($existingArticle) {
					// can change only if principal owns this article
					if($p->getRealUsername() == $existingArticle->auteur) {
						// mise à jour de l'article complet
						if($withContent) $indexedData[$data->{$keyField}] = $data;
						// mise à jour du titre et de la date
						else {
							$existingArticle->titre = $data->titre;
							$existingArticle->dateModification = $data->dateModification;
						}
					}						
				}
				else $indexedData[$data->{$keyField}] = $data;
			}
					
			// rebuilds list
			$existingData = array_values($indexedData);			
			
			// Persists new data
			$element->setFieldValue(json_encode($existingData),'dataStorage');
			ServiceProvider::getElementService()->updateElement($p,$element,$fsl);
		}
		return true;
	}
	/**
	 * Incrémente ou décrémente la position d'un article du projet partage de l'Atelier Encode 
	 * FuncExp signature : <code>partage_rateArticle(repositoryId,rating,jsonArticle)</code><br/>
	 * Where arguments are :
	 * - Arg(0) repositoryId: Int. Element ID localisé à https://rise.wigii.org/#NCD/Espace/, contenant la base de données des articles du projet partage
	 * - Arg(1) rating: Int. Incrément ou décrément (si négatif) à ajouter à la position des articles postés.
	 * - Arg(2) jsonArticle: String. Une liste d'article sérialisée en JSON.
	 * @return Boolean returns true 
	 * @throws FuncExpEvalException in case of error.
	 */
	public function partage_rateArticle($args) {
		$p = $this->getPrincipal();
		$timestamp = floor(microtime(true)*1000);
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<2) throw new FuncExpEvalException("partage_rateArticle prend trois arguments: l'ID de l'élément stockant les articles, le rating et les articles en JSON", FuncExpEvalException::INVALID_ARGUMENT);
		$repositoryId = $this->evaluateArg($args[0]);
		$keyField = 'id';
		$rating = $this->evaluateArg($args[1]);		
		if($nArgs>2) {
			$data = $this->evaluateArg($args[2]);
			if(!empty($data)) {
				$data=json_decode($data);
				if(json_last_error()) throw new FuncExpEvalException("invalid json data.\n".$data, FuncExpEvalException::INVALID_ARGUMENT);
			}			
		}
		else {
			$data = ServiceProvider::getWigiiBPL()->dataFetchFromPost($p, $this, wigiiBPLParam('type','json'));
		}
		// Stores data
		if(!empty($data)) {
			// gets storage container
			$fsl = fsl(fs('dataStorage'));
			$element = sel($p, elementP($repositoryId,$fsl), dfasl(dfas("NullDFA")));
			if(!isset($element)) throw new FuncExpEvalException("no existing or accessible element was found with ID ".$repositoryId, FuncExpEvalException::INVALID_ARGUMENT);
			$element = $element->getDbEntity();
			// Extracts existing data
			$existingData = $element->getFieldValue('dataStorage');			
			$temp=json_decode($existingData);
			if(json_last_error()) throw new FuncExpEvalException("invalid existing json data. Please correct stored data in element $repositoryId\n".$existingData, FuncExpEvalException::INVALID_ARGUMENT);
			$existingData = $temp;
			
			// Merges existing data with new data
			
			// builds indexed data
			$indexedData = array();
			if(is_array($existingData)) {
				foreach($existingData as $obj) {
					$indexedData[$obj->{$keyField}] = $obj;
				}
			}
			else $indexedData[$existingData->{$keyField}] = $existingData;
			
			// replaces existing data with new data based on keyField
			if(is_array($data)) {
				foreach($data as $obj) {
					$existingArticle = $indexedData[$obj->{$keyField}];
					if($existingArticle) {
						$existingArticle->position += $rating;
					}
				}
			}
			else {
				$existingArticle = $indexedData[$data->{$keyField}];
				if($existingArticle) {
					$existingArticle->position += $rating;
				}
			}
			
			// rebuilds list
			$existingData = array_values($indexedData);			
			
			// Persists new data
			$element->setFieldValue(json_encode($existingData),'dataStorage');
			ServiceProvider::getElementService()->updateElement($p,$element,$fsl);
		}
		return true;
	}
	/**
	 * Sauvegarde des contenus d'articles du projet partage de l'Atelier Encode 
	 * FuncExp signature : <code>partage_sauverContenu(repositoryId,jsonContenu)</code><br/>
	 * Where arguments are :
	 * - Arg(0) repositoryId: Int. Element ID localisé à https://rise.wigii.org/#NCD/Espace/, contenant la base de données des articles du projet partage
	 * - Arg(1) jsonContenu: String. Une liste de contenus d'articles sérialisée en JSON.
	 * @return Boolean returns true 
	 * @throws FuncExpEvalException in case of error.
	 */
	public function partage_sauverContenu($args) {
		$p = $this->getPrincipal();
		$timestamp = floor(microtime(true)*1000);
		// Extracts arguments
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("partage_sauverContenu prend deux arguments: l'ID de l'élément stockant les articles, et le contenu des articles en JSON", FuncExpEvalException::INVALID_ARGUMENT);
		$repositoryId = $this->evaluateArg($args[0]);
		$keyField = 'id';		
		if($nArgs>1) {
			$data = $this->evaluateArg($args[1]);
			if(!empty($data)) {
				$data=json_decode($data);
				if(json_last_error()) throw new FuncExpEvalException("invalid json data.\n".$data, FuncExpEvalException::INVALID_ARGUMENT);
			}			
		}
		else {
			$data = ServiceProvider::getWigiiBPL()->dataFetchFromPost($p, $this, wigiiBPLParam('type','json'));
		}
		// Stores data
		if(!empty($data)) {
			// gets storage container
			$fsl = fsl(fs('dataStorage'));
			$element = sel($p, elementP($repositoryId,$fsl), dfasl(dfas("NullDFA")));
			if(!isset($element)) throw new FuncExpEvalException("no existing or accessible element was found with ID ".$repositoryId, FuncExpEvalException::INVALID_ARGUMENT);
			$element = $element->getDbEntity();
			// Extracts existing data
			$existingData = $element->getFieldValue('dataStorage');			
			$temp=json_decode($existingData);
			if(json_last_error()) throw new FuncExpEvalException("invalid existing json data. Please correct stored data in element $repositoryId\n".$existingData, FuncExpEvalException::INVALID_ARGUMENT);
			$existingData = $temp;
			
			// Merges existing data with new data
			
			// builds indexed data
			$indexedData = array();
			$indexedArticles = array();
			if(is_array($existingData)) {
				foreach($existingData as $obj) {
					$indexedArticles[$obj->{$keyField}] = $obj;
					$existingContent = $obj->listeContenu;
					foreach($existingContent as $content) {
						$indexedData[$content->{$keyField}] = $content;
					}					
				}
			}
			else {
				$indexedArticles[$existingData->{$keyField}] = $existingData;
				$existingContent = $existingData->listeContenu;
				foreach($existingContent as $content) {
					$indexedData[$content->{$keyField}] = $content;
				}	
			}
			
			// replaces existing data with new data based on keyField
			if(is_array($data)) {
				foreach($data as $obj) {
					$existingContent = $indexedData[$obj->{$keyField}];
					if($existingContent) {
						// can change only if principal owns this content
						if($p->getRealUsername() == $existingContent->auteur) {
							$existingContent->texte = $obj->texte;
							$existingContent->date = $obj->date;
						}						
					}
					// else adds content to existing article
					else {
						list($articleId,$contentId) = explode('_',$obj->{$keyField});
						$existingArticle = $indexedArticles[$articleId];
						if($existingArticle) {
							if(!is_array($existingArticle->listeContenu)) $existingArticle->listeContenu = array();
							$existingArticle->listeContenu[] = $obj;
						}
					}
				}
			}
			else {
				$existingContent = $indexedData[$data->{$keyField}];
				if($existingContent) {
					// can change only if principal owns this content
					if($p->getRealUsername() == $existingContent->auteur) {
						$existingContent->texte = $data->texte;
						$existingContent->date = $data->date;
					}						
				}
				// else adds content to existing article
				else {
					list($articleId,$contentId) = explode('_',$data->{$keyField});
					$existingArticle = $indexedArticles[$articleId];
					if($existingArticle) {
						if(!is_array($existingArticle->listeContenu)) $existingArticle->listeContenu = array();
						$existingArticle->listeContenu[] = $data;
					}
				}
			}			
			
			// rebuilds list
			$existingData = array_values($indexedArticles);			
			
			// Persists new data
			$element->setFieldValue(json_encode($existingData),'dataStorage');
			ServiceProvider::getElementService()->updateElement($p,$element,$fsl);
		}
		return true;
	}
	
	
	// Projet ATELIER ENCODE / MOVE FORWARD
	
	/**
	 * Returns the code of an object stored into Move Forward's catalog 
	 * FuncExp signature : <code>mf_getCode(objectId)</code><br/>
	 * Where arguments are :
	 * - Arg(0) objectId: String. Move Forward catalog object ID
	 * @return StdClass of the form 
	 * { id: object ID in catalog,
	 *  type: svg|ncd type of code fetched,
	 *  svg: String. SVG code fetched if defined,
	 *  ncd: String. WNCD code fetched if defined
	 *  objectName: String. Name of object in catalog
	 *  objectType: String. Type of object in catalog
	 *}
	 * If type is ncd both svg and ncd code can be defined. In that case, ncd holds an expression which uses in some way the svg code.
	 * @throws FuncExpEvalException in case of error.
	 */
	public function mf_getCode($args) {
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs<1) throw new FuncExpEvalException("mf_getCode gets at least on argument which is the object ID in the catalog", FuncExpEvalException::INVALID_ARGUMENT);
		$p = $this->getPrincipal();
		$returnValue = null;
		// fetches element in catalog given its ID
		$element = ServiceProvider::getWigiiBPL()->elementFetchByKey($p,$this,wigiiBPLParam(
			"keyField","objectID",
			"keyValue",$this->evaluateArg($args[0]),
			"groupId",$this->riseNcd_dataConfig()->moveForwardCatalogLx,
			"fsl",fsl(fs('srcFile','name'),fs('srcFile','path'),fs('srcFile','size'),fs('srcFile','mime'),fs('srcFile','type'),
				fs('srcCode'), fs('objectName'),fs('objectType'),fs('objectID')
			)
		));
		if($element) {
			$element = $element->getDbEntity();			
			$codeType=null;			
			// extracts svg
			if(!is_null($element->getFieldValue("srcFile","path")) && $element->getFieldValue("srcFile","type") == '.svg') {
				$codeType = 'svg';
				$svgCode = sel($p,elementFile2df($element,"srcFile"),dfasl(
					dfas("StringBufferDFA")
				));
			}
			else $svgCode = null;
			// validates code type
			if(!is_null($element->getFieldValue("srcCode"))) $codeType = 'ncd';
			if(!isset($codeType)) throw new FuncExpEvalException("catalog object ".$element->getFieldValue("objectID")." of type ".$element->getFieldValue("objectType")." has no attached NCD or SVG code.", FuncExpEvalException::INVALID_ARGUMENT);
			 
			// fills code object
			$returnValue = array();
			$returnValue['id']= $element->getFieldValue("objectID");
			$returnValue['type']= $codeType;
			if(!empty($svgCode)) $returnValue['svg']= $svgCode;
			if($codeType=='ncd') $returnValue['ncd']= $element->getFieldValue("srcCode");
			if(!is_null($element->getFieldValue("objectName"))) $returnValue['objectName']= $element->getFieldValue("objectName");
			if(!is_null($element->getFieldValue("objectType"))) $returnValue['objectType']= $element->getFieldValue("objectType");
			$returnValue = (object)$returnValue;
		}
		return $returnValue;
	}
	
	/**
	 * Encodes a given stdClass or Array as JSON
	 * FuncExp signature : <code>mf_jsonEncode(obj)</code><br/>
	 * Where arguments are :
	 * - Arg(0) obj: StdClass|Array. StdClass instance or Array to encode as JSON string
	 * @return String encode JSON string
	 * @throws FuncExpEvalException in case of error.
	 */
	public function mf_jsonEncode($args) {
		$returnValue = null;
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs>0) {
			$returnValue = $this->evaluateArg($args[0]);
			if(is_array($returnValue) || $returnValue instanceof stdClass) {
				$returnValue = @json_encode($returnValue,JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK|JSON_UNESCAPED_SLASHES);
				if(json_last_error() !== JSON_ERROR_NONE) throw new FuncExpEvalException('JSON encoding syntax error', ServiceException::INVALID_ARGUMENT);
			}
			elseif(!is_string($returnValue)) throw new FuncExpEvalException('obj is not a JSON string', ServiceException::INVALID_ARGUMENT);
		}
		return $returnValue;
	}
	
	// Accessors
	
	private $dataConfig;
	
	/**
	 * Data configuration for Rise NCD Web Service
	 *@return StdClass an object with some configuration values used by Rise NCD Web Service
	 */
	protected function riseNcd_dataConfig() {
		if(!isset($this->dataConfig)) {
			$this->dataConfig = array();
			$this->dataConfig['moveForwardCatalogLx'] = lxEq(fs('id'),130);
			$this->dataConfig = (object)$this->dataConfig;
		}
		return $this->dataConfig;
	}
	
	// Core services
	
	/**
	 * Get the next code of a field in a group. This method select the last
	 * values within a the selected group (recursive) that starts with the CodePrefix and calculate the
	 * next value by adding 1
	 * FuncExp signature : <code>getNextCode(groupID or "namespace|module", fieldname, codePrefix, digits=3, startValue=1)</code><br/>
	 * Where arguments are :
	 * - Arg(0) groupID: Int. The ID of the group in which to look at.
	 * 					 String: namespace|modulename the "|" is used as a separator between the namespace and the module. Groups containing "trahsbin" or "corbeille" are ignored.
	 * 					 String: id1|id2|id3... multiple group ids separated by a |
	 * - Arg(1) fieldname: String. The name of the field containing the code.
	 * - Arg(2) codePrefix: String. The prefix of the Dimension
	 * - Arg(3) digits: Int. Optional number of digit to use for the dimension numbering. Default is 3.
	 * - Arg(4) startValue: Int. Optional start number from which to begin the incrementation. Default is 1.
	 * If -1, then uses lower case letters, starting from a (then b, c, d, e, ...)
	 * - Arg(5) attributeExpCacheToDelete: String Optional string to cleanup AttributeExp cache.
	 * @return String the value of the next code
	 */
	public function getNextCode($args){
		$rootGroupId = $this->evaluateArg($args[0]);
		$fieldname = $this->evaluateArg($args[1]);
		$codePrefix = $this->evaluateArg($args[2]);
		if($this->getNumberOfArgs($args) > 3){
			$digits = $this->evaluateArg($args[3]);
		} else {
			$digits = 3;
		}
		if($this->getNumberOfArgs($args)>4) {
			$startValue = $this->evaluateArg($args[4]);
		} else {
			$startValue = 1;
		}
		
		$p = $this->getRootPrincipal();
		$origNS = $p->getWigiiNamespace();
		$p->bindToWigiiNamespace($this->getPrincipal()->getWigiiNamespace());
		// builds dimension selector
		if(is_numeric($rootGroupId)){
			$selector = lxEq(fs('id'), $rootGroupId);			
		} else {
			$rootGroupId = explode("|", $rootGroupId);
			if(is_numeric($rootGroupId[0])){
				$selector = lxIn(fs('id'), $rootGroupId);				
			} else {
				$selector = lxAnd(lxEq(fs('module'), $rootGroupId[1]), lxEq(fs('wigiiNamespace'), $rootGroupId[0]), lxOr(lxEq(fs('id_group_parent'),0),lxIsNull(fs('id_group_parent'))), lxAnd(lxNotLike(fs('groupname'), '%trashbin%'), lxNotLike(fs('groupname'), '%corbeille%')));
			}
		}
		$returnValue = sel(
				$p,
				elementPList(
						lxInGR($selector),
						lf(NULL, lxLike(fs($fieldname), $codePrefix."%"), fskl(fsk($fieldname, "value", false)),1,1)
						),
				dfasl(
						dfas("MapElement2ValueDFA", "setElement2ValueFuncExp", fs($fieldname))
						)
				);
		$returnValue = str_replace($codePrefix, "", $returnValue);
		// if letter increment, starts with 'a'
		if($digits == -1 && !$returnValue) $returnValue = 'a';
		elseif(!$returnValue) $returnValue = $startValue;
		else $returnValue++;
		
		if($digits){
			$returnValue = str_pad($returnValue, $digits, "0", STR_PAD_LEFT);
		}
		$returnValue = $codePrefix.($returnValue);
		$p->bindToWigiiNamespace($origNS);
		
		if($this->getNumberOfArgs($args)>5) {
			// clears session cache
			$sessAS = ServiceProvider::getSessionAdminService();
			$sessAS->clearDataKey("AttributeExpConfigController_".md5($this->evaluateArg($args[5])));
		}
		
		return $returnValue;
	}
}