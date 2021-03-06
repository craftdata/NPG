<?php

/**
 * Np_Method_ExecuteResponse File
 * 
 * @package         Np_Method
 * @subpackage      Np_Method_ExecuteResponse
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Np_Method_ExecuteResponse Class Definition
 * 
 * @package Np_Method
 * @subpackage Np_Method_ExecuteResponse
 */
class Np_Method_ExecuteResponse extends Np_MethodResponse {

	/**
	 * Constructor
	 * 
	 * receives options array and sets into body array accordingly
	 * sets parent's $type to "ExecuteResponse"
	 * 
	 * @param array $options 
	 */
	protected function __construct(&$options) {

		parent::__construct($options);

		//SET BODY 
		foreach ($options as $key => $value) {
			switch (ucwords(strtolower($key))) {

				case "Disconnect_time":
				case "Request_retry_date":
				case "Request_trx_no":
				case "Approval_ind":
				case "Reject_reason_code":
					$this->setBodyField($key, $value);
					break;
			}
		}
	}

	public function PostValidate() {
		$this->setAck($this->validateParams($this->getHeaders()));
		//first step is GEN
		if (!$this->checkDirection()) {
			return "Gen04";
		}
		//HOW TO CHECK Gen05
//		if (!$this->ValidateDB()) {
//			return "Gen07";
//		}
		if (($timer_ack = Np_Timers::validate($this)) !== TRUE) {
			return $timer_ack;
		}
		return true;
	}

	/**
	 * updates status , last transaction and disconnect time in requests table 
	 * where request_id 
	 * 
	 * overridden from parent Np_Method
	 * 
	 * @return bool 
	 */
	public function saveToDB() {
		if (parent::saveToDB() === FALSE) {
			return FALSE;
		}
		$updateArray = array(
			'status' => 1,
			'last_transaction' => $this->getHeaderField("MSG_TYPE"),
			'disconnect_time' => Application_Model_General::getDateTimeInSqlFormat($this->getBodyField("DISCONNECT_TIME")),
		);
		// if it's execute_response that leaves, status => 0 (no more actions)
		if ($this->getHeaderField("FROM") == Application_Model_General::getSettings('InternalProvider')) {
			$updateArray['status'] = 0;
		}
		$whereArray = array(
			'request_id =?' => $this->getHeaderField("REQUEST_ID"),
		);
		$tbl = new Application_Model_DbTable_Requests(Np_Db::master());
		return $tbl->update($updateArray, $whereArray);
	}
	
	protected function addApprovalXml(&$xml, $msgType) {
		if ($this->checkApprove()) {
			$xml->$msgType->positiveApproval;
			$xml->$msgType->positiveApproval->approvalInd = "Y";
			$xml->$msgType->positiveApproval->disconnectDateTime = $this->getBodyField('DISCONNECT_TIME');
		} else {
			$xml->$msgType->negativeApproval;
			$xml->$msgType->negativeApproval->approvalInd = "N";
			$rejectReasonCode = $this->getBodyField('REJECT_REASON_CODE');
			$xml->$msgType->negativeApproval->rejectReasonCode = ($rejectReasonCode !== NULL) ? $rejectReasonCode : '';
		}
	}
	
	protected function addTrxNoXml(&$xml, $msgType) {
		$xml->$msgType->requestTrxNo = $this->getBodyField('REQUEST_TRX_NO');
	}

	
	/**
	 * method triggered after internal response to NPG
	 * 
	 * @param object $internalResponseObject
	 */
	public function postInternalRequest($internalResponseObject) {
		if ($this->getHeaderField("TO") == Application_Model_General::getSettings('InternalProvider')) {
			if (isset($internalResponseObject->connect_time)) {
				$connect_time = $internalResponseObject->connect_time;
			} else {
				$connect_time = time();
			}
			$updateArray = array('connect_time' => Application_Model_General::getDateTimeInSqlFormat($connect_time));
			$whereArray = array(
				'request_id =?' => $this->getHeaderField("REQUEST_ID"),
			);
			$tbl = new Application_Model_DbTable_Requests(Np_Db::master());
			return $tbl->update($updateArray, $whereArray);
		}
		return true;
	}

}
