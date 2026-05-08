<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @author Sebastian Krupinski <krupinski01@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\JMAPC\Controller;

use OCA\JMAPC\Service\ConfigurationService;
use OCA\JMAPC\Service\CoreService;
use OCA\JMAPC\Service\HarmonizationService;
use OCA\JMAPC\Service\ServicesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;

use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class UserConfigurationController extends Controller {
	
	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigurationService $ConfigurationService,
		private CoreService $CoreService,
		private HarmonizationService $HarmonizationService,
		private ServicesService $ServicesService,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * handles services list request
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/service/list')]
	public function serviceList(): DataResponse {
		
		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve services
		try {
			$rs = $this->ServicesService->fetchByUserId($this->userId);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles connect click event
	 *
	 * @param array $service collection of configuration options
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/connect')]
	public function Connect(array $service): DataResponse {
		
		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// assign options
		$options = ['VALIDATE'];
		// execute command
		try {
			$rs = $this->CoreService->connectAccount($this->userId, $service, $options);
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		
	}

	/**
	 * handles disconnect click event
	 *
	 * @param int $sid Service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/disconnect')]
	public function Disconnect(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$this->CoreService->disconnectAccount($this->userId, $sid);
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles synchronize click event
	 *
	 * @param int $sid service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/service/harmonize')]
	public function Harmonize(int $sid): DataResponse {

		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$this->HarmonizationService->performHarmonization($this->userId, $sid, 'M');
			return new DataResponse('success');
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles remote collections fetch requests
	 *
	 * @param int $sid service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/remote/collections/fetch')]
	public function remoteCollectionsFetch(int $sid): DataResponse {
		
		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve collections
		try {
			$rs = $this->CoreService->remoteCollectionsFetch($this->userId, $sid);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * handles local collections fetch requests
	 *
	 * @param int $sid Service id
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/local/collections/fetch')]
	public function localCollectionsFetch(int $sid): DataResponse {
		
		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// retrieve collections
		try {
			$rs = $this->CoreService->localCollectionsFetch($this->userId, $sid);
			return new DataResponse($rs);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

	/**
	 * handles save correlations requests
	 *
	 * @param array $values key/value pairs to save
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/local/collections/deposit')]
	public function localCollectionsDeposit(int $sid, array $ContactCorrelations, array $EventCorrelations, array $TaskCorrelations): DataResponse {
		
		// evaluate if user id is present
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		// execute command
		try {
			$rs = $this->CoreService->localCollectionsDeposit($this->userId, $sid, $ContactCorrelations, $EventCorrelations, $TaskCorrelations);
			return $this->localCollectionsFetch($sid);
		} catch (\Throwable $th) {
			return new DataResponse($th->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

	}

}
