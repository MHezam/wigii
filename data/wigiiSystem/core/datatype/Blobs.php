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
 * Created on 3 déc. 09
 * by LWR
 */
class Blobs extends DataTypeInstance {
	
	/**
	* overrides parent class
	* cette méthode contrôle les données du type de donnée. Ce contrôle ne se fait pas
	* automatiquement, si le type de donnée évolue, il faut aussi modifier cette méthode
	*/
	public function checkValues($p, $elementId, $wigiiBag, $field){
		$val=$wigiiBag->getValue($elementId, 'Blobs', $field->getFieldName());		
		if($val) {
			$size = strlen($val);			
			$allowed = 512*1024; /* CWE 11.03.2016: limits the Blobs size to 512Ko to keep CKEditor running smoothly. Blobs SQL limit is MediumText: 16Mo.*/
			if($size>$allowed) {
				throw new RecordException(str_replace(array('$$chars$$', '$$size$$','$$allowed$$'), array($size-$allowed, $size,$allowed), ServiceProvider::getTranslationService()->t($p,'exceedBlobsLimit')), RecordException::INVALID_ARGUMENT);
			}
		}
	}
}


