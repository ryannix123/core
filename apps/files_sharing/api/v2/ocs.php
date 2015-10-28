<?php

namespace OCA\Files_Sharing\API\V2;

class OCS {

	/** @var OC\Share20\Manager */
	private $shareManager;

	/** @var OCP\IGroupManager */
	private $groupManager;

	/** @var OCP\IUserManager */
	private $userManager;

	/** @var OCP\IRequest */
	private $request;

	/** @var OCP\Files\Folder */
	private $userFolder;

	public function __construct(\OC\Share20\Manager $shareManager,
	                            \OCP\IGroupManager $groupManager,
	                            \OCP\IUserManager $userManager,
	                            \OCP\IRequest $request,
								\OCP\Files\Folder $userFolder) {
		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->request = $request;
		$this->userFolder = $userFolder;
	}

	/**
	 * Convert a share object to an array we can output
	 * @param \OC\Share20\Share $share
	 * @return string[]
	 */
	private function formatShare(\OC\Share20\Share $share) {
		$result = [
			'id' => $share->getId(),
			'path' => $this->userFolder->getRelativePath($share->getPath()->getPath()),
			'permission' => $share->getPermissions(),
			'shareType' => $share->getShareType(),
			'shareWith' => null,
			'expireDate' => null,
		];

		switch ($share->getShareType()) {
			case \OCP\Share::SHARE_TYPE_USER:
				$result['shareWith'] = $share->getShareWith()->getUID();
				break;
			case \OCP\Share::SHARE_TYPE_GROUP:
				$result['shareWith'] = $share->getShareWith()->getGID();
				break;
			case \OCP\Share::SHARE_TYPE_LINK:
				$result['shareWith'] = $share->getShareWith();
				break;
			default:
				throw new \Exception();
		}

		if ($share->getExpirationDate() !== null) {
			$result['expireDate'] = $share->getExpirationDate()->format('Y-m-d');
		}

		return $result;
	}

	public function createShare() {
		// We need a path to share
		$path = $this->request->getParam('path');
		if ($path === null) {
			return new \OC_OCS_Result(null, 400, 'please specify a file or folder path');
		}

		// The path needs to be valid
		try {
			$path = $this->userFolder->get($path);
		} catch (\OCP\Files\NotFoundException $e) {
			return new \OC_OCS_Result(null, 404, 'wrong path, file/folder doesn\'t exist.');
		}

		// Share type is required
		$shareType = $this->request->getParam('shareType');
		if ($shareType === null) {
			return new \OC_OCS_Result(null, 400, 'please specify a shareType');
		}
		$shareType = (int)$shareType;

		$shareWith = $this->request->getParam('shareWith');
		$permissions = $this->request->getParam('permissions');

		if ($shareType === \OCP\Share::SHARE_TYPE_USER) {
			// A user share needs a valid user
			$shareWith = $this->userManager->get($shareWith);
			if ($shareWith === null) {
				return new \OC_OCS_Result(null, 400, 'please supply a valid user as shareWith');
			}
		} else if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			// A group share needs a valid group
			$shareWith = $this->groupManager->get($shareWith);
			if ($shareWith === null) {
				return new \OC_OCS_Result(null, 400, 'please supply a valid group as shareWith');
			}
		} else if ($shareType === \OCP\Share::SHARE_TYPE_LINK) {
			// Check if public upload is set for the link share.a
			$publicUpload = $this->request->getParam('publicUpload');
			if ($publicUpload === 'true' || (int)$publicUpload === 1) {
				$permissions = 7;
			} else {
				$permissions = 1;
			}
		} else if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			// Currently not implemented
			return new \OC_OCS_Result(null, 501);
		} else {
			return new \OC_OCS_Result(null, 400, 'invalid share type');
		}

		// Permissions are int so make sure we cast properly
		if ($permissions !== null) {
			$premissions = (int)$permissions;
		} else {
			// Fallback to default
			// TODO: This is about to be changed!
			$permissions = 31;
		}

		$expireDate = $this->request->getParam('expireDate');
		if ($expireDate !== null) {
			$expireDate = \DateTime::createFromFormat('Y-m-d', $expireDate);
			if ($expireDate === false) {
				return new \OC_OCS_Result(null, 400, 'please supply a valid date Y-m-d');
			}
			$expireDate->setTime(0,0,0);
		}

		$share = new \OC\Share20\Share();
		$share->setPath($path)
			->setShareType($shareType)
			->setShareWith($shareWith)
			->setPermissions($permissions);

		if ($expireDate !== null) {
			$share->setExpirationDate($expireDate);
		}

		$share = $this->shareManager->createShare($share);

		$output = $this->formatShare($share);

		return new \OC_OCS_Result($output);
	}
	
}
