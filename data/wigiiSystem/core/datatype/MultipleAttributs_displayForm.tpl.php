<?php
/**
 *  This file is part of Wigii.
 *
 *  Wigii is free software: you can redistribute it and\/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Wigii is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Wigii.  If not, see <http:\//www.gnu.org/licenses/>.
 *
 *  @copyright  Copyright (c) 2012 Wigii 		 http://code.google.com/p/wigii/    http://www.wigii.ch
 *  @license    http://www.gnu.org/licenses/     GNU General Public License
 */

/*
 * Created on 4 déc. 09
 * by LWR
 */
if(!isset($transS)) $transS = ServiceProvider::getTranslationService();

$fieldXml = $field->getXml();

//defining width if existant
if($parentWidth != null){
	$valueWidth = " width:".($parentWidth-1)."px; "; //select we don't need to make it smaller
}

//defining readOnly or disabled
$readonly = $this->getRecord()->getWigiiBag()->isReadonly($fieldName);
$disabled = $this->getRecord()->getWigiiBag()->isDisabled($fieldName);

// getting prefixFilter if defined
$prefixFilter = (string)$fieldXml['prefixFilter'];
$filterDropDown = !empty($prefixFilter);

$subFieldName = "value";

if((string)$fieldXml["useCheckboxes"]=="1"){

	//define the options:
	$useMultipleColumn = (int)(string)$fieldXml["useMultipleColumn"];
	$inputNode = "input";
	$inputType = "checkbox";
	$inputName = $fieldName.'_'.$subFieldName.'[]';
	$val = $this->getRecord()->getFieldValue($fieldName, $subFieldName);
//	eput($val);
	foreach($fieldXml->attribute as $attribute_key => $attribute){

		// filters dropdown using prefix filter
		if($filterDropDown && $attribute != "none" && strpos((string)$attribute, $prefixFilter)!==0) continue;
		
		//the checkbox is before the text of the option
		//the width of the checkbox is valueWidth / useMultipleColumn if defined

		if($useMultipleColumn>0){
			$this->put('<div style="float:left; width:'.(($parentWidth-5)/$useMultipleColumn).'px;" >');
		}
		$inputId = $formId.'_'.$fieldName.'_'.$subFieldName.'_'.str_replace(" ", "_", (string)$attribute).'_'.($inputType==null?$inputNode:$inputType);

		$label = $this->getRecord()->getRedirectedFieldLabel($this->getP(), $fieldName, $attribute);

		$tempDisabled = $disabled;
		if(!$label && $label!=="0"){
			$label = $transS->t($p, (string)$attribute, $attribute);
			$tempDisabled = true;
		}

		//add the checkbox
		$this->put('<'.$inputNode.' id="'.$inputId.'" name="'.$inputName.'" '.($attribute["disabled"]=="1" ? 'disabled="on"' : "").' '.($attribute["class"]!="" ? 'class="'.(string)$attribute["class"].'"' : "").' ');
		if($inputType != null) $this->put(' type="'.$inputType.'" ');
		$this->put(' value="'.(string)$attribute.'" ');
		if($tempDisabled) $this->put(' disabled ');
		if($readonly) $this->put(' disabled class="removeDisableOnSubmit" ');
		if(($val != null && array_search((string)$attribute, $val) !== false)) $this->put(' checked="on" ');
		$this->put(' style="');
		if($fieldXml["displayAsTag"]=="1") $this->put(' float:left; '); //the label will be float left, so the input should be as well
		if($readonly) $this->put('background-color:#E3E3E3;'); //disabled make color as white in Google Chrome
		$this->put('" />');
		//add the label
		if($attribute["noLabel"]!="1"){
			if($fieldXml["displayAsTag"]=="1"){
				$label = $this->doFormatForTag($label, $fieldXml);
			}
			if($useMultipleColumn>0) $labelWidth = (($parentWidth-5)/$useMultipleColumn)-30;
			else $labelWidth = ($parentWidth-30);
			$this->put('<label style="padding-left:5px;" for="'.$inputId.'" ><div style="display: inline-table;width:'.$labelWidth.'px;">'.$label.'</div></label>');
		}
		if($useMultipleColumn>0){
			$this->put('</div>');
		} else {
			$this->put('<br>'); //next line for the next label and checkbox
		}
	}

} else {
	$inputNode = "select";
	$inputType = null;
	$inputId = $formId.'_'.$fieldName.'_'.$subFieldName.'_'.($inputType==null?$inputNode:$inputType);
	$inputName = $fieldName.'_'.$subFieldName.'[]';

	$this->put('<'.$inputNode.' id="'.$inputId.'" name="'.$inputName.'" ');
	if($inputType != null) $this->put(' type="'.$inputType.'" ');
	if($disabled) $this->put(' disabled ');
	if($readonly) $this->put(' disabled ');
	$this->put('class="');
	if($readonly) $this->put('removeDisableOnSubmit ');
	$chosen = (string)$fieldXml["chosen"]=="1";
	if($chosen) $this->put('chosen ');
	
	if((string)$fieldXml["allowNewValues"]=="1") $this->put('allowNewValues ');
	$this->put('"');
	if((string)$fieldXml["size"]!="") $this->put(' size="'.(string)$fieldXml["size"].'" ');
	elseif((string)$fieldXml["searchable"]!="1") $this->put(' size="5" ');
	$this->put(' multiple="multiple" ');
	$this->put(' style="'.$valueWidth);
	if($readonly) $this->put('background-color:#E3E3E3;'); //disabled make color as white in Google Chrome
	$this->put('" >');

	$val = $this->getRecord()->getFieldValue($fieldName, $subFieldName);

	$sameAsField = (string)$fieldXml["sameAsField"];
	if(!empty($sameAsField)) {
		$sameAsFieldId = $formId.'_'.$sameAsField.'_'.$subFieldName.'_'.($inputType==null?$inputNode:$inputType);
		$this->addJsCode('$("#'.$inputId.'").html($("#'.$sameAsFieldId.' option").clone()).find("option[selected]").prop("selected", false);');
		$s = ''; $first=true;		
		foreach($val as $k) {
			if($first) $first = false;
			else $s .= ', ';
			$s .= 'option[value='."'$k'".']';			
		}
		$this->addJsCode('$("#'.$inputId.'").'.'find("'.$s.'").prop("selected", "selected");');
		// checks for eventual new values
		if($fieldXml["allowNewValues"]=="1"){
			$existingKeys = array();
			foreach($fieldXml->attribute as $attribute_key => $attribute){
				$existingKeys[(string)$attribute] = (string)$attribute;
			}
			foreach($val as $k) {
				if($existingKeys[$k] == null) {
					$labelForTitle = $transS->t($p, $k);
					$label = $labelForTitle;
					if(!$chosen && strlen($label)>64) {
						$label = substr($label, 0, 61)."...";
					}
					$label = str_replace(" ", "&nbsp;", $label);
					$htmlOption = "'".'<option selected="selected" value="'.$k.'" title="'.$labelForTitle.'" >'.$label.'</option>'."'";
					$this->addJsCode('$("#'.$inputId.'").append('.$htmlOption.')');
				}	
			}		
		}
	}
	else {
		//define the options:
		$html2text = new Html2text();
		foreach($fieldXml->attribute as $attribute_key => $attribute){
			// filters dropdown using prefix filter
			if($filterDropDown && $attribute != "none" && strpos((string)$attribute, $prefixFilter)!==0) continue;
			
			if(($val != null && array_search((string)$attribute, $val) !== false)) $selected = ' selected="selected" ';
			else $selected = "";
			$label = $this->getRecord()->getRedirectedFieldLabel($this->getP(), $fieldName, $attribute);
			$tempDisabled = false;
			if(!$label && $label!=="0"){
				$label = $transS->t($p, (string)$attribute, $attribute);
				$tempDisabled = true;
			}
			
			// cleans up the html
			$html2text->html2text($label);
			$label = $html2text->get_text();
			$html2text->clear();
			
			if($attribute["optGroupStart"]=="1"){
				$this->put('<optgroup '.($tempDisabled || $attribute["disabled"]=="1" ? 'disabled="on"' : "").' label="'.$label.'" >');
			} else if($attribute["optGroupEnd"]=="1"){
				$this->put('</optgroup>');
			} else {
				$labelForTitle = $label;
				if(!$chosen && strlen($label)>64) {
					$label = substr($label, 0, 61)."...";
				}
				$label = str_replace(" ", "&nbsp;", $label);
				$this->put('<option '.($tempDisabled || $attribute["disabled"]=="1" ? 'disabled="on"' : "").' '.($attribute["class"]!="" ? 'class="'.(string)$attribute["class"].'"' : "").' value="'.(string)$attribute.'" '.$selected.' title="'.$labelForTitle.'" >'.$label.'</option>');
			}
		}
	}
	unset($html2text);
	$this->put('</'.$inputNode.'>');

	//ici il ne faut pas metre la condition if disable car s'il est disable il peut très bien se faire
	//enable par un autre checkbox. Donc il faut que le code js soit présent...
	if(!$readonly) {
		//$this->getExecutionService()->addJsCode("multipleSelectVals['".$inputId."'] = $('#".$inputId."').val(); $('#".$inputId."').click(function(){ multipleSelectOnClick(this.id); });");
	}
}

