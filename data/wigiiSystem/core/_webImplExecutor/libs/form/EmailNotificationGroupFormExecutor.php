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
 * Created on 11 february 10
 * by LWR
 */
class EmailNotificationGroupFormExecutor extends FormExecutor {

	private $groupP;
	protected function setGroupP($groupP){ $this->groupP = $groupP; }
	protected function getGroupP(){ return $this->groupP; }

	private $actOnCheckedRecordRequest;
	protected function setActOnCheckedRecordRequest($request){ $this->actOnCheckedRecordRequest = $request; }
	protected function getActOnCheckedRecordRequest(){ return $this->actOnCheckedRecordRequest; }

	public static function createInstance($wigiiExecutor, $groupP, $record, $formId, $submitUrl, $actOnCheckedRecordRequest=""){
		$fe = new self();
		$fe->setWigiiExecutor($wigiiExecutor);
		$fe->setGroupP($groupP);
		$fe->setRecord($record);
		$fe->setFormId($formId);
		$fe->setSubmitUrl($submitUrl);
		$fe->setActOnCheckedRecordRequest($actOnCheckedRecordRequest);
		return $fe;
	}

	protected function doSpecificCheck($p, $exec){

		$transS = ServiceProvider::getTranslationService();
		$rec = $this->getRecord();
		$group = $this->getGroupP()->getGroup();

		$group->getDetail()->setEmailNotification($rec->getFieldValue("emails"));

	}

	protected function actOnCheckedRecord($p, $exec) {

		$groupAS = ServiceProvider::getGroupAdminService();

		$rec = $this->getRecord();
		$group = $this->getGroupP()->getGroup();

		try{
			$groupAS->setEmailNotification($p, $group->getId(), $group->getDetail()->getEmailNotification());
			$exec->addJsCode(" adminGroup_crtSelectedGroup = '".$group->getId()."'; ");
		} catch(GroupAdminServiceException $e){
			throw $e;
		}

		$groupAS->unLock($p, $group);

		$this->getWigiiExecutor()->throwEvent()->updateGroup(PWithGroup::createInstance($p, $group));

		//important to clear the cancel stack
		$exec->addJsCode("actOnCloseDialog('".$exec->getIdAnswer()."');");

		if($exec->getIdAnswer() != "confirmationDialog"){
			echo ExecutionServiceImpl::answerRequestSeparator;
			echo "confirmationDialog";
			echo ExecutionServiceImpl::answerParamSeparator;
		}
		if(!isset($transS)) $transS = ServiceProvider::getTranslationService();
		$this->getWigiiExecutor()->operationSuccessfullMessage("confirmationDialog", 350, $transS->t($p, "operationDoneSuccessfully"), "", "done");

		if($this->getActOnCheckedRecordRequest()!=null){
			$exec->addRequests($this->getActOnCheckedRecordRequest());
		}
	}


	protected function doRenderForm($p, $exec){
		$transS = ServiceProvider::getTranslationService();

		$rec = $this->getRecord();
		$group = $this->getGroupP()->getGroup();

//		$rec->getWigiiBag()->setHelp($transS->h($p, "authenticationServerHelp"), "authenticationServer");

		$this->getTrm()->setState($this->getState());
		$this->getTrm()->setFormRenderer($this->getFormRenderer());

		$this->getTrm()->openForm($this->getFormId(), $this->getSubmitUrl(), $this->getTotalWidth(), $this->isDialog());

		$this->getTrm()->displayRemainingForms();

		$this->getTrm()->closeForm($this->getFormId(), $this->goToNextState(), $this->getSubmitLabel(), $this->isDialog());

		$cancelJsCode = "update('NoAnswer/".$exec->getCrtWigiiNamespace()->getWigiiNamespaceUrl()."/".$exec->getCrtModule()->getModuleUrl()."/unlock/group/".$group->getId(). (array_pop(explode("/",$this->getSubmitUrl()))=="groupPanel" ? "/groupPanel" : "")."');";
		$this->getWigiiExecutor()->openAsDialogForm(
			$exec->getIdAnswer(), $this->getTotalWidth()+$this->getCorrectionWidth(),
			'$("form", this).submit();', $transS->t($p, $rec->getActivity()->getActivityName()),
			$transS->t($p, "ok"), $transS->t($p, "cancel"), $cancelJsCode);

		$this->getTrm()->addJsCodeAfterFormIsShown($this->getFormId());
	}
}



