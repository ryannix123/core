<?php

namespace OCA\Files_Sharing\Tests\API\V2;

use OCA\Files_Sharing\API\V2\OCS;

/**
 * Class Test_Files_Sharing_Api
 */
class OCSTest extends \Test\TestCase {

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

	/** @var OCS */
	private $ocs;

	protected function setUp() {
		$this->shareManager = $this->getMockBuilder('OC\Share20\Manager')
			->disableOriginalConstructor()
			->getMock();
		$this->groupManager = $this->getMockBuilder('OCP\IGroupManager')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder('OCP\IUserManager')
			->disableOriginalConstructor()
			->getMock();
		$this->request = $this->getMockBuilder('OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder = $this->getMockBuilder('OCP\Files\Folder')
			->disableOriginalConstructor()
			->getMock();

		$this->ocs = new OCS($this->shareManager,
							 $this->groupManager,
							 $this->userManager,
							 $this->request,
							 $this->userFolder);
	}

	public function testCreatShareNoPath() {
		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareInvalidPath() {
		$this->request->expects($this->atLeastOnce())
			->method('getParam')
			->with('path')
			->willReturn('foo');
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->will($this->throwException(new \OCP\Files\NotFoundException));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(404, $res->getStatusCode());
	}

	public function testCreateShareNoShareType() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareInvalidShareType() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, 42],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareUserNoShareWith() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$this->userManager->expects($this->once())
			->method('get')
			->with(null)
			->willReturn(null);

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareUserInvalidShareWith() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
				['shareWith', null, 'invalid'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$this->userManager->expects($this->once())
			->method('get')
			->with('invalid')
			->willReturn(null);

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareUser() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
				['shareWith', null, 'valid'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager->expects($this->once())
			->method('get')
			->with('valid')
			->willReturn($user);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node, $user) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_USER &&
						$share->getShareWith() === $user &&
						$share->getPermissions() === 31
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

	public function testCreateShareUserWithPermission() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
				['shareWith', null, 'valid'],
				['permissions', null, 42],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager->expects($this->once())
			->method('get')
			->with('valid')
			->willReturn($user);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node, $user) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_USER &&
						$share->getShareWith() === $user &&
						$share->getPermissions() === 42
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

	public function testCreateShareUserWithInvalidExpire() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
				['shareWith', null, 'valid'],
				['expireDate', null, '2000-01-01 00:00:00'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager->expects($this->once())
			->method('get')
			->with('valid')
			->willReturn($user);

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(400, $res->getStatusCode());
	}

	public function testCreateShareUserWithValidExpire() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_USER],
				['shareWith', null, 'valid'],
				['expireDate', null, '2000-01-01'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager->expects($this->once())
			->method('get')
			->with('valid')
			->willReturn($user);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node, $user) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_USER &&
						$share->getShareWith() === $user &&
						$share->getPermissions() === 31 &&
						$share->getExpirationDate()->format('c') === \DateTime::createFromFormat('Y-m-d\TH:i:s', '2000-01-01T00:00:00')->format('c')
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

	public function testCreateShareGroup() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_GROUP],
				['shareWith', null, 'valid'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$group = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$this->groupManager->expects($this->once())
			->method('get')
			->with('valid')
			->willReturn($group);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node, $group) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP &&
						$share->getShareWith() === $group &&
						$share->getPermissions() === 31
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

	public function testCreateShareLink() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_LINK],
				['shareWith', null, 'FOOBAR'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
						$share->getShareWith() === 'FOOBAR' &&
						$share->getPermissions() === 1
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

	public function testCreateShareLinkWithPublicUpload() {
		$this->request->method('getParam')
			->will($this->returnValueMap([
				['path', null, 'foo'],
				['shareType', null, \OCP\Share::SHARE_TYPE_LINK],
				['shareWith', null, 'FOOBAR'],
				['publicUpload', null, 'true'],
			]));

		$node = $this->getMockBuilder('OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($node);

		$this->shareManager->expects($this->once())
			->method('createShare')
			->with(
				$this->callback(function($share) use ($node) {
					return (
						$share->getPath() === $node &&
						$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
						$share->getShareWith() === 'FOOBAR' &&
						$share->getPermissions() === 7
					);
				})
			)->will($this->returnArgument(0));

		$res = $this->ocs->createShare();
		$this->assertInstanceOf('OC_OCS_Result', $res);
		$this->assertEquals(100, $res->getStatusCode());
	}

}
