<?php

namespace OC\Share20;


use OCP\IAppConfig;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\ILogger;

use OC\Share20\Exceptions\ShareNotFoundException;

/**
 * This class is the communication hub for all sharing related operations.
 */
class Manager {

	/**
	 * @var IShareProvider[]
	 */
	private $shareProviders;

	/**
	 * @var string[]
	 */
	private $shareTypeToProvider;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ILogger */
	private $logger;

	/** @var IAppConfig */
	private $appConfig;

	public function __construct(IUser $user,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ILogger $logger,
								IAppConfig $appConfig) {
		$this->user = $user;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->appConfig = $appConfig;

		// TODO: Get storage share provider from primary storage
	}

	/**
	 * Register a callback function which must return an IShareProvider instance
	 *
	 * @param string $id
	 * @param int $shareTypes
	 * @param callable $callback
	 *
	 * @throws ShareProviderAlready
	 */
	public function registerShareProvider($id, $shareTypes, callable $callback) {
		/*
		 * Providers ids have to be unique
		 */
		if (isset($this->shareProviders[$id])) {
			//TODO Exception
		}

		/*
		 * For now if a share provider registers itself with a number of supported share types
		 * this only succeeds if none of those are already registered.
		 * This is a limitation we might want to lift later but for now it makes sense
		 */
		if (count(array_intersect(array_keys($this->shareTypeToProvider, $shareTypes)))) {
			//TODO Exception
		}

		$this->shareProviders[$id] = $callback;
		foreach ($shareTypes as $shareType) {
			$this->shareTypeToProviderId[$shareType] = $id;
		}
	}

	/**
	 * Get a ShareProvider
	 *
	 * @param string $id
	 * @return IShareProvider
	 */
	private function getShareProvider($id) {
		if (!isset($this->shareProviders[$id])) {
			//Throw exception;
		}

		// Check if we have instanciated this provider yet
		if (!($this->shareProviders[$id] instanceOf OC\Share20\IShareProvider)) {
			$this->shareProviders[$id] = call_user_func($this->shareProviders[$id]);

			// Check if it actually is a IShareProvider
			if (!($provider instanceOf OC\Share20\IShareProvider)) {
				//TODO Throw exception
			}
		}

		return $this->shareProviders[$id];
	}

	/**
	 * Get shareProvider based on shareType
	 *
	 * @param int $shareType
	 * @return IShareProvider
	 */
	private function getShareProviderByType($shareType) {
		if (!isset($this->shareTypeToProviderId[$shareType])) {
			//Throw exception
		}

		return $this->getShareProvider($this->shareTypeToProviderId[$shareType]);
	}

	/**
	 * Share a path
	 * 
	 * @param Share $share
	 * @return Share The share object
	 */
	public function createShare(Share $share) {
		$provider = $this->getShareProviderByType($share->getShareType());
		$share = $provider->create($share);

		//TODO set proper provider ID to share

		return $share;
	}

	/**
	 * Update a share
	 *
	 * @param Share $share
	 * @return Share The share object
	 */
	public function updateShare(Share $share) {
		$provider = $this->getShareProviderByType($share->getShareType());
		$share = $provider->update($share);

		return $share;
	}

	/**
	 * Delete a share
	 *
	 * @param Share $share
	 */
	public function deleteShare(Share $share) {
		$provider = $this->getShareProviderByType($share->getShareType());
		
		$provider->delete($share);
	}

	/**
	 * Retrieve all shares by the current user
	 *
	 * @param int $page
	 * @param int $perPage
	 * @return Share[]
	 */
	public function getShares($page=0, $perPage=50) {
		$shares = [];

		$start = $page * $perPage;
		$left = $perPage;

		foreach($this->shareTypeToProvider as $shareType => $providerId) {
			if ($left === 0) {
				break;
			}

			$provider = $this->getShareProvider($providerId);

			$shareCount = $provider->getShareCount($shareType);
			if ($shareCount > $start) {
				$tmp = $provider->getShares($this->currentUser, $shareType, $start, $left);

				$left = $left - count($tmp);
				$start = 0;

				//TODO some share verification here

				$shares = array_merge($shares, $tmp);
			} else {
				$start = $start - $shareCount;
			}
		}

		return $shares;
	}

	/**
	 * Retrieve a share by the share id
	 *
	 * @param string $id
	 * @return Share
	 *
	 * @throws ShareNotFoundException
	 */
	public function getShareById($id) {
		$provider = getShareProvider($id);

		try {
			$share = $provider->getShareById($this->currentUser, $id);
		} catch (ShareNotFoundException $e) {
			//TODO: Some error handling?
			throw new ShareNotFoundException();
		}

		return $share;
	}

	/**
	 * Get all the shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return Share[]
	 */
	public function getSharesByPath(\OCP\Files\Node $path, $page=0, $perPage=50) {
		//TODO proper pagination
		$storageShares = $this->storageShareProvider->getSharesByPath($this->currentUser, $path);
		$federatedShares = $this->federatedShareProvider->getSharesByPath($this->currentUser, $path);

		//TODO: ID's should be unique who handles this?

		$shares = array_merge($storageShares, $federatedShares);
		return $shares;
	}

	/**
	 * Get all shares that are shared with the current user
	 *
	 * @param int $shareType
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return Share[]
	 */
	public function getSharedWithMe($shareType = null, $page=0, $perPage=50) {
		//TODO proper pagination
		$storageShares = $this->storageShareProvider->getSharedWithMe($this->currentUser, $shareType);
		$federatedShares = $this->federatedShareProvider->getSharedWithMe($this->currentUser, $shareType);

		//TODO: ID's should be unique who handles this?

		$shares = array_merge($storageShares, $federatedShares);
		return $shares;
	}

	/**
	 * Get the share by token possible with password
	 *
	 * @param string $token
	 * @param string $password
	 *
	 * @return Share
	 *
	 * @throws ShareNotFoundException
	 */
	public function getShareByToken($token, $password=null) {
		// Only link shares have tokens and they are handeld by the storageShareProvider
		try {
			$share = $this->storageShareProvider->getShareByToken($this->currentUser, $token);
		} catch (ShareNotFoundException $e) {
			// TODO some error handling
			throw new ShareNotFoundException();
		}
		
		return $share;
	}

	/**
	 * Get access list to a path. This means
	 * all the users and groups that can access a given path.
	 *
	 * Consider:
	 * -root
	 * |-folder1
	 *  |-folder2
	 *   |-fileA
	 *
	 * fileA is shared with user1
	 * folder2 is shared with group2
	 * folder1 is shared with user2
	 *
	 * Then the access list will to '/folder1/folder2/fileA' is:
	 * [
	 * 	'users' => ['user1', 'user2'],
	 *  'groups' => ['group2']
	 * ]
	 *
	 * This is required for encryption
	 *
	 * @param \OCP\Files\Node $path
	 */
	public function getAccessList(\OCP\Files\Node $path) {
	}

	private function splitId($id) {
		$split = explode(':', $id, 2);

		if (count($split) !== 2) {
			//Throw exception
		}

		return $split;
	}
}
