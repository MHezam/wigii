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
 * Created on 4 déc. 09 by LWR
 * Modified by CWE on 25.02.2016 to display a deprecated message to user if an old value is present in field and does not exist in the drop down anymore.
 * Modified by Medair in 2016 for maintenance purposes (see SVN log for details)
 */

if(!isset($transS)) $transS = ServiceProvider::getTranslationService();
if(!isset($exec)) $exec = ServiceProvider::getExecutionService();

$fieldXml = $field->getXml();

//defining width if existant
if($parentWidth != null){
	if($fieldXml["flex"]=="1"){
		$valueWidth = " width: 100%; max-width:".($parentWidth)."px; ";  //select we don't need to make it smaller
	} else {
		$valueWidth = " width: 100%; max-width:".($parentWidth)."px; ";  //select we don't need to make it smaller
	}
}

//defining readOnly or disabled
$readonly = $this->getRecord()->getWigiiBag()->isReadonly($fieldName);
$disabled = $this->getRecord()->getWigiiBag()->isDisabled($fieldName);
$isPublicPrincipal = ServiceProvider::getAuthorizationService()->isPublicPrincipal($this->getP());

// getting prefixFilter if defined
$prefixFilter = (string)$fieldXml['prefixFilter'];
$filterDropDown = !empty($prefixFilter);

$subFieldName = "value";

if((string)$fieldXml["useRadioButtons"]=="1" || (string)$fieldXml["useCheckboxes"]=="1"){

	//define the options:
	$useMultipleColumn = (int)(string)$fieldXml["useMultipleColumn"];
	$inputNode = "input";
	if((string)$fieldXml["useRadioButtons"]=="1") $inputType = "radio";
	else $inputType ="checkbox";

	$inputName = $fieldName.'_'.$subFieldName.'';
	$val = $this->formatValueToPreventInjection($this->getRecord()->getFieldValue($fieldName, $subFieldName));

	//if none attribute exist then add code to uncheck existing values if click on it
	$allowUnchek = false;
	if($fieldXml->xpath("attribute[text()='none']")){
		$allowUnchek = true;
	}
    $firstLoop = true;
	foreach($fieldXml->attribute as $attribute_key => $attribute){

		if($attribute == "none") continue;

		// filters dropdown using prefix filter
		if($filterDropDown && strpos((string)$attribute, $prefixFilter)!==0) continue;
		// CWE 09.02.2016: in public: filters disabled options
		if($isPublicPrincipal && $attribute["disabled"]=="1") continue;

        if(!$firstLoop && !$useMultipleColumn) {
            $this->put('<br>'); //next line for the next label and radiobutton
        }
		
		//the radioButton is before the text of the option
		//the width of the checkbox is valueWidth / useMultipleColumn if defined
		if($useMultipleColumn>0){
			$this->put('<div style="float:left; width: 100%; max-width:'.(($parentWidth)/$useMultipleColumn).'px;" >');
		}
		$inputId = $formId.'_'.$fieldName.'_'.$subFieldName.'_'.str_replace(" ", "_", (string)$attribute).'_'.($inputType==null?$inputNode:$inputType);

		$label = $this->getRecord()->getRedirectedFieldLabel($this->getP(), $fieldName, $attribute);
		$labelDBValue = (string)$attribute;
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

		if(($val != null && (string)$attribute==$val)) $this->put(' checked="on" ');
		$this->put(' style="');
		if($fieldXml["displayAsTag"]=="1") $this->put(' float:left; '); //the label will be float left, so the input should be as well
		if($readonly) $this->put('background-color:#E3E3E3;'); //disabled make color as white in Google Chrome
		$this->put('" />');
		//add the label
		if($attribute["noLabel"]!="1"){
			if($fieldXml["displayAsTag"]=="1"){
				$label = $this->doFormatForTag($label, $fieldXml, $labelDBValue);
			} else if ((string)$attribute["color"]){
				$color = (string)$attribute["color"];
				$label = '<span style="padding:2px 10px 2px 10px;line-height:21px;background-color:#'.$color.';color:#'.getBlackOrWhiteFromBackgroundColor($color).'">'.$label.'</span>';
			}
			if($useMultipleColumn>0) $labelWidth = (($parentWidth-5)/$useMultipleColumn)-30;
			else $labelWidth = ($parentWidth-30);
			$this->put('<label style="padding-left:5px;" for="'.$inputId.'" ><div style="display: inline-table;width: 100%; max-width:'.$labelWidth.'px;">'.$label.'</div></label>');
		}
		if($useMultipleColumn>0){
			$this->put('</div>');
		}

        if($firstLoop){
            $firstLoop = false;
        }
	}

	if((string)$fieldXml["useCheckboxes"]=="1"){
		$exec->addJsCode("
$('div#".$formId."__".$fieldName." .value input:checkbox').click(function() {
    $('div#".$formId."__".$fieldName." .value input:checkbox').attr('checked', false);" .
    ($allowUnchek
    		? "if($(this).val()==radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."){ $(this).attr('checked', false); radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= ''; } else { $(this).attr('checked', true); radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= $(this).val(); }"
    		: "$(this).attr('checked', true);"
    		)."
});
");
	}
	if($allowUnchek){
		$exec->addJsCode("
radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= '';
$('div#".$formId."__".$fieldName." .value').mouseover(function(e) { radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= $('input[name=$inputName]:checked').attr('value'); });
				".((string)$fieldXml["useCheckboxes"]=="1" ? "" : "$('input[name=$inputName]').click(function(e) { if($(this).val()==radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."){ $(this).attr('checked', false); radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= ''; } else { radioButtonClick_".$formId.'_'.$fieldName.'_'.$subFieldName."= $(this).val(); } }); ")."
");
	}

} else {

$inputNode = "select";
$inputType = null;
$inputId = $formId.'_'.$fieldName.'_'.$subFieldName.'_'.($inputType==null?$inputNode:$inputType);
$inputName = $fieldName.'_'.$subFieldName;

$this->put('<'.$inputNode.' id="'.$inputId.'" name="'.$inputName.'" ');
if($inputType != null) $this->put(' type="'.$inputType.'" ');
if($disabled) $this->put(' disabled ');
if($readonly) $this->put(' disabled ');
$this->put('class="');
if($readonly) $this->put('removeDisableOnSubmit ');
$chosen = (string)$fieldXml["chosen"]=="1";
if($chosen) $this->put('chosen ');
$flex = (string)$fieldXml["flex"]=="1";
if($flex) $this->put('flex ');
$flex = $flex || $chosen;
if((string)$fieldXml["allowNewValues"]=="1") $this->put('allowNewValues ');
$this->put('"');
$this->put(' style="'.$valueWidth);
if($readonly) $this->put('background-color:#E3E3E3;'); //disabled make color as white in Google Chrome
$this->put('" >');

$val = $this->formatValueToPreventInjection($this->getRecord()->getFieldValue($fieldName, $subFieldName));

$sameAsField = (string)$fieldXml["sameAsField"];
if(!empty($sameAsField)) {	
	$sameAsFieldId = $formId.'_'.$sameAsField.'_'.$subFieldName.'_'.($inputType==null?$inputNode:$inputType);
	// clones drop-down and unselects value
	$this->addJsCode('$("#'.$inputId.'").attr("data-placeholder", $("#'.$sameAsFieldId.'").attr("data-placeholder")).html($("#'.$sameAsFieldId.' option").clone()).find("option[selected]").prop("selected", false);');
	// selects new value (the two calls must be separated to work)
	$this->addJsCode('$("#'.$inputId.'").'.'find("option[value='."'$val'".']").prop("selected", "selected");');
	
	$existingKeys = array();
	foreach($fieldXml->attribute as $attribute_key => $attribute){
		$existingKeys[(string)$attribute] = (string)$attribute;
	}
	if($existingKeys[$val] == null) {
		// Adds non matching value as a new value if allowed
		if($fieldXml["allowNewValues"]=="1"){
			$labelForTitle = $transS->t($p, $val);
			$label = $labelForTitle;
			if(!$flex && strlen($label)>64) {
				$label = substr($label, 0, 61)."...";
			}
			$label = str_replace(" ", "&nbsp;", $label);
			$htmlOption = "'".'<option selected="selected" value="'.$val.'" title="'.$labelForTitle.'" >'.$label.'</option>'."'";
			$this->addJsCode('$("#'.$inputId.'").append('.$htmlOption.')');
		}
		// CWE 25.02.2016: displays a deprecated message to user if an old value is present in field and does not exist in the drop down anymore.
		elseif($val && !$isPublicPrincipal) {
			$currentFlow = $this->evalfx(fx('ctlCurrentFlow'));
			if($currentFlow == ElementEvaluator::ELEMENT_FLOW_COPY || $currentFlow == ElementEvaluator::ELEMENT_FLOW_EDIT) {
				$deprecatedMessage=str_replace('$value$',"&apos;".$val."&apos;",$transS->t($p,'removeDeprecatedValue'));
				$this->addJsCode(
						"$('#".$formId.'__'.$fieldName." div.value').wigii('bindHelpService',{
					width:250,height:80,
					type:'warning',
					localContent:true,
					content:'".$deprecatedMessage."'
				});");
			}
		}
	}		
}
else {
	$valExistsInOption = false;
	//define the options:
	$html2text = new Html2text();
	foreach($fieldXml->attribute as $attribute_key => $attribute){
		$label = $this->getRecord()->getRedirectedFieldLabel($this->getP(), $fieldName, $attribute);
		$tempDisabled = false;
		if(!$label && $label!=="0"){
			$label = $transS->t($p, (string)$attribute, $attribute);
			$tempDisabled = true;
		}
		
		// cleans up the html
		$html2text->setHtml($label);
		$label = $html2text->getText();
// 		$html2text->clear();
		$label = trim($label);
		
		if($attribute == "none" && $flex){
			if($label != '' && $label!='&nbsp;') {
				$this->addJsCode("$('#".$inputId."').attr('data-placeholder','".$label."')");		
			}
			$this->put('<option '.($attribute["class"]!="" ? 'class="'.(string)$attribute["class"].'"' : "").' value="" title="" ></option>');
			continue;
		}
		
		// filters dropdown using prefix filter
		if($filterDropDown && $attribute != "none" && strpos((string)$attribute, $prefixFilter)!==0) continue;
		// CWE 09.02.2016: in public: filters disabled options
		if($isPublicPrincipal && $attribute["disabled"]=="1") continue;
		
		if(!$valExistsInOption) $valExistsInOption = $val == (string)$attribute;
		if(($val == (string)$attribute)){
			$selected = ' selected="selected" ';
		} else {
			$selected = "";
		}		
	
		if($attribute["optGroupStart"]=="1"){
			$this->put('<optgroup '.($tempDisabled || $attribute["disabled"]=="1" ? 'disabled="on"' : "").' label="'.$label.'" >');
		} else if($attribute["optGroupEnd"]=="1"){
			$this->put('</optgroup>');
		} else {
			$labelForTitle = $label;
			if(!$flex && strlen($label)>64) {
				$label = substr($label, 0, 61)."...";
			}
			$label = str_replace(" ", "&nbsp;", $label);
			$this->put('<option '.($tempDisabled || $attribute["disabled"]=="1" ? 'disabled="on"' : "").' '.$selected.' '.($attribute["class"]!="" ? 'class="'.(string)$attribute["class"].'"' : "").' value="'.(string)$attribute.'" title="'.$labelForTitle.'" >'.$label.'</option>');
		}
	}
	unset($html2text);
	// Adds non matching value as a new value if allowed
	if($fieldXml["allowNewValues"]=="1" && !$valExistsInOption){
		$labelForTitle = $transS->t($p, $val);
		$label = $labelForTitle;
		if(!$flex && strlen($label)>64) {
			$label = substr($label, 0, 61)."...";
		}
		$label = str_replace(" ", "&nbsp;", $label);
		$this->put('<option selected="selected" value="'.$val.'" title="'.$labelForTitle.'" >'.$label.'</option>');
	}
	// CWE 25.02.2016: displays a deprecated message to user if an old value is present in field and does not exist in the drop down anymore.
	elseif(!$valExistsInOption && $val && !$isPublicPrincipal) {
		$currentFlow = $this->evalfx(fx('ctlCurrentFlow'));
		if($currentFlow == ElementEvaluator::ELEMENT_FLOW_COPY || $currentFlow == ElementEvaluator::ELEMENT_FLOW_EDIT) {
			$deprecatedMessage=str_replace('$value$',"&apos;".$val."&apos;",$transS->t($p,'removeDeprecatedValue'));
			$this->addJsCode(
			"$('#".$formId.'__'.$fieldName." div.value').wigii('bindHelpService',{
				width:250,height:80,
				type:'warning',
				localContent:true,
				content:'".$deprecatedMessage."'
			});");
		}
	}
}
$this->put('</'.$inputNode.'>');

}
