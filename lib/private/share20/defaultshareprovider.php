<?php

namespace OC\Share20;

use OCP\IUser;

class DefaultShareProvider implements IShareProvider {

	/** @var \OCP\IDBConnection */
	private $dbConn;

	public function __construct(\OCP\IDBConnection $connection) {
		$this->dbConn = $connection;
	}

	/**
	 * Share a path
	 * 
	 * @param Share $share
	 * @return Share The share object
	 */
	public function create(Share $share) {

		$shareWith = null;
		$token = null;

		// Set the correct share with depening on the shareType
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			$shareWith = $share->getShareWith()->getUID();
			$token = '';
		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$shareWith = $share->getShareWith()->getGID();
			$token = '';
		} else {
			$shareWith = $share->getShareWith();
			//TODO SET ACTUAL TOKEN
		}

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share_20')
			->setValue('share_type', $qb->createParameter('shareType'))
			->setValue('share_with', $qb->createParameter('shareWith'))
			->setValue('shared_by', $qb->createParameter('sharedBy'))
			->setValue('file_id', $qb->createParameter('fileId'))
			->setValue('file_target', $qb->createParameter('fileTarget'))
			->setValue('permissions', $qb->createParameter('permissions'))
			->setValue('stime', $qb->createParameter('shareTime'))
			->setValue('token', $qb->createParameter('token'))
			->setParameter(':shareType', $share->getShareType())
			->setParameter(':sharedBy', $share->getSharedBy()->getUID())
			->setParameter(':fileId', $share->getPath()->getId())
			->setParameter(':permissions', $share->getPermissions())
			->setParameter(':fileTarget', $share->getPath()->getName())
			->setParameter(':shareTime', time())
			->setParameter(':shareWith', $shareWith)
			->setParameter(':token', $token);


		if ($share->getExpirationDate() !== null) {
			$qb->setValue('expiration', $qb->createParameter(':expireDate'))
				->setParameter(':expireDate', $share->getExpirationDate());
		}

		try {
			$qb->execute();
		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
			throw new \Exception("Share already exists!");
		}

		//Fetch the new share!
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('id')
			->from('share_20')
			->where($qb->expr()->eq('share_type', $qb->createParameter('shareType')))
			->andWhere($qb->expr()->eq('share_with', $qb->createParameter('shareWith')))
			->andWhere($qb->expr()->eq('shared_by', $qb->createParameter('sharedBy')))
			->andWhere($qb->expr()->eq('file_id', $qb->createParameter('fileId')))
			->andWhere($qb->expr()->eq('token', $qb->createParameter('token')))
			->setParameter(':shareType', $share->getShareType())
			->setParameter(':shareWith', $shareWith)
			->setParameter(':sharedBy', $share->getSharedBy()->getUID())
			->setParameter(':fileId', $share->getPath()->getId())
			->setParameter(':token', $token);

		$query = $qb->execute();
		$result = $query->fetch();
		$query->closeCursor();

		$share->setInternalId($result['id']);

		return $share;
	}

	/**
	 * Update a share
	 *
	 * @param Share $share
	 * @return Share The share object
	 */
	public function update(Share $share) {
	}

	/**
	 * Delete a share
	 *
	 * @param Share $share
	 */
	public function delete(Share $share) {

	}

	/**
	 * Get all shares by the given user
	 *
	 * @param IUser $user
	 * @param int $shareType
	 * @param int $offset
	 * @param int $limit
	 * @return Share[]
	 */
	public function getShares(IUser $user, $shareType, $offset, $limit) {

	}

	/**
	 * Get share by id
	 *
	 * @param int $id
	 * @return Share
	 */
	public function getShareById($id) {

	}

	/**
	 * Get shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @param Share[]
	 */
	public function getSharesByPath(\OCP\IUser $user, \OCP\Files\Node $path) {

	}

	/**
	 * Get shared with the given user
	 *
	 * @param IUser $user
	 * @param int $shareType
	 * @param Share
	 */
	public function getSharedWithMe(IUser $user, $shareType = null) {

	}

	/**
	 * Get a share by token and if present verify the password
	 *
	 * @param string $token
	 * @param string $password
	 * @param Share
	 */
	public function getShareByToken($token, $password = null) {

	}
}
