<?php
/**
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Controller;


use \OCP\IConfig;
use \OCP\IUserSession;
use \OCP\IRequest;
use \OCP\IL10N;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCA\Files_external\Service\StoragesService;
use \OCA\Files_external\NotFoundException;
use \OCA\Files_external\Lib\StorageConfig;
use \OCA\Files_External\Lib\Backend\Backend;
use \OCA\Files_External\Lib\Auth\AuthMechanism;
use \OCP\Files\StorageNotAvailableException;
use \OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use \OCA\Files_External\Service\BackendService;

/**
 * Base class for storages controllers
 */
abstract class StoragesController extends Controller {

	/**
	 * L10N service
	 *
	 * @var IL10N
	 */
	protected $l10n;

	/**
	 * Storages service
	 *
	 * @var StoragesService
	 */
	protected $service;

	/**
	 * Creates a new storages controller.
	 *
	 * @param string $AppName application name
	 * @param IRequest $request request object
	 * @param IL10N $l10n l10n service
	 * @param StoragesService $storagesService storage service
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IL10N $l10n,
		StoragesService $storagesService
	) {
		parent::__construct($AppName, $request);
		$this->l10n = $l10n;
		$this->service = $storagesService;
	}

	/**
	 * Create a storage from its parameters
	 *
	 * @param string $mountPoint storage mount point
	 * @param string $backend backend identifier
	 * @param string $authMechanism authentication mechanism identifier
	 * @param array $backendOptions backend-specific options
	 * @param array|null $mountOptions mount-specific options
	 * @param array|null $applicableUsers users for which to mount the storage
	 * @param array|null $applicableGroups groups for which to mount the storage
	 * @param int|null $priority priority
	 *
	 * @return StorageConfig|DataResponse
	 */
	protected function createStorage(
		$mountPoint,
		$backend,
		$authMechanism,
		$backendOptions,
		$mountOptions = null,
		$applicableUsers = null,
		$applicableGroups = null,
		$priority = null
	) {
		try {
			return $this->service->createStorage(
				$mountPoint,
				$backend,
				$authMechanism,
				$backendOptions,
				$mountOptions,
				$applicableUsers,
				$applicableGroups,
				$priority
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Invalid backend or authentication mechanism class')
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
	}

	/**
	 * Validate storage config
	 *
	 * @param StorageConfig $storage storage config
	 *
	 * @return DataResponse|null returns response in case of validation error
	 */
	protected function validate(StorageConfig $storage) {
		$mountPoint = $storage->getMountPoint();
		if ($mountPoint === '' || $mountPoint === '/') {
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Invalid mount point')
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if ($storage->getBackendOption('objectstore')) {
			// objectstore must not be sent from client side
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Objectstore forbidden')
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		/** @var Backend */
		$backend = $storage->getBackend();
		/** @var AuthMechanism */
		$authMechanism = $storage->getAuthMechanism();
		if ($backend->checkDependencies()) {
			// invalid backend
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Invalid storage backend "%s"', [
						$backend->getIdentifier()
					])
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if (!$backend->isVisibleFor($this->service->getVisibilityType())) {
			// not permitted to use backend
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Not permitted to use backend "%s"', [
						$backend->getIdentifier()
					])
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!$authMechanism->isVisibleFor($this->service->getVisibilityType())) {
			// not permitted to use auth mechanism
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Not permitted to use authentication mechanism "%s"', [
						$authMechanism->getIdentifier()
					])
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if (!$backend->validateStorage($storage)) {
			// unsatisfied parameters
			return new DataResponse(
				array(
					'message' => (string)$this->l10n->t('Unsatisfied backend parameters')
				),
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}
		if (!$authMechanism->validateStorage($storage)) {
			// unsatisfied parameters
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Unsatisfied authentication mechanism parameters')
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		return null;
	}

	/**
	 * Check whether the given storage is available / valid.
	 *
	 * Note that this operation can be time consuming depending
	 * on whether the remote storage is available or not.
	 *
	 * @param StorageConfig $storage storage configuration
	 */
	protected function updateStorageStatus(StorageConfig &$storage) {
		try {
			/** @var AuthMechanism */
			$authMechanism = $storage->getAuthMechanism();
			$authMechanism->manipulateStorageConfig($storage);
			/** @var Backend */
			$backend = $storage->getBackend();
			$backend->manipulateStorageConfig($storage);

			// update status (can be time-consuming)
			$storage->setStatus(
				\OC_Mount_Config::getBackendStatus(
					$backend->getStorageClass(),
					$storage->getBackendOptions(),
					false
				)
			);
		} catch (InsufficientDataForMeaningfulAnswerException $e) {
			$storage->setStatus(\OC_Mount_Config::STATUS_INDETERMINATE);
		} catch (StorageNotAvailableException $e) {
			$storage->setStatus(\OC_Mount_Config::STATUS_ERROR);
		}
	}

	/**
	 * Get an external storage entry.
	 *
	 * @param int $id storage id
	 *
	 * @return DataResponse
	 */
	public function show($id) {
		try {
			$storage = $this->service->getStorage($id);

			$this->updateStorageStatus($storage);
		} catch (NotFoundException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Storage with id "%i" not found', array($id))
				],
				Http::STATUS_NOT_FOUND
			);
		}

		return new DataResponse(
			$storage,
			Http::STATUS_OK
		);
	}

	/**
	 * Deletes the storage with the given id.
	 *
	 * @param int $id storage id
	 *
	 * @return DataResponse
	 */
	public function destroy($id) {
		try {
			$this->service->removeStorage($id);
		} catch (NotFoundException $e) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Storage with id "%i" not found', array($id))
				],
				Http::STATUS_NOT_FOUND
			);
		}

		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}

}

