<?php
/**
 *  This file is part of Wigii.
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
 * Wigii interface with Medidata (https://www.medidata.ch/)
 * Created by CWE on 19 sept 2018
 */
class WigiiMedidataFL extends FuncExpVMAbstractFL
{
	// Dependency injection
	
	private $_debugLogger;
	private function debugLogger()
	{
		if(!isset($this->_debugLogger))
		{
			$this->_debugLogger = DebugLogger::getInstance("WigiiMedidataFL");
		}
		return $this->_debugLogger;
	}

	private $trm;
	public function setTrm($trm) {
		$this->trm = $trm;
	}
	protected function getTrm() {
		if(!isset($this->trm)) {
			$this->trm = TemplateRecordManager::createInstance();
		}
		return $this->trm;
	}
	
	/**
	 * Gets a table with the medical cases attached to a customer<br/>
	 * FuncExp signature : <code>getCustomerMediCaseTable(customerNumber)</code><br/>
	 * Where arguments are :
	 * - Arg(0) customerNumber: String|LogExp. Contact number of type Customer for which to get the medical cases
	 * @return Array of StdClass instances of the form {contactNumber,caseNumber,caseLaw,caseDate,caseTariff}
	 */
	public function getCustomerMediCaseTable($args) {
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs < 1) throw new FuncExpEvalException('getCustomerMediCaseTable takes at least one parameter which is the customer number', FuncExpEvalException::INVALID_ARGUMENT);
		$customerNumber = $this->evaluateArg($args[0]);
		if(!($customerNumber instanceof LogExp)) $customerNumber = lxEq(fs('contactNumber'),$customerNumber);
		// loads the customers and extracts the medical cases
		return sel($this->getPrincipal(),elementPList(lxInGR($this->evaluateFuncExp(fx('companyDataConfig','customerGroupLx'))),lf(null,$customerNumber)),dfasl(
			dfas("ElementMatrixDFA",
				"setColumns",array('caseNumber_','caseLaw_','caseDate_','caseTariff_'),
				"setFromRow","1",
				"setCalculatedColumns",array('contactNumber'=>fs('contactNumber'))
			),
			dfas("CallbackDFA","setProcessDataChunkCallback",function($data,$callbackDFA){
				// keeps only rows with a case number
				if(oVal($data->{'caseNumber_'})) {
					$callbackDFA->writeResultToOutput((object)array(
						'contactNumber'=>$data->contactNumber,
						'caseNumber'=>oVal($data->{'caseNumber_'}),
						'caseLaw'=>oVal($data->{'caseLaw_'}),
						'caseDate'=>oVal($data->{'caseDate_'}),
						'caseTariff'=>oVal($data->{'caseTariff_'}),
					));
				}
			}),
			dfas("ArrayBufferDFA")
		));
	}
		
	// Wigii Medidata General Invoice Request 4.5
	
	/**
	 * Generates a Medidata General Invoice Request 4.5 using a Wigii Company CustomerOrder element.<br/>
	 * FuncExp signature : <code>genMedidataInvoiceRequest45(customerOrder,options)</code><br/>
	 * Where arguments are :
	 * - Arg(0) customerOrder: Element of module CustomerOrders|ElementPDataFlowConnector. Given customer order for which to generate the Medidata General Invoice Request as XML.
	 * - Arg(1) options: WigiiBPLParameter. An optional bag of options to configure the generation process.
	 * @return SimpleXMLElement a SimpleXMLElement compatible with XML schema http://www.forum-datenaustausch.ch/invoice generalInvoiceRequest_450.xsd
	 */
	public function genMedidataInvoiceRequest45($args) {	    
		$this->debugLogger()->logBeginOperation('genMedidataInvoiceRequest45');
		$nArgs = $this->getNumberOfArgs($args);
		if($nArgs < 1) throw new FuncExpEvalException('genMedidataInvoiceRequest45 takes at least one parameter which should evaluate to an Element of type Wigii Company CustomerOrders', FuncExpEvalException::INVALID_ARGUMENT);
		$customerOrder = $this->evaluateArg($args[0]);		
		// fetches element if needed
		if($customerOrder instanceof ElementPDataFlowConnector) $customerOrder = sel($this->getPrincipal(),$customerOrder,dfasl(dfas("NullDFA")));			
		if($customerOrder instanceof ElementP) $customerOrder = $customerOrder->getElement();
		if(!($customerOrder instanceof Element)) throw new FuncExpEvalException('genMedidataInvoiceRequest45 takes at least one parameter which should evaluate to an Element of type Wigii Company CustomerOrders', FuncExpEvalException::INVALID_ARGUMENT);
		// reads bag of options 
		if($nArgs>1) {
			$options = $this->evaluateArg($args[1]);
			if(!isset($options)) $options = wigiiBPLParam();
			elseif(!($options instanceof WigiiBPLParameter)) throw new FuncExpEvalException('options can only be an instance of WigiiBPLParameter',FuncExpEvalException::INVALID_ARGUMENT);
		}
		else $options = wigiiBPLParam();
		$this->initializeInvoice45Options($customerOrder, $options);
		
		// generates invoice request
		$returnValue = $this->getXmlDoc($options);		
		$this->createInvoice45Request($returnValue, $customerOrder, $options);
		// validates generated xml
		$this->assertXmlIsValid($returnValue, $options->getValue('xmlSchema'));
		// returns as SimpleXmlElement
		$returnValue = simplexml_import_dom($returnValue);
		$this->debugLogger()->logEndOperation('genMedidataInvoiceRequest45');
		return $returnValue;
	}
	
	/**
	 * Generates a Medidata invoice reminder request using a generated Medidata xml invoice file stored into a Wigii Company CustomerOrder element.<br/>
	 * FuncExp signature : <code>genMedidataInvoiceReminder45(customerOrder,medidataInvoiceXml,reminderLevel,options)</code><br/>
	 * Where arguments are :
	 * - Arg(0) customerOrder: Element of module CustomerOrders|ElementPDataFlowConnector. Given customer order to which is attached an XML Medidata General Invoice Request.
	 * - Arg(1) medidataInvoiceXml: FieldSelector. Field of type File containing the generated Medidata invoice XML file
	 * - Arg(2) reminderLevel: int. One of 1, 2 or 3.
	 * - Arg(2) options: WigiiBPLParameter. An optional bag of options to configure the generation process.
	 * @return SimpleXMLElement a SimpleXMLElement for an invoice reminder request compatible with XML schema http://www.forum-datenaustausch.ch/invoice generalInvoiceRequest_450.xsd
	 */
	public function genMedidataInvoiceReminder45($args) {
	    $this->debugLogger()->logBeginOperation('genMedidataInvoiceReminder45');
	    $nArgs = $this->getNumberOfArgs($args);
	    if($nArgs < 3) throw new FuncExpEvalException('genMedidataInvoiceReminder45 takes at least three parameters which first one should evaluate to an Element of type Wigii Company CustomerOrders, second be a FieldSelector pointing to a field of type Files containing a Medidata generated XML invoice file, and third parameter the reminder level 1,2 or 3', FuncExpEvalException::INVALID_ARGUMENT);
	    $customerOrder = $this->evaluateArg($args[0]);
	    // fetches element if needed
	    if($customerOrder instanceof ElementPDataFlowConnector) $customerOrder = sel($this->getPrincipal(),$customerOrder,dfasl(dfas("NullDFA")));
	    if($customerOrder instanceof ElementP) $customerOrder = $customerOrder->getElement();
	    if(!($customerOrder instanceof Element)) throw new FuncExpEvalException('genMedidataInvoiceReminder45 takes a first parameter which should evaluate to an Element of type Wigii Company CustomerOrders', FuncExpEvalException::INVALID_ARGUMENT);
	    // gets medidata XML invoice
	    if($args[1] instanceof FieldSelector) $xmlFieldName = $args[1]->getFieldName();
	    else {
	        $xmlFieldName = $this->evaluateArg($args[1]);
	        if($xmlFieldName instanceof FieldSelector) $xmlFieldName = $xmlFieldName->getFieldName();
	    }
	    $xmlField = $customerOrder->getFieldList()->getField($xmlFieldName);
	    if(!($xmlField->getDataType() instanceof Files)) throw new FuncExpEvalException('genMedidataInvoiceReminder45 takes second parameter which should be a FieldSelector pointing to a field of type Files containing a Medidata generated XML invoice file', FuncExpEvalException::INVALID_ARGUMENT);
	    // gets reminder level
	    $reminderLevel = $this->evaluateArg($args[2]);
	    if(!(1<=$reminderLevel && $reminderLevel<=3)) throw new FuncExpEvalException('reminder level should be 1, 2 or 3', FuncExpEvalException::INVALID_ARGUMENT);
	    
	    // reads bag of options
	    if($nArgs>3) {
	        $options = $this->evaluateArg($args[3]);
	        if(!isset($options)) $options = wigiiBPLParam();
	        elseif(!($options instanceof WigiiBPLParameter)) throw new FuncExpEvalException('options can only be an instance of WigiiBPLParameter',FuncExpEvalException::INVALID_ARGUMENT);
	    }
	    else $options = wigiiBPLParam();
	    
	    // initializes options
	    $options->setValue('xmlSchema','generalInvoiceRequest_450.xsd');
	    $options->setValue('namespaceURI','http://www.forum-datenaustausch.ch/invoice');
	    $options->setValue('namespacePrefix','invoice');
	    
	    // loads invoice request as an xml document
	    $returnValue = sel($this->getPrincipal(),elementFile2df($customerOrder, $xmlFieldName),dfasl(dfas("StringBufferDFA")));
	    $returnValue = $this->loadXmlDoc($returnValue);	    	   
	    // validates loaded xml
	    $this->assertXmlIsValid($returnValue, $options->getValue('xmlSchema'));
	    // goes to request
	    $xml = $this->createXmlElement($returnValue, 'request', $options);
	    // goes to payload
	    $xml = $this->createXmlElement($xml, 'payload', $options);
	    // checks that payload type is invoice
	    if($xml->getAttribute('type') != 'invoice') throw new WigiiMedidataException('a reminder can only be created from an invoice and not from a payload of type "'.$xml->getAttribute('type').'"',WigiiMedidataException::XML_VALIDATION_ERROR);
	    // sets payload type as reminder
	    $xml->setAttribute('type','reminder');
	    // inserts a payload/reminder node, before payload/body node 
	    $xml = $this->insertXmlElement($xml, 'reminder', $this->createXmlElement($xml, 'body', $options), $options);
	    $xml->setAttribute('request_timestamp', strtotime($this->assertNotNull($customerOrder, 'reminder'.$reminderLevel)));
	    $xml->setAttribute('request_date', $this->assertDateNotNull($customerOrder, 'reminder'.$reminderLevel));
	    $xml->setAttribute('request_id', 'R'.$reminderLevel.'-'.$this->assertNotNull($customerOrder,'customerOrderNumber'));	    
	    $xml->setAttribute('reminder_level', $reminderLevel);
	    $reminderText = '';
	    switch($reminderLevel) {
	        case 1: $reminderText .= 'Rappel'; break;
	        case 2: $reminderText .= 'Deuxième rappel'; break;
	        case 3: $reminderText .= 'Troisième rappel'; break;
	    }
	    $reminderText.= ' sur la facture n°'.$this->assertNotNull($customerOrder,'customerOrderNumber');
	    $reminderText.= ' du '.Dates::formatDisplay($this->assertNotNull($customerOrder,'orderDate'),"dd.mm.yyyy",null);
	    $reminderText.= ' échue le '.Dates::formatDisplay($this->assertNotNull($customerOrder,'paiementDueDate'),"dd.mm.yyyy",null);
	    $xml->setAttribute('reminder_text', $reminderText);
	    
	    // validates generated xml
	    $this->assertXmlIsValid($returnValue, $options->getValue('xmlSchema'));
	    // returns as SimpleXmlElement
	    $returnValue = simplexml_import_dom($returnValue);
	    $this->debugLogger()->logEndOperation('genMedidataInvoiceReminder45');
	    return $returnValue;	    
	}
	
	/**
	 * Generates a Medidata SendControl file using a generated Medidata xml file stored into a Wigii Company CustomerOrder element.<br/>
	 * FuncExp signature : <code>genMedidataSendControl(customerOrder,medidataXml,options)</code><br/>
	 * Where arguments are :
	 * - Arg(0) customerOrder: Element of module CustomerOrders|ElementPDataFlowConnector. Given customer order to which is attached an XML Medidata General Invoice Request.
	 * - Arg(1) medidataXml: FieldSelector. Field of type File containing the generated Medidata XML file
	 * - Arg(2) options: WigiiBPLParameter. An optional bag of options to configure the generation process.
	 * @return SimpleXMLElement a SimpleXMLElement compatible with XML schema http://www.medidata.ch/mpc/XSD SendControl10.xsd
	 */
	public function genMedidataSendControl($args) {
	    $this->debugLogger()->logBeginOperation('genMedidataSendControl');
	    $nArgs = $this->getNumberOfArgs($args);
	    if($nArgs < 2) throw new FuncExpEvalException('genMedidataSendControl takes at least two parameters which first one should evaluate to an Element of type Wigii Company CustomerOrders, and second be a FieldSelector pointing to a field of type Files containing a Medidata generated XML file', FuncExpEvalException::INVALID_ARGUMENT);
	    $customerOrder = $this->evaluateArg($args[0]);
	    // fetches element if needed
	    if($customerOrder instanceof ElementPDataFlowConnector) $customerOrder = sel($this->getPrincipal(),$customerOrder,dfasl(dfas("NullDFA")));
	    if($customerOrder instanceof ElementP) $customerOrder = $customerOrder->getElement();
	    if(!($customerOrder instanceof Element)) throw new FuncExpEvalException('genMedidataSendControl takes a first parameter which should evaluate to an Element of type Wigii Company CustomerOrders', FuncExpEvalException::INVALID_ARGUMENT);
	    // gets medidata XML invoice
	    if($args[1] instanceof FieldSelector) $xmlFieldName = $args[1]->getFieldName();
	    else {
	        $xmlFieldName = $this->evaluateArg($args[1]);
	        if($xmlFieldName instanceof FieldSelector) $xmlFieldName = $xmlFieldName->getFieldName();
	    }
	    $xmlField = $customerOrder->getFieldList()->getField($xmlFieldName);
	    if(!($xmlField->getDataType() instanceof Files)) throw new FuncExpEvalException('genMedidataSendControl takes second parameter which should be a FieldSelector pointing to a field of type Files containing a Medidata generated XML file', FuncExpEvalException::INVALID_ARGUMENT);
	    
	    // reads bag of options
	    if($nArgs>2) {
	        $options = $this->evaluateArg($args[2]);
	        if(!isset($options)) $options = wigiiBPLParam();
	        elseif(!($options instanceof WigiiBPLParameter)) throw new FuncExpEvalException('options can only be an instance of WigiiBPLParameter',FuncExpEvalException::INVALID_ARGUMENT);
	    }
	    else $options = wigiiBPLParam();
	    
	    // initializes options
	    if($options->getValue("DistType")===null) $options->setValue("DistType",0); // 0=courrier B, 1=courrier A.
	    if($options->getValue("DocPrinted")===null) $options->setValue("DocPrinted","true"); // true=doc already printed, Medidta does not print it; false=doc not printed, Medidata should print and send a paper copy. 
	    $options->setValue('xmlSchema','SendControl10.xsd');
	    $options->setValue('namespaceURI','http://www.medidata.ch/mpc/XSD');
	    $options->setValue('namespacePrefix','mpc');
	    
	    // generates send control file
	    $returnValue = $this->getXmlDoc($options);	   
	    $xml = $this->createXmlElement($returnValue, 'DocumentsToSend', $options);
	    $this->initializeXmlRootElement($xml, $options);
	    
	    $xml = $this->createXmlElement($xml, 'Document', $options);	    
	    $xml->setAttribute('DistType', $options->getValue("DistType"));
	    $xml->setAttribute('DocAttr', 'Tiers_Payant');
	    $xml->setAttribute('DocPrinted', $options->getValue("DocPrinted"));
	    $xml->setAttribute('DocSize', $this->assertNotNull($customerOrder, $xmlFieldName,'size'));
	    $xml->setAttribute('FileName', $this->assertNotNull($customerOrder, $xmlFieldName,'name').$this->assertNotNull($customerOrder, $xmlFieldName,'type'));
	    $xml->setAttribute('PrintLanguage', 'F');
	    $xml->setAttribute('SenderDocId', $this->assertNotNull($customerOrder, $xmlFieldName,'name'));
	    if($customerOrder->getFieldValue('orderStatus')=='reminderSent') $xml->setAttribute('Subject', "Rappel sur facture no ".$this->assertNotNull($customerOrder, "customerOrderNumber"));
	    else $xml->setAttribute('Subject', "Facture no ".$this->assertNotNull($customerOrder, "customerOrderNumber"));
	    
	    // validates generated xml
	    $this->assertXmlIsValid($returnValue, $options->getValue('xmlSchema'));
	    // returns as SimpleXmlElement
	    $returnValue = simplexml_import_dom($returnValue);
	    $this->debugLogger()->logEndOperation('genMedidataSendControl');
	    return $returnValue;
	}
	
	/**
	 * Sends a generated invoice request to Medidata.
	 * The Medidata xml invoice and send control file are put into the folder CLIENT_DATA_PATH/Medidata/send and are ready to be sent to MediportCommunicator by the WigiiMedidataFileWatcher batch 
	 * FuncExp signature : <code>sendMedidataInvoiceRequest(customerOrder,medidataXml,medidataSendControl,options)</code><br/>
	 * Where arguments are :
	 * - Arg(0) customerOrder: Element of module CustomerOrders|ElementPDataFlowConnector. Given customer order to which is attached an XML Medidata General Invoice Request.
	 * - Arg(1) medidataXml: FieldSelector. Field of type File containing the generated Medidata XML file
	 * - Arg(2) medidataSendControl: FieldSelector. Field of type File containing the generated Medidata send control XML file
	 * - Arg(3) options: WigiiBPLParameter. An optional bag of options to configure the sending process.
	 * @return Boolean true if files are correctly sent, else throws an exception
	 */
	public function sendMedidataInvoiceRequest($args) {
	    $this->assertFxOriginIsNotPublic();
	    $this->debugLogger()->logBeginOperation('sendMedidataInvoiceRequest');
	    $nArgs = $this->getNumberOfArgs($args);
	    $returnValue=false;
	    if($nArgs < 3) throw new FuncExpEvalException('sendMedidataInvoiceRequest takes at least three parameters which first one should evaluate to an Element of type Wigii Company CustomerOrders, and the two others are FieldSelectors pointing to fields of type Files containing a Medidata generated XML files (invoice and send control)', FuncExpEvalException::INVALID_ARGUMENT);
	    $customerOrder = $this->evaluateArg($args[0]);
	    // fetches element if needed
	    if($customerOrder instanceof ElementPDataFlowConnector) $customerOrder = sel($this->getPrincipal(),$customerOrder,dfasl(dfas("NullDFA")));
	    if($customerOrder instanceof ElementP) $customerOrder = $customerOrder->getElement();
	    if(!($customerOrder instanceof Element)) throw new FuncExpEvalException('sendMedidataInvoiceRequest takes a first parameter which should evaluate to an Element of type Wigii Company CustomerOrders', FuncExpEvalException::INVALID_ARGUMENT);
	    // gets medidata XML invoice
	    if($args[1] instanceof FieldSelector) $invoiceFieldName = $args[1]->getFieldName();
	    else {
	        $invoiceFieldName = $this->evaluateArg($args[1]);
	        if($invoiceFieldName instanceof FieldSelector) $invoiceFieldName = $invoiceFieldName->getFieldName();
	    }
	    $invoiceField = $customerOrder->getFieldList()->getField($invoiceFieldName);
	    if(!($invoiceField->getDataType() instanceof Files)) throw new FuncExpEvalException('sendMedidataInvoiceRequest takes second parameter which should be a FieldSelector pointing to a field of type Files containing a Medidata generated invoice XML file', FuncExpEvalException::INVALID_ARGUMENT);
	    
	    // gets medidata XML send control
	    if($args[2] instanceof FieldSelector) $sendControlFieldName = $args[2]->getFieldName();
	    else {
	        $sendControlFieldName = $this->evaluateArg($args[2]);
	        if($sendControlFieldName instanceof FieldSelector) $sendControlFieldName = $sendControlFieldName->getFieldName();
	    }
	    $sendControlField = $customerOrder->getFieldList()->getField($sendControlFieldName);
	    if(!($sendControlField->getDataType() instanceof Files)) throw new FuncExpEvalException('sendMedidataInvoiceRequest takes third parameter which should be a FieldSelector pointing to a field of type Files containing a Medidata generated send control XML file', FuncExpEvalException::INVALID_ARGUMENT);
	    	    
	    // Dumps the xml invoice to CLIENT_DATA_PATH/Medidata/send directory
	    sel($this->getPrincipal(),elementFile2df($customerOrder, $invoiceFieldName),dfasl(
	        dfas("FileOutputStreamDFA","setRootFolder",CLIENT_DATA_PATH,
	           "setFileName","Medidata/send/".$customerOrder->getFieldValue($invoiceFieldName,"name").$customerOrder->getFieldValue($invoiceFieldName,"type")
	        )
	    ));
	    
	    // Dumps the xml send control file to CLIENT_DATA_PATH/Medidata/send directory
	    sel($this->getPrincipal(),elementFile2df($customerOrder, $sendControlFieldName),dfasl(
	        dfas("FileOutputStreamDFA","setRootFolder",CLIENT_DATA_PATH,
	            "setFileName","Medidata/send/".$customerOrder->getFieldValue($sendControlFieldName,"name").$customerOrder->getFieldValue($sendControlFieldName,"type")
	            )
	        ));
	    
	    $this->debugLogger()->logEndOperation('sendMedidataInvoiceRequest');
	    return $returnValue;
	}
	
	/**
	 * Prints a received Medidata invoice response and extracts any attached documents.
	 * FuncExp signature : <code>printMedidataInvoiceResponse(element, medidataResponseXml, options)</code><br/>
	 * Where arguments are :
	 * - Arg(0) element: Element of module Filemanager. Given element to which is attached a Medidata received XML Invoice Response.
	 * - Arg(1) medidataResponseXml: FieldSelector. Field of type File containing the received Medidata XML file
	 * - Arg(2) options: WigiiBPLParameter. An optional bag of options to configure the extraction process. It supports the following options:
	 * - extractAttachementsTo: String. Field name prefix, which selects a set of fields of type Files in which to extract all attached documents. The set of fields should respect the Wigii Matrix naming convention and be numbered 1, 2, 3, ..., n.
	 * - printTemplate: String. Name of the print template to use to print the Medidata Invoice Response as HTML. Defaults to medidataInvoiceResponse.
	 * To activate the print template in the configuration you should:
	 * 1. copy the MedidataPrintInvoiceResponse.php file delivered in the Wigii Medidata addon to the client configuration folder and customize it for the client namespace
	 * 2. add the print activity with the template medidataInvoiceResponse in the Wigii Filemanager module
	 * <Print>
     *	<medidataInvoiceResponse path="clientNamespace_MedidataPrintInvoiceResponse.php" inClientFolder="1" />
	 * </Print>  
	 * @return Array an array with the html content of the printed invoice response and all the useful Files subfields. The FuncExp return value is compatible with a calculated field of type Files.
 	 * @example In a Wigii Filemanager module, add the following field to store the Medidata Invoice Response as a pretty HTML document and extract any attachements to a set of fields of type Files named mdtDoc1, mdtDoc2, etc. 
	 * <mdtInvoiceResponse type="Files" readonly="1" clearOnCopy="1" enableForDownloading="1" funcExp='printMedidataInvoiceResponse(medidataXml,wigiiBPLParam("extractAttachementsTo","mdtDoc"))'><label_l01>Medidata response</label_l01><label_l02>Réponse Medidata</label_l02></mdtInvoiceResponse>
	 */
	public function printMedidataInvoiceResponse($args) {
	    $this->assertFxOriginIsNotPublic();
	    $this->debugLogger()->logBeginOperation('printMedidataInvoiceResponse');
	    $nArgs = $this->getNumberOfArgs($args);
	    $returnValue=null;
	    if($nArgs < 2) throw new FuncExpEvalException('printMedidataInvoiceResponse takes at least two parameters which first one evaluates to current element and second is FieldSelector pointing to a field of type Files containing a Medidata received XML file (invoice response)', FuncExpEvalException::INVALID_ARGUMENT);
	    // gets current element
	    $element = $this->evaluateArg($args[0]);
	    if($element instanceof ElementP) $element = $element->getElement();
	    if(!($element instanceof Element)) throw new FuncExpEvalException('printMedidataInvoiceResponse takes a first parameter which should evaluate to an Element', FuncExpEvalException::INVALID_ARGUMENT);
	    $fieldList = $element->getFieldList();
	    // gets Medidata XML response
	    if($args[1] instanceof FieldSelector) $xmlResponseFieldName = $args[1]->getFieldName();
	    else {
	        $xmlResponseFieldName = $this->evaluateArg($args[1]);
	        if($xmlResponseFieldName instanceof FieldSelector) $xmlResponseFieldName = $xmlResponseFieldName->getFieldName();
	    }
	    $xmlResponseField = $fieldList->getField($xmlResponseFieldName);
	    if(!($xmlResponseField->getDataType() instanceof Files)) throw new FuncExpEvalException('printMedidataInvoiceResponse takes second parameter which should be a FieldSelector pointing to a field of type Files containing a Medidata received XML file (invoice response)', FuncExpEvalException::INVALID_ARGUMENT);
	    
	    // reads bag of options
	    if($nArgs>2) {
	        $options = $this->evaluateArg($args[2]);
	        if(!isset($options)) $options = wigiiBPLParam();
	        elseif(!($options instanceof WigiiBPLParameter)) throw new FuncExpEvalException('options can only be an instance of WigiiBPLParameter',FuncExpEvalException::INVALID_ARGUMENT);
	    }
	    else $options = wigiiBPLParam();
	    
	    $printTemplate = $options->getValue('printTemplate');
	    if(empty($printTemplate)) $printTemplate = 'medidataInvoiceResponse';
	    
	    $attachementFieldName = $options->getValue('extractAttachementsTo');
	    $extractAttachements = !empty($attachementFieldName);  
	    
	    // extracts Medidata XML
	    $options->setValue('xmlSchema','generalInvoiceResponse_450.xsd');
	    $options->setValue('namespaceURI','http://www.forum-datenaustausch.ch/invoice');
	    $options->setValue('namespacePrefix','invoice');
	    $medidataXml = sel($this->getPrincipal(),elementFile2df($element, $xmlResponseFieldName),dfasl(dfas("StringBufferDFA")));
	    $medidataXml = $this->loadXmlDoc($medidataXml);
	    // validates loaded xml
	    $this->assertXmlIsValid($medidataXml, $options->getValue('xmlSchema'));
        // transforms it as SimpleXml
        $medidataXml = simplexml_import_dom($medidataXml);
        
	    // extracts type of response (invoice or reminder)
	    $responseType = (string)($medidataXml->xpath("/invoice:response/invoice:payload")[0]["type"]);
	    // extracts message date (Wigii format)
	    $messageDate = date('Y-m-d H:m:i', intval((string)($medidataXml->xpath("/invoice:response/invoice:payload")[0]["response_timestamp"])));	    
	    // extracts customer order number
	    $customerOrderNumber = (string)($medidataXml->xpath("/invoice:response/invoice:payload/invoice:invoice")[0]["request_id"]);
	    // extracts type of message (pending, accepted, rejected)
	    $messageXml = $medidataXml->xpath("/invoice:response/invoice:payload/invoice:body/invoice:pending");
	    if(!$messageXml) $messageXml = $medidataXml->xpath("/invoice:response/invoice:payload/invoice:body/invoice:accepted");
	    if(!$messageXml) $messageXml = $medidataXml->xpath("/invoice:response/invoice:payload/invoice:body/invoice:rejected");
	    $messageXml = $messageXml[0];
	    $messageType=$messageXml->getName();
	    // extracts attachements
	    if($extractAttachements) {
	        $messageAttachements = $medidataXml->xpath("/invoice:response/invoice:payload/invoice:body/invoice:documents/invoice:document");
	        if($messageAttachements) $nbAttachements = count($messageAttachements);
	        else $nbAttachements = 0;
	    }
	    // builds message title    
	    $messageTitle = Dates::formatDisplay($messageDate, "yyyy.mm.dd")." Réponse ".$messageType." à ".$responseType." no ".$customerOrderNumber;
	    
	    // intializes options for print
	    $options->setValue('medidataXml',$medidataXml);
	    $options->setValue('customerOrderNumber',$customerOrderNumber);
	    $options->setValue('responseType',$responseType);
	    $options->setValue('messageDate',$messageDate);
	    $options->setValue('messageXml',$messageXml);
	    $options->setValue('messageType',$messageType);
	    $options->setValue('nbAttachements',$nbAttachements);
	    if($nbAttachements>0) $options->setValue('messageAttachements',$messageAttachements);	    
	    
	    // prints invoice response as html
	    $htmlContent = $this->evaluateFuncExp(fx('printElementWithTemplate',$element,$printTemplate,$options));
	    
	    // generates files subfields
	    $returnValue = $this->evaluateFuncExp(fx('buildHtmlFileSubFieldsMap',$messageTitle,$htmlContent,$messageDate));
	    
	    // extracts attachements
	    if($extractAttachements && $nbAttachements>0) {
	        // if element is new, then first persists it to be able to save the attached files
	        if($element->isNew()) {
	           ServiceProvider::getWigiiBPL()->elementInsert($this->getPrincipal(), $this, wigiiBPLParam(
                    'element', $element,
                    'groupId', $this->evaluateFuncExp(fx('cfgCurrentGroup','id'))
                ));	            
	        }
	        $i=1; 
	        foreach($messageAttachements as $attachement) {
	            // extract attachement and stores it into field no i
	            $this->extractInvoiceResponse45Document($element, $attachementFieldName.$i, $attachement, $options);
	            $i++;
	        }
	    }
	    
	    $this->debugLogger()->logEndOperation('printMedidataInvoiceResponse');
	    return $returnValue;
	}
	
	/**
	 * Extracts a Medidata Invoice Response attached document and saves it to a field of type files of a given element 
	 * @param Element $element an element to which save the attached document
	 * @param String $fieldName field of type Files to which attach the document
	 * @param SimpleXMLElement $documentXml generalInvoiceResponse_450 documentType XML node holding the attached document
	 * @param WigiiBPLParameter $options optional bag of options to configure the extraction process
	 */
	private function extractInvoiceResponse45Document($element,$fieldName,$documentXml,$options) {
	    $this->debugLogger()->logBeginOperation('extractInvoiceResponse45Document');
	    $content = $documentXml->xpath("invoice:base64");
	    if($content) $content = base64_decode((string)$content[0]);
	    if($content) {
	        $fileName = (string)$documentXml['filename'];
	        if($fileName) {
	            $ext = explode(".", $fileName);
	            if(count($ext) > 1){
	                $ext = end($ext);
	                $ext = ".".$ext;
	            } else $ext = "";
	            $fileName = basename($fileName,$ext);
	        }
	        else $fileName=null;
	        $mimeType = (string)$documentXml['mimeType'];
	        if(!$mimeType) $mimeType=null;
	        
    	    // in generalInvoiceResponse_450 schema, typeMime is restricted to application/pdf|image/jpeg|image/png, therefore it is safe.
    	    ServiceProvider::getDataFlowService()->processString($this->getPrincipal(), $content, dfasl(
    	        dfas('ElementFileOutputStreamDFA','setElement',$element,'setFieldName',$fieldName,
    	            'setPersistElement', true,
    	            'setFileSubfields', array("name"=>$fileName,"type"=>$ext,"mime"=>$mimeType)
    	            )
    	        ));
	    }
	    $this->debugLogger()->logEndOperation('extractInvoiceResponse45Document');	  
	}
	
	/**
	 * Initializes the bag of options to generate invoice request 4.5
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options the ag of options configuring the generation process
	 * @return WigiiBPLParameter the bag of options initialized
	 */
	protected function initializeInvoice45Options($customerOrder,$options) {
		$options->setValue('xmlSchema','generalInvoiceRequest_450.xsd');
		$options->setValue('namespaceURI','http://www.forum-datenaustausch.ch/invoice');
		$options->setValue('namespacePrefix','invoice');
		// loads attached legal entity if not defined
		if(is_null($options->getValue('legalEntity'))) $options->setValue('legalEntity', $this->evaluateFuncExp(fx('getLegalEntityAttr',$customerOrder->getFieldValue('legalEntity'),fs_e('this'))));
		// loads attached customer if not defined
		if(is_null($options->getValue('customer'))) $options->setValue('customer', $this->evaluateFuncExp(fx('getContactAttr',$customerOrder->getFieldValue('customer'),fs_e('this'))));
		// loads attached invoice to if not defined
		if(is_null($options->getValue('invoiceTo'))) {
			if($customerOrder->getFieldValue('customer') == $customerOrder->getFieldValue('invoiceTo')) $options->setValue('invoiceTo',$options->getValue('customer'));
			else $options->setValue('invoiceTo', $this->evaluateFuncExp(fx('getContactAttr',$customerOrder->getFieldValue('invoiceTo'),fs_e('this'))));
		}		
		return $options;
	}
	
	/**
	 * Creates an invoice request
	 * @param DOMDocument $xmlDoc XML document in which the invoice request will be created. 
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice request node
	 */
	protected function createInvoice45Request($xmlDoc, $customerOrder, $options) {
		$returnValue = $this->createXmlElement($xmlDoc, 'request', $options);
		$this->initializeXmlRootElement($returnValue, $options);
		$this->declareXmlNamespace($returnValue, 'xenc', 'http://www.w3.org/2001/04/xmlenc#');
		$this->declareXmlNamespace($returnValue, 'ds', 'http://www.w3.org/2000/09/xmldsig#');
		// invoice request attributes
		$returnValue->setAttribute('language','fr');		
		/* CWE 2019.02.19: not yet authorized to be put into production */$returnValue->setAttribute('modus','test');
		//$returnValue->setAttribute('modus','production');
		$returnValue->setAttribute('validation_status','0');
		// invoice processing
		$this->createInvoice45Processing($returnValue, $customerOrder, $options);
		// invoice payload
		$this->createInvoice45Payload($returnValue, $customerOrder, $options);
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request processing node
	 * @param DOMElement $invoiceRequest current invoice request node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice processing node
	 */
	protected function createInvoice45Processing($invoiceRequest,$customerOrder,$options) {
		$legalEntity = $options->getValue('legalEntity');
		$insurance = $options->getValue('invoiceTo');
		$returnValue = $this->createXmlElement($invoiceRequest, 'processing', $options);
		// transport
		$xml = $this->createXmlElement($returnValue, 'transport', $options);
		$xml->setAttribute('from', $this->assertNoSepNotNull($legalEntity,'noGLN'));
		$xml->setAttribute('to', $this->assertNoSepNotNull($insurance,'noGLN'));
		// via Medidata
		$xml = $this->createXmlElement($xml, 'via', $options);
		$xml->setAttribute('via', '7601001304307');
		$xml->setAttribute('sequence_id', '1');
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request payload node
	 * @param DOMElement $invoiceRequest current invoice request node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice payload node
	 */
	protected function createInvoice45Payload($invoiceRequest,$customerOrder,$options) {
		$returnValue = $this->createXmlElement($invoiceRequest, 'payload', $options);
		$returnValue->setAttribute('type', 'invoice');
		$returnValue->setAttribute('copy', 0);
		$returnValue->setAttribute('storno', 0);
		// invoice
		$xml = $this->createXmlElement($returnValue, 'invoice', $options);		
		$xml->setAttribute('request_timestamp', strtotime($this->assertNotNull($customerOrder, 'orderDate')));
		$xml->setAttribute('request_date', $this->assertDateNotNull($customerOrder, 'orderDate'));
		$xml->setAttribute('request_id', $this->assertNotNull($customerOrder,'customerOrderNumber'));
		// body
		$this->createInvoice45Body($returnValue, $customerOrder, $options);
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request body node
	 * @param DOMElement $invoicePayload current invoice payload node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice body node
	 */
	protected function createInvoice45Body($invoicePayload,$customerOrder,$options) {
		$returnValue = $this->createXmlElement($invoicePayload, 'body', $options);
		$returnValue->setAttribute('role_title', 'Orthopédie');
		$returnValue->setAttribute('role', 'other');
		$returnValue->setAttribute('place', 'company');
		// prolog / generator
		$xml = $this->createXmlElement($returnValue, 'prolog', $options);
		$xml = $this->createXmlElement($xml, 'generator', $options);
		$xml->setAttribute('name', 'WigiiMedidataFL');
		$xml->setAttribute('copyright', 'Wigii.org 2019');
		$xml->setAttribute('version', '450');
		// remark
		$remark = substr(trim($this->evaluateFuncExp(fx('html2text',$customerOrder->getFieldValue('annexInfo')))),0,350);
		if(!empty($remark)) $xml = $this->createXmlElement($returnValue, 'remark', $options, $remark);
		// tiers payant
		$this->createInvoice45TiersPayant($returnValue, $customerOrder, $options);
		// esr QR
		$this->createInvoice45esrQR($returnValue, $customerOrder, $options);
		// case management
		switch($customerOrder->getFieldValue('caseLaw')){
			case 'LAI':
				// ivg
				$this->createInvoice45ivg($returnValue, $customerOrder, $options);
				break;
			case 'LAA':
				// uvg
				$this->createInvoice45uvg($returnValue, $customerOrder, $options);
				break;
			case 'LAMAL':
			    // kvg
			    $this->createInvoice45kvg($returnValue, $customerOrder, $options);
			    break;
			// default node is not created.
		}
		// treatment
		$this->createInvoice45Treatment($returnValue, $customerOrder, $options);
		// services
		$this->createInvoice45Services($returnValue, $customerOrder, $options);
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request Tiers payant node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice tiers payant node
	 */
	protected function createInvoice45TiersPayant($invoiceBody,$customerOrder,$options) {
		$legalEntity = $options->getValue('legalEntity');
		$insurance = $options->getValue('invoiceTo');
		$patient = $options->getValue('customer');
		$returnValue = $this->createXmlElement($invoiceBody, 'tiers_payant', $options);
		$returnValue->setAttribute('payment_period', 'P60D');
		// biller
		$xml = $this->createXmlElement($returnValue, 'biller', $options);
		$xml->setAttribute('ean_party', $this->assertNoSepNotNull($legalEntity,'noGLN'));
		$xml->setAttribute('zsr', $this->assertNoSepNotNull($legalEntity,'noRCC'));
		$xml->setAttribute('specialty', 'Orthopédie');
		$xml->setAttribute('uid_number', $this->assertNoSepNotNull($legalEntity, 'IDE'));
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, $this->assertNotNull($legalEntity, 'entityName'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($legalEntity, 'entityAddress','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($legalEntity, 'entityAddress','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($legalEntity, 'entityAddress','city'));
		// debitor
		$xml = $this->createXmlElement($returnValue, 'debitor', $options);
		$xml->setAttribute('ean_party', $this->assertNoSepNotNull($insurance,'noGLN'));
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, $this->assertNotNull($insurance, 'company'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($insurance, 'address','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($insurance, 'address','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($insurance, 'address','city'));
		// provider
		$xml = $this->createXmlElement($returnValue, 'provider', $options);
		$xml->setAttribute('ean_party', $this->assertNoSepNotNull($legalEntity,'noGLN'));
		$xml->setAttribute('zsr', $this->assertNoSepNotNull($legalEntity,'noRCC'));
		$xml->setAttribute('specialty', 'Orthopédie');
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, $this->assertNotNull($legalEntity, 'entityName'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($legalEntity, 'entityAddress','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($legalEntity, 'entityAddress','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($legalEntity, 'entityAddress','city'));
		// insurance
		$xml = $this->createXmlElement($returnValue, 'insurance', $options);
		$xml->setAttribute('ean_party', $this->assertNoSepNotNull($insurance,'noGLN'));
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, $this->assertNotNull($insurance, 'company'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($insurance, 'address','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($insurance, 'address','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($insurance, 'address','city'));
		// patient
		$xml = $this->createXmlElement($returnValue, 'patient', $options);
		$xml->setAttribute('gender', ($this->assertNotNull($patient, 'title')=='mr'?'male':'female'));
		$xml->setAttribute('birthdate', $this->assertDateNotNull($patient, 'dateOfBirth'));
		$xml->setAttribute('ssn', $this->assertNoSepNotNull($patient, 'noAVS'));
		$xml = $this->createXmlElement($xml, 'person', $options);
		$xml->setAttribute('salutation', $this->formatValue($patient, 'title'));
		$this->createXmlElement($xml, 'familyname', $options, $this->assertNotNull($patient, 'last_name'));
		$this->createXmlElement($xml, 'givenname', $options, $this->assertNotNull($patient, 'first_name'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($patient, 'address','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($patient, 'address','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($patient, 'address','city'));
		// guarantor
		$xml = $this->createXmlElement($returnValue, 'guarantor', $options);
		$xml = $this->createXmlElement($xml, 'person', $options);
		// takes tutor as guarantor if defined
		if(!empty($patient->getFieldValue('tutor_last_name'))) {
			$this->createXmlElement($xml, 'familyname', $options, $this->assertNotNull($patient, 'tutor_last_name'));
			$this->createXmlElement($xml, 'givenname', $options, $this->assertNotNull($patient, 'tutor_first_name'));
			$xml = $this->createXmlElement($xml, 'postal', $options);
			$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($patient, 'tutor_address','street'));
			$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($patient, 'tutor_address','zip_code'));
			$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($patient, 'tutor_address','city'));
		}
		// else patient is guarantor
		else {
			$xml->setAttribute('salutation', $this->formatValue($patient, 'title'));
			$this->createXmlElement($xml, 'familyname', $options, $this->assertNotNull($patient, 'last_name'));
			$this->createXmlElement($xml, 'givenname', $options, $this->assertNotNull($patient, 'first_name'));
			$xml = $this->createXmlElement($xml, 'postal', $options);
			$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($patient, 'address','street'));
			$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($patient, 'address','zip_code'));
			$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($patient, 'address','city'));
		}
		// balance
		$this->createInvoice45Balance($returnValue, $customerOrder, $options);
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request Balance node
	 * @param DOMElement $invoiceTiersType current invoice Tiers payant or Tiers garant node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice balance node
	 */
	protected function createInvoice45Balance($invoiceTiersType,$customerOrder,$options) {
		$legalEntity = $options->getValue('legalEntity');
		$returnValue = $this->createXmlElement($invoiceTiersType, 'balance', $options);		
		// vat
		$vat = $this->createXmlElement($returnValue, 'vat', $options);
		$vat->setAttribute('vat_number', $this->assertNoSepNotNull($legalEntity, 'IDE'));
		$vat->setAttribute('vat', $this->assertNumericNotNull($customerOrder, 'vatAmount'));
		// vat summary
		$vatSummary = $this->assertNotNull($customerOrder, 'vatSummary');
		$vatSummary = json_decode(stripslashes($vatSummary));
		if(!$vatSummary) throw new WigiiMedidataException('vatSummary is not valid json. '.json_last_error_msg(),WigiiMedidataException::XML_VALIDATION_ERROR);
		// creates vat entries per rate
		$ttcAmount = 0.0;
		foreach($vatSummary as $vatValue=>$vatObj) {
			$xml = $this->appendXmlElement($vat, 'vat_rate', $options);
			$xml->setAttribute('vat_rate', $vatValue);
			$orderTTCAmount = $this->assertNumericNotNull($vatObj, 'orderTTCAmount');
			$ttcAmount += $orderTTCAmount;
			$xml->setAttribute('amount', $orderTTCAmount);
			$xml->setAttribute('vat', $this->assertNumericNotNull($vatObj, 'vatAmount'));
		}		
		// sets balance attributes
		$returnValue->setAttribute('currency', 'CHF');
		$returnValue->setAttribute('amount', number_format($ttcAmount, 2, '.', ''));
		$returnValue->setAttribute('amount_due', $this->assertNumericNotNull($customerOrder, 'dueAmount'));
		$returnValue->setAttribute('amount_obligations', number_format($ttcAmount, 2, '.', ''));
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request esrQR node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice esr QR node
	 */
	protected function createInvoice45esrQR($invoiceBody,$customerOrder,$options) {
		$legalEntity = $options->getValue('legalEntity');
		$returnValue = $this->createXmlElement($invoiceBody, 'esrQR', $options);
		$returnValue->setAttribute('type', 'esrQR');
		$returnValue->setAttribute('iban', $this->assertNoSepNotNull($legalEntity, 'IBAN'));
		$returnValue->setAttribute('reference_number', $this->evaluateFuncExp(fx('txtFormatSwissBvr',$this->assertNotNull($customerOrder,'customerOrderNumber'),false)));
		// bank
		$xml = $this->createXmlElement($returnValue, 'bank', $options);
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, 'Postfinance SA');
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'zip', $options, '3030');
		$this->createXmlElement($xml, 'city', $options, 'Bern');
		// creditor
		$xml = $this->createXmlElement($returnValue, 'creditor', $options);
		$xml = $this->createXmlElement($xml, 'company', $options);
		$this->createXmlElement($xml, 'companyname', $options, $this->assertNotNull($legalEntity, 'entityName'));
		$xml = $this->createXmlElement($xml, 'postal', $options);
		$this->createXmlElement($xml, 'street', $options, $this->assertNotNull($legalEntity, 'entityAddress','street'));
		$this->createXmlElement($xml, 'zip', $options, $this->assertNotNull($legalEntity, 'entityAddress','zip_code'));
		$this->createXmlElement($xml, 'city', $options, $this->assertNotNull($legalEntity, 'entityAddress','city'));
		return $returnValue;
	}
			
	/**
	 * Creates an invoice request ivg (LAI) node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice ivg node
	 */
	protected function createInvoice45ivg($invoiceBody,$customerOrder,$options) {
		$legalEntity = $options->getValue('legalEntity');
		$patient = $options->getValue('customer');
		$returnValue = $this->createXmlElement($invoiceBody, 'ivg', $options);
		$returnValue->setAttribute('case_id', $this->assertNotNull($customerOrder, 'caseNumber'));
		$returnValue->setAttribute('case_date', $this->assertDateNotNull($customerOrder, 'caseDate'));
		$returnValue->setAttribute('ssn', $this->assertNoSepNotNull($patient, 'noAVS'));
		$returnValue->setAttribute('nif', $this->assertNoSepNotNull($legalEntity,'noNIF'));
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request uvg (LAA) node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice ivg node
	 */
	protected function createInvoice45uvg($invoiceBody,$customerOrder,$options) {		
		$patient = $options->getValue('customer');
		$returnValue = $this->createXmlElement($invoiceBody, 'uvg', $options);
		$returnValue->setAttribute('insured_id', $this->assertNotNull($customerOrder, 'caseNumber'));
		$returnValue->setAttribute('case_id', $this->assertNotNull($customerOrder, 'caseNumber'));
		$returnValue->setAttribute('case_date', $this->assertDateNotNull($customerOrder, 'caseDate'));
		$returnValue->setAttribute('ssn', $this->assertNoSepNotNull($patient, 'noAVS'));
		return $returnValue;
	}
	
	/**
	 * Creates an invoice request kvg (LAMAL) node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice kvg node
	 */
	protected function createInvoice45kvg($invoiceBody,$customerOrder,$options) {	    
	    $patient = $options->getValue('customer');
	    $returnValue = $this->createXmlElement($invoiceBody, 'kvg', $options);
	    $returnValue->setAttribute('insured_id', $this->assertNoSepNotNull($patient, 'noAVS'));
	    $returnValue->setAttribute('case_id', $this->assertNotNull($customerOrder, 'caseNumber'));
	    $returnValue->setAttribute('case_date', $this->assertDateNotNull($customerOrder, 'caseDate'));	   
	    return $returnValue;
	}
	
	/**
	 * Creates an invoice request treatement node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice treatment node
	 */
	protected function createInvoice45Treatment($invoiceBody,$customerOrder,$options) {
		$patient = $options->getValue('customer');
		$returnValue = $this->createXmlElement($invoiceBody, 'treatment', $options);
		$returnValue->setAttribute('date_begin', $this->assertDateNotNull($customerOrder, 'orderDate'));
		$returnValue->setAttribute('date_end', $this->assertDateNotNull($customerOrder, 'orderDate'));
		$returnValue->setAttribute('canton', $this->assertCanton($patient, 'address'));
		$returnValue->setAttribute('reason', 'unknown');
		return $returnValue;
	}
	
	protected $tariffTypeMapping = array('OSM'=>'326','ASTO'=>'327');
	/**
	 * Creates an invoice request services node
	 * @param DOMElement $invoiceBody current invoice body node
	 * @param Element $customerOrder element of type CustomerOrders sourcing the invoice creation
	 * @param WigiiBPLParameter $options optional bag of options to configure the generation process
	 * @return DOMElement the created invoice services node
	 */
	protected function createInvoice45Services($invoiceBody,$customerOrder,$options) {
		$services = $this->createXmlElement($invoiceBody, 'services', $options);
		// iterates through the CatalogOrders linked to this CustomerOrder and create service nodes.
		sel($this->getPrincipal(),$this->evaluateFuncExp(fx('getCustomerOrderDetail',$customerOrder->getFieldValue('customerOrderNumber'))),dfasl(
			dfas('CallbackDFA','setProcessDataChunkCallback',function($data,$callbackDFA) use($services,$customerOrder, $options){
				$legalEntity = $options->getValue('legalEntity');
				$servicesCount = $callbackDFA->getValueInContext('servicesCount');
				$servicesCount++;
				$callbackDFA->setValueInContext('servicesCount',$servicesCount);
				
				$catalogOrder = $data->getDbEntity();				
				$service = $this->appendXmlElement($services, 'service', $options);
				$service->setAttribute('record_id',$servicesCount);
				
				$articleNumber = $this->assertNotNull($catalogOrder, 'articleNumber');
				$tariffNumber = $catalogOrder->getFieldValue('tariffNumber');
				if(empty($tariffNumber)) $tariffNumber = $articleNumber;
				list($designation,$tariffType) = explode($tariffNumber,$this->assertNotNull($catalogOrder, 'designation'));
				$tariffType = trim($tariffType);
				$remark = str_replace(array('OSM','ASTO'), "", $tariffType);
				if(!empty($remark) && $remark==$tariffType) $remark=null;
				if(!empty($remark)) $tariffType = str_replace($remark,"",$tariffType);
				$coTariffType = $catalogOrder->getFieldValue('tariffType');
				if(!empty($coTariffType) && $coTariffType!='none') $tariffType = $coTariffType; 
				$tariffType = $this->tariffTypeMapping[$tariffType];
				if(empty($tariffType)) throw new WigiiMedidataException('tariff type is empty for article '.$articleNumber,WigiiMedidataException::XML_VALIDATION_ERROR);
				
				$service->setAttribute('tariff_type',$tariffType);
				$service->setAttribute('code',$tariffNumber);				
				$service->setAttribute('name',trim($designation));
				$service->setAttribute('session','1');
				$quantity = $this->assertNumericNotNull($catalogOrder, 'quantity');
				$service->setAttribute('quantity',$quantity);
				$service->setAttribute('date_begin',$this->assertDateNotNull($catalogOrder, 'orderDate'));
				$service->setAttribute('provider_id',$this->assertNoSepNotNull($legalEntity,'noGLN'));
				$glnPrincipal = $this->assertNoSep($legalEntity,'noGLNResponsible');
				if(empty($glnPrincipal)) $glnPrincipal = $this->assertNoSepNotNull($legalEntity,'noGLN');
				$service->setAttribute('responsible_id',$glnPrincipal);
				$amount = $this->assertNumericNotNull($catalogOrder, 'orderTTCTotal');
				$service->setAttribute('unit',($quantity!=0?$amount/$quantity:$amount));
				$service->setAttribute('unit_factor','1');
				$service->setAttribute('amount',$amount);
				$service->setAttribute('vat_rate',$this->assertNumeric($catalogOrder, 'vatValue'));
				$service->setAttribute('obligation','1');				
				if(!empty($remark)) $service->setAttribute('remark',$remark);
				$service->setAttribute('service_attributes','0');
			})
		));
		return $services;
	}
	
	// Tools
		
	/**
	 * Gets the XML document containing the given XML node or creates a new empty XML document
	 * @param DOMNode $xmlNode existing xml node for which to get the containing document. If null, then a new empty DOMDocument is created.
	 * If a bag of otions is given, then tries to set the document URI based on the namespaceURI option.
	 * @return DOMDocument  
	 */
	protected function getXmlDoc($xmlNode=null) {
		if(!isset($xmlNode) || ($xmlNode instanceof WigiiBPLParameter)) {
			$returnValue = new DOMDocument('1.0','UTF-8');
			$returnValue->xmlStandalone=false;
			if(isset($xmlNode)) {
				$returnValue->documentURI = $xmlNode->getValue('namespaceURI');
			}
		}
		else $returnValue = $xmlNode->ownerDocument;
		if(!isset($returnValue)) {
			if($xmlNode instanceof DOMDocument) $returnValue = $xmlNode;
			else throw new WigiiMedidataException("given xml node doesn't belong to any xml document", WigiiMedidataException::INVALID_ARGUMENT);
		}		
		return $returnValue;
	}
	/**
	 * Loads an XML document from a given xml string
	 * @param String $xmlString xml string to be parsed into an xml document
	 * @return DOMDocument
	 */
	protected function loadXmlDoc($xmlString) {
	    set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext){
	        $e = new WigiiMedidataException("Invalid XML document: ".$errno." ".$errstr, WigiiMedidataException::XML_VALIDATION_ERROR);
	        restore_error_handler();
	        throw $e;
	    });
	    $returnValue = new DOMDocument();
	    $returnValue->loadXML($xmlString);
	    restore_error_handler();
	    return $returnValue;
	}
	/**
	 * Initializes XML root element with standard namespace declarations and other attributes based on given options
	 * @param DOMElement $xmlRootNode XML root element
	 * @param WigiiBPLParameter $options bag of options for the xml generation process
	 * @return DOMElement returns root element
	 */
	protected function initializeXmlRootElement($xmlRootNode,$options) {
		$xmlRootNode->setAttribute('xmlns', $options->getValue('namespaceURI'));
		$xmlRootNode->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance','xsi:schemaLocation', $options->getValue('namespaceURI').' '.$options->getValue('xmlSchema'));		
	}
	
	/**
	 * Declares a new XML namespace into XML document
	 * @param DOMNode $xmlNode one node in current XML document
	 * @param String $namespacePrefix XML namespace prefix that will be used in document
	 * @param String $namespaceURI namespace URI	 
	 */
	protected function declareXmlNamespace($xmlNode,$namespacePrefix,$namespaceURI) {
		if(!isset($xmlNode)) throw new WigiiMedidataException('xml node cannot be null',WigiiMedidataException::INVALID_ARGUMENT);
		$rootNode = $this->getXmlDoc($xmlNode)->documentElement;
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns:'.$namespacePrefix, $namespaceURI);
	}
	
	/**
	 * Creates or gets an XML Element given its parent node, and name.
	 * @param DOMNode $parentNode existing XML node in which to create or find given element
	 * @param String $name element tag name
	 * @param WigiiBPLParameter $options a bag of options containing at least the namespaceURI and namespacePrefix
	 * @param String $nodeValue optional node value to set upon creation
	 * @return DOMElement created or found xml element
	 */
	protected function createXmlElement($parentNode,$name,$options,$nodeValue=null) {
		if(!isset($options)) $options = wigiiBPLParam();
		$returnValue = $this->createXmlElementByName($parentNode, $options->getValue('namespaceURI'), $options->getValue('namespacePrefix'), $name);
		if(isset($nodeValue)) $returnValue->nodeValue = $nodeValue;
		return $returnValue;
	}
	
	/**
	 * Creates and appends an XML Element given its parent node, and name.
	 * @param DOMNode $parentNode existing XML node in which to create the element
	 * @param String $name element tag name
	 * @param WigiiBPLParameter $options a bag of options containing at least the namespaceURI and namespacePrefix
	 * @return DOMElement created xml child element
	 */
	protected function appendXmlElement($parentNode,$name,$options) {
		if(!isset($options)) $options = wigiiBPLParam();
		return $this->appendXmlElementByName($parentNode, $options->getValue('namespaceURI'), $options->getValue('namespacePrefix'), $name);
	}
	
	/**
	 * Gets or inserts an XML Element given its parent node, and name, before a given node.
	 * @param DOMNode $parentNode existing XML node in which to create the element
	 * @param String $name element tag name
	 * @param DOMNode $beforeNode existing XML node before which to create the element
	 * @param WigiiBPLParameter $options a bag of options containing at least the namespaceURI and namespacePrefix
	 * @return DOMElement created xml element
	 */
	protected function insertXmlElement($parentNode,$name,$beforeNode,$options) {
	    if(!isset($options)) $options = wigiiBPLParam();
	    return $this->insertXmlElementByName($parentNode, $options->getValue('namespaceURI'), $options->getValue('namespacePrefix'), $name, $beforeNode);
	}
	
	/**
	 * Creates or gets an XML Element given its parent node, local name and namespace.
	 * @param DOMDocument|DOMElement $parentNode existing XML node in which to create or find given element
	 * @param String $namespaceURI xml namespace of the element
	 * @param String $namespacePrefix actual namespace used prefix 
	 * @param String $localName element tag name
	 * @return DOMElement created or found xml element
	 */
	protected function createXmlElementByName($parentNode,$namespaceURI,$namespacePrefix,$localName) {
		if(!isset($parentNode)) throw new FuncExpEvalException('parentNode cannot be null',FuncExpEvalException::INVALID_ARGUMENT);		
		// first looks for any existing node having this name
		$returnValue = $parentNode->getElementsByTagNameNS($namespaceURI, $localName);
		if($returnValue->length>0) $returnValue = $returnValue->item(0);
		// if not found, creates a new child node
		else {
			if($namespaceURI) $returnValue = $this->getXmlDoc($parentNode)->createElementNS($namespaceURI,($namespacePrefix?$namespacePrefix.':':'').$localName);
			else $returnValue = $this->getXmlDoc($parentNode)->createElement($localName);
			// attaches child node to parent
			$parentNode->appendChild($returnValue);
		}
		if($returnValue===false) throw new WigiiMedidataException("error creating xml element '$localName' attached to parent node ".$parentNode->getNodePath(), WigiiMedidataException::XML_VALIDATION_ERROR);
		return $returnValue;
	}
	
	/**
	 * Creates and appends an XML Element given its parent node, local name and namespace.
	 * @param DOMDocument|DOMElement $parentNode existing XML node in which to create the element
	 * @param String $namespaceURI xml namespace of the element
	 * @param String $namespacePrefix actual namespace used prefix
	 * @param String $localName element tag name
	 * @return DOMElement created xml element
	 */
	protected function appendXmlElementByName($parentNode,$namespaceURI,$namespacePrefix,$localName) {
		if(!isset($parentNode)) throw new FuncExpEvalException('parentNode cannot be null',FuncExpEvalException::INVALID_ARGUMENT);
		// creates a new child node
		if($namespaceURI) $returnValue = $this->getXmlDoc($parentNode)->createElementNS($namespaceURI,($namespacePrefix?$namespacePrefix.':':'').$localName);
		else $returnValue = $this->getXmlDoc($parentNode)->createElement($localName);
		// attaches child node to parent
		$parentNode->appendChild($returnValue);
		if($returnValue===false) throw new WigiiMedidataException("error creating xml element '$localName' attached to parent node ".$parentNode->getNodePath(), WigiiMedidataException::XML_VALIDATION_ERROR);
		return $returnValue;
	}
	
	/**
	 * Inserts before a given node an XML Element given its parent node, local name and namespace. If element already exists, returns it.
	 * @param DOMDocument|DOMElement $parentNode existing XML node in which to create or find given element
	 * @param String $namespaceURI xml namespace of the element
	 * @param String $namespacePrefix actual namespace used prefix
	 * @param String $localName element tag name
	 * @param DOMNode $beforeNode existing XML node before which to create the element
	 * @return DOMElement created or found xml element
	 */
	protected function insertXmlElementByName($parentNode,$namespaceURI,$namespacePrefix,$localName,$beforeNode) {
	    if(!isset($parentNode)) throw new FuncExpEvalException('parentNode cannot be null',FuncExpEvalException::INVALID_ARGUMENT);
	    // first looks for any existing node having this name
	    $returnValue = $parentNode->getElementsByTagNameNS($namespaceURI, $localName);
	    if($returnValue->length>0) $returnValue = $returnValue->item(0);
	    // if not found, creates a new child node
	    else {
	        if($namespaceURI) $returnValue = $this->getXmlDoc($parentNode)->createElementNS($namespaceURI,($namespacePrefix?$namespacePrefix.':':'').$localName);
	        else $returnValue = $this->getXmlDoc($parentNode)->createElement($localName);
	        // inserts child node into parent, before given node
	        $returnValue = $parentNode->insertBefore($returnValue,$beforeNode);
	    }
	    if($returnValue===false) throw new WigiiMedidataException("error inserting xml element '$localName' attached to parent node ".$parentNode->getNodePath(), WigiiMedidataException::XML_VALIDATION_ERROR);
	    return $returnValue;
	}
	
	// Validation
	
	/**
	 * Asserts given XML Document is valid against reference schema
	 * @param DOMDocument $xmlDoc prepared XML document
	 * @param String $schema XSD file name to be loaded fom Medidata addon repository
	 * @throws WigiiMedidataException in case of error
	 */
	protected function assertXmlIsValid($xmlDoc,$schema) {
		if(!isset($xmlDoc)) throw new WigiiMedidataException('xmlDoc cannot be null',WigiiMedidataException::INVALID_ARGUMENT);
		$schemaPath = ADDONS_PATH.'Medidata/'.$schema;
		if(!file_exists($schemaPath)) throw new WigiiMedidataException('xml schema '.$schema.' does not exist in Wigii Medidata addon folder', WigiiMedidataException::INVALID_ARGUMENT);
		libxml_use_internal_errors(true);
		$exception = null;
		// validates xml document against given schema
		if(!$xmlDoc->schemaValidate($schemaPath)) {
			$xmlErrors = libxml_get_errors();
			$exception = 'xml document is not valid against schema '.$schema;
			$fatalError=false;
			foreach($xmlErrors as $xmlError) {
				if($xmlError->code == 3083) continue; /* skips Warning XML_SCHEMAP_WARN_SKIP_SCHEMA */
				$exception .= "\n".'XML error '.$xmlError->code.' on line '.$xmlError->line.': '.$xmlError->message;
				$fatalError = true;
			}
			if($fatalError) $exception = new WigiiMedidataException($exception,WigiiMedidataException::XML_VALIDATION_ERROR);
			else $exception=null;
		}
		libxml_use_internal_errors(false);
		if(isset($exception)) throw $exception;
		else return true;
	}	
	
	/**
	 * Asserts that a field value is not null and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @param String $subfieldName optional subfield name
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertNotNull($element,$fieldName,$subfieldName=null) {
		$returnValue = $element->getFieldValue($fieldName,$subfieldName);
		if(!is_numeric($returnValue) && empty($returnValue)) throw new WigiiMedidataException("Field '$fieldName' cannot be empty",WigiiMedidataException::XML_VALIDATION_ERROR);
		return $returnValue;
	}
	/**
	 * Asserts that a field value doesn't contain any natural separator and is not null, then returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @param String $subfieldName optional subfield name
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertNoSepNotNull($element,$fieldName,$subfieldName=null) {
		$returnValue = $element->getFieldValue($fieldName,$subfieldName);
		if(!empty($returnValue)) $returnValue = str_replace(array('.','-'), "", preg_replace("/".ValueListArrayMapper::Natural_Separators."/", "", $returnValue));
		if(!is_numeric($returnValue) && empty($returnValue)) throw new WigiiMedidataException("Field '$fieldName' cannot be empty",WigiiMedidataException::XML_VALIDATION_ERROR);
		return $returnValue;
	}
	/**
	 * Asserts that a field value doesn't contain any natural separator and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @param String $subfieldName optional subfield name
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertNoSep($element,$fieldName,$subfieldName=null) {
		$returnValue = $element->getFieldValue($fieldName,$subfieldName);
		if(!empty($returnValue)) $returnValue = str_replace(array('.','-'), "", preg_replace("/".ValueListArrayMapper::Natural_Separators."/", "", $returnValue));
		return $returnValue;
	}
	/**
	 * Asserts that a field value is a date and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @param Boolean $allowNull optional flag allowing null dates or not. Default to true.
	 * @return Scalar element field value as a formatted date 
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertDate($element,$fieldName,$allowNull=true) {
		$returnValue = $element->getFieldValue($fieldName);		
		if(!empty($returnValue)) {
			$d = $m = $y = $h = $min = $s = null;
			if(Dates::fromString($returnValue, $d, $m, $y, $h, $min, $s)) {
				$returnValue = Dates::toString($d, $m, $y,'yyyy-mm-dd').'T'.Times::toString($h, $min, $s,'hh:mm:ss');
			}
			else throw new WigiiMedidataException("Field '$fieldName' is not a valid date",WigiiMedidataException::XML_VALIDATION_ERROR);
		}
		elseif(!$allowNull) throw new WigiiMedidataException("Field '$fieldName' cannot be empty",WigiiMedidataException::XML_VALIDATION_ERROR);
		return $returnValue;
	}
	/**
	 * Asserts that a field value is a non null date and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @return Scalar element field value as a formatted date
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertDateNotNull($element,$fieldName) {return $this->assertDate($element, $fieldName,false);}
	/**
	 * Asserts that a field value is a number and returns it
	 * @param Element|Scalar $element element from which to get the field value or scalar value to be tested directly
	 * @param String $fieldName the field name
	 * @param Boolean $allowNull optional flag allowing null numbers or not. Default to true.
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertNumeric($element,$fieldName,$allowNull=true) {
		if($element instanceof Record) $returnValue = $element->getFieldValue($fieldName);
		elseif(is_object($element)) $returnValue = $element->{$fieldName}; 
		else $returnValue = $element;
		if(!is_numeric($returnValue)) {
			if(!(empty($returnValue) && $allowNull)) throw new WigiiMedidataException("Field '$fieldName' is not a valid number: '$returnValue'",WigiiMedidataException::XML_VALIDATION_ERROR);
		}		
		return $returnValue;
	}	
	/**
	 * Asserts that a field value is a non null number and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertNumericNotNull($element,$fieldName) {return $this->assertNumeric($element, $fieldName,false);}
	private $cantonCodeMapping = array(
			'vaud'=>'VD',
			'valais'=>'VS',
			'genève'=>'GE','geneve'=>'GE',
			'neuchatel'=>'NE','neuchâtel'=>'NE',
			'fribourg'=>'FR',
			'berne'=>'BE','bern'=>'BE',
			'tessin'=>'TI',
			'allemagne'=>'D','france'=>'F','italie'=>'I','autriche'=>'A'		
	);
	/**
	 * Asserts that a field value is a canton code and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @param Boolean $allowNull optional flag allowing null values or not. Default to true.
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertCanton($element,$fieldName,$allowNull=true) {
		// checks for canton
		$returnValue = $element->getFieldValue($fieldName,'state');
		if(!empty($returnValue)) {
			$s = $this->cantonCodeMapping[strtolower($returnValue)];
			if(!empty($s)) $returnValue = $s;
		}
		// checks for country
		if(empty($returnValue)) {
			$returnValue = $element->getFieldValue($fieldName,'country');
			if(!empty($returnValue)) {
				$s = $this->cantonCodeMapping[strtolower($returnValue)];
				if(!empty($s)) $returnValue = $s;
			}
		}
		if(empty($returnValue) && !$allowNull) throw new WigiiMedidataException("Field '$fieldName' is not a valid canton",WigiiMedidataException::XML_VALIDATION_ERROR);		
		return $returnValue;
	}
	/**
	 * Asserts that a field value is a non null canton code and returns it
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 * @return Scalar element field value
	 * @throws WigiiMedidataException if assertion fails
	 */
	protected function assertCantonNotNull($element,$fieldName) {return $this->assertCanton($element, $fieldName,false);}	
	/**
	 * Formats and translates a field value using the Wigii TRM
	 * @param Element $element element from which to get the field value
	 * @param String $fieldName the field name
	 */
	protected function formatValue($element, $fieldName) {
		return html_entity_decode($this->getTrm()->formatValueFromFS(fs($fieldName), $element), ENT_COMPAT, "UTF-8");
	}	
}