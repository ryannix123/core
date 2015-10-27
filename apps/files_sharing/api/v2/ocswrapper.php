<?php

namespace OCA\Files_Sharing\API\V2;

class OCSWrapper {

	private function getOCS() {
		return new OCS(\OC::$server->getShareManager(),
                       \OC::$server->getGroupManager(),
                       \OC::$server->getUserManager(),
                       \OC::$server->getRequest(),
			           \OC::$server->getUserFolder());
	}

	public function createShare() {
		return $this->getOCS()->createShare();
	}
	
}
