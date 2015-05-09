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
 * Created on 15 sept. 09
 * by LWR
 */
class NewUserFormExecutor extends EditUserFormExecutor {

	public static function createInstance($wigiiExecutor, $userP, $record, $formId, $submitUrl, $actOnCheckedRecordRequest=""){
		$fe = new self();
		$fe->setWigiiExecutor($wigiiExecutor);
		$fe->setUserP($userP);
		$fe->setRecord($record);
		$fe->setFormId($formId);
		$fe->setSubmitUrl($submitUrl);
		$fe->setActOnCheckedRecordRequest($actOnCheckedRecordRequest);
		return $fe;
	}

	protected function getDialogTitle($p, $exec){
		$transS = ServiceProvider::getTranslationService();
		return $transS->t($p, "userNew");
	}

}



