<?php

use Behat\Behat\Context\BehatContext;
use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext {

	/** @var string */
	private $baseUrl = '';

	/** @var ResponseInterface */
	private $response = null;

	/** @var string */
	private $currentUser = '';

	/** @var int */
	private $apiVersion = 1;

	/** @var SimpleXMLElement */
	private $lastShareData = null;

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct(array $parameters) {

		// Initialize your context here
		$this->baseUrl = $parameters['baseUrl'];
		$this->adminUser = $parameters['admin'];
		$this->regularUser = $parameters['regular_user_password'];

		// in case of ci deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = $testServerUrl;
		}
	}

	/**
	 * @When /^sending "([^"]*)" to "([^"]*)"$/
	 */
	public function sendingTo($verb, $url) {
		$this->sendingToWith($verb, $url, null);
	}

	/**
	 * Parses the xml answer to get ocs response which doesn't match with
	 * http one in v1 of the api.
	 */
	public function getOCSResponse($response) {
		return $response->xml()->meta[0]->statuscode;
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 */
	public function getArrayOfUsersResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->users[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of groups returned.
	 */
	public function getArrayOfGroupsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->groups[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of subadmins returned.
	 */
	public function getArrayOfSubadminsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of apps returned.
	 */
	public function getArrayOfAppsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->apps[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * This function is needed to use a vertical fashion in the gherkin tables.
	 */
	public function simplifyArray($arrayOfArrays){
		$a = array_map(function($subArray) { return $subArray[0]; }, $arrayOfArrays);
		return $a;
	}
   
	/**
	 * @Then /^users returned are$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function theUsersShouldBe($usersList) {
		if ($usersList instanceof \Behat\Gherkin\Node\TableNode) {
			$users = $usersList->getRows();
			$usersSimplified = $this->simplifyArray($users);
			$respondedArray = $this->getArrayOfUsersResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals($usersSimplified, $respondedArray, "", 0.0, 10, true);
		}

	}

	/**
	 * @Then /^groups returned are$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function theGroupsShouldBe($groupsList) {
		if ($groupsList instanceof \Behat\Gherkin\Node\TableNode) {
			$groups = $groupsList->getRows();
			$groupsSimplified = $this->simplifyArray($groups);
			$respondedArray = $this->getArrayOfGroupsResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals($groupsSimplified, $respondedArray, "", 0.0, 10, true);
		}

	}

	/**
	 * @Then /^subadmin groups returned are$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function theSubadminGroupsShouldBe($groupsList) {
		if ($groupsList instanceof \Behat\Gherkin\Node\TableNode) {
			$groups = $groupsList->getRows();
			$groupsSimplified = $this->simplifyArray($groups);
			$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals($groupsSimplified, $respondedArray, "", 0.0, 10, true);
		}

	}

	/**
	 * @Then /^subadmin users returned are$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function theSubadminUsersShouldBe($groupsList) {
		$this->theSubadminGroupsShouldBe($groupsList);
	}

	/**
	 * @Then /^apps returned are$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function theAppsShouldBe($appList) {
		if ($appList instanceof \Behat\Gherkin\Node\TableNode) {
			$apps = $appList->getRows();
			$appsSimplified = $this->simplifyArray($apps);
			$respondedArray = $this->getArrayOfAppsResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals($appsSimplified, $respondedArray, "", 0.0, 10, true);
		}

	}

	/**
	 * @Then /^the OCS status code should be "([^"]*)"$/
	 */
	public function theOCSStatusCodeShouldBe($statusCode) {
		PHPUnit_Framework_Assert::assertEquals($statusCode, $this->getOCSResponse($this->response));
	}

	/**
	 * @Then /^the HTTP status code should be "([^"]*)"$/
	 */
	public function theHTTPStatusCodeShouldBe($statusCode) {
		PHPUnit_Framework_Assert::assertEquals($statusCode, $this->response->getStatusCode());
	}

	/**
	 * @Given /^As an "([^"]*)"$/
	 */
	public function asAn($user) {
		$this->currentUser = $user;
	}

	/**
	 * @Given /^using api version "([^"]*)"$/
	 */
	public function usingApiVersion($version) {
		$this->apiVersion = $version;
	}

	/**
	 * @Given /^user "([^"]*)" exists$/
	 */
	public function assureUserExists($user) {
		try {
			$this->userExists($user);			
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$previous_user = $this->currentUser;
			$this->currentUser = "admin";
			$this->creatingTheUser($user);
			$this->currentUser = $previous_user;
		}
		$this->userExists($user);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());

	}

	public function userExists($user){
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->adminUser;

		$this->response = $client->get($fullUrl, $options);
	}

	/**
	 * @Given /^user "([^"]*)" belongs to group "([^"]*)"$/
	 */
	public function userBelongsToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$groups = array($group);
		$respondedArray = $this->getArrayOfGroupsResponded($this->response);
		PHPUnit_Framework_Assert::assertEquals($groups, $respondedArray, "", 0.0, 10, true);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Given /^user "([^"]*)" does not belong to group "([^"]*)"$/
	 */
	public function userDoesNotBelongToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$groups = array($group);
		$respondedArray = $this->getArrayOfGroupsResponded($this->response);
		PHPUnit_Framework_Assert::assertNotEquals($groups, $respondedArray, "", 0.0, 10, true);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}


	/**
	 * @Given /^user "([^"]*)" is subadmin of group "([^"]*)"$/
	 */
	public function userIsSubadminOfGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group/subadmins";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$subadmins = array($user);
		$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertContains($user, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Given /^user "([^"]*)" is not a subadmin of group "([^"]*)"$/
	 */
	public function userIsNotSubadminOfGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group/subadmins";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$subadmins = array($user);
		$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertNotContains($user, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Given /^user "([^"]*)" does not exist$/
	 */
	public function userDoesNotExist($user) {
		try {
			$this->userExists($user);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			PHPUnit_Framework_Assert::assertEquals(404, $ex->getResponse()->getStatusCode());
			return;
		}
		$previous_user = $this->currentUser;
		$this->currentUser = "admin";
		$this->deletingTheUser($user);
		$this->currentUser = $previous_user;
		try {
			$this->userExists($user);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			PHPUnit_Framework_Assert::assertEquals(404, $ex->getResponse()->getStatusCode());
		}
	}

	/**
	 * @Given /^app "([^"]*)" is disabled$/
	 */
	public function appIsDisabled($app) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/apps?filter=disabled";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfAppsResponded($this->response);
		PHPUnit_Framework_Assert::assertContains($app, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Given /^app "([^"]*)" is enabled$/
	 */
	public function appIsEnabled($app) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/apps?filter=enabled";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfAppsResponded($this->response);
		PHPUnit_Framework_Assert::assertContains($app, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	public function createUser($user) {
		$this->creatingTheUser($user);
		$this->userExists($user);
	}

	public function deleteUser($user) {
		$this->deletingTheUser($user);
		$this->userDoesNotExist($user);
	}

	public function createGroup($group) {
		$this->creatingTheGroup($group);
		$this->groupExists($group);
	}

	public function deleteGroup($group) {
		$this->deletingTheGroup($group);
		$this->groupDoesNotExist($group);
	}

	public function creatingTheUser($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$options['body'] = [
							'userid' => $user,
							'password' => '123456'
							];

		$this->response = $client->send($client->createRequest("POST", $fullUrl, $options));

	}

	/**
	 * @When /^creating the group "([^"]*)"$/
	 */
	public function creatingTheGroup($group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$options['body'] = [
							'groupid' => $group,
							];

		$this->response = $client->send($client->createRequest("POST", $fullUrl, $options));
	}

	/**
	 * @When /^Deleting the user "([^"]*)"$/
	 */
	public function deletingTheUser($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->send($client->createRequest("DELETE", $fullUrl, $options));
	}

	/**
	 * @When /^Deleting the group "([^"]*)"$/
	 */
	public function deletingTheGroup($group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/groups/$group";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$this->response = $client->send($client->createRequest("DELETE", $fullUrl, $options));
	}

	/**
	 * @Given /^Add user "([^"]*)" to the group "([^"]*)"$/
	 */
	public function addUserToGroup($user, $group) {
		$this->userExists($user);
		$this->groupExists($group);
		$this->addingUserToGroup($user, $group);

	}

	/**
	 * @When /^User "([^"]*)" is added to the group "([^"]*)"$/
	 */
	public function addingUserToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		}

		$options['body'] = [
							'groupid' => $group,
							];

		$this->response = $client->send($client->createRequest("POST", $fullUrl, $options));
	}


	public function groupExists($group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->adminUser;

		$this->response = $client->get($fullUrl, $options);
	}

	/**
	 * @Given /^group "([^"]*)" exists$/
	 */
	public function assureGroupExists($group) {
		try {
			$this->groupExists($group);			
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$previous_user = $this->currentUser;
			$this->currentUser = "admin";
			$this->creatingTheGroup($group);
			$this->currentUser = $previous_user;
		}
		$this->groupExists($group);
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @Given /^group "([^"]*)" does not exist$/
	 */
	public function groupDoesNotExist($group) {
		try {
			$this->groupExists($group);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			PHPUnit_Framework_Assert::assertEquals(404, $ex->getResponse()->getStatusCode());
			return;
		}
		$previous_user = $this->currentUser;
		$this->currentUser = "admin";
		$this->deletingTheGroup($group);
		$this->currentUser = $previous_user;
		try {
			$this->groupExists($group);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			PHPUnit_Framework_Assert::assertEquals(404, $ex->getResponse()->getStatusCode());
		}
	}

	/**
	 * @When /^sending "([^"]*)" to "([^"]*)" with$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function sendingToWith($verb, $url, $body) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php" . $url;
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUser];
		}
		if ($body instanceof \Behat\Gherkin\Node\TableNode) {
			$fd = $body->getRowsHash();
			$options['body'] = $fd;
		}

		try {
			$this->response = $client->send($client->createRequest($verb, $fullUrl, $options));
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @When /^creating a public share with$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function createPublicShare($body) {
		$this->sendingToWith("POST", "/apps/files_sharing/api/v1/shares", $body);
		$this->lastShareData = $this->response->xml();
	}

	/**
	 * @Then /^Public shared file "([^"]*)" can be downloaded$/
	 */
	public function checkPublicSharedFile($filename) {
		$client = new Client();
		$options = [];
		$url = $this->lastShareData->data[0]->url;
		$fullUrl = $url . "/download";
		$options['save_to'] = "./$filename";
		$this->response = $client->get($fullUrl, $options);
		$finfo = new finfo;
		$fileinfo = $finfo->file("./$filename", FILEINFO_MIME_TYPE);
		PHPUnit_Framework_Assert::assertEquals($fileinfo, "text/plain");
		if (file_exists("./$filename")) {
        	unlink("./$filename");
        }
	}

	/**
	 * @Then /^Public shared file "([^"]*)" with password "([^"]*)" can be downloaded$/
	 */
	public function checkPublicSharedFileWithPassword($filename, $password) {
		$client = new Client();
		$options = [];
		$token = $this->lastShareData->data[0]->token;
		$fullUrl = substr($this->baseUrl, 0, -4) . "public.php/webdav";
		$options['auth'] = [$token, $password];
		$options['save_to'] = "./$filename";
		$this->response = $client->get($fullUrl, $options);
		$finfo = new finfo;
		$fileinfo = $finfo->file("./$filename", FILEINFO_MIME_TYPE);
		PHPUnit_Framework_Assert::assertEquals($fileinfo, "text/plain");
		if (file_exists("./$filename")) {
        	unlink("./$filename");
        }
	}

	/**
	 * @When /^Adding expiration date to last share$/
	 */
	public function addingExpirationDate() {
		$share_id = $this->lastShareData->data[0]->id;
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->apiVersion}/shares/$share_id";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUser];
		}
		$date = date('Y-m-d', strtotime("+3 days"));
		$options['body'] = ['expireDate' => $date];
		$this->response = $client->send($client->createRequest("PUT", $fullUrl, $options));
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
	}
}
