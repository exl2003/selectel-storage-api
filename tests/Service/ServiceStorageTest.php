<?php namespace ForumHouse\SelectelStorageApi\Test\ServiceStorage;

use Exception;
use ForumHouse\SelectelStorageApi\Authentication\CredentialsAuthentication;
use ForumHouse\SelectelStorageApi\Authentication\Exception\AuthenticationFailedException;
use ForumHouse\SelectelStorageApi\Authentication\IAuthentication;
use ForumHouse\SelectelStorageApi\Container\Container;
use ForumHouse\SelectelStorageApi\Exception\ParallelOperationException;
use ForumHouse\SelectelStorageApi\Exception\UnexpectedHttpStatusException;
use ForumHouse\SelectelStorageApi\File\Exception\CrcFailedException;
use ForumHouse\SelectelStorageApi\File\File;
use ForumHouse\SelectelStorageApi\File\SymLink;
use ForumHouse\SelectelStorageApi\Service\OfflineStorageService;
use ForumHouse\SelectelStorageApi\Service\StorageService;
use GuzzleHttp\Client;
use PHPUnit_Framework_TestCase;

/**
 * Class ServiceStorageTest
 *
 * @package ForumHouse\SelectelStorageApi\Test\ServiceStorage
 */
class ServiceStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $testFileName;

    /**
     * @var IAuthentication
     */
    protected $auth;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var StorageService
     */
    protected $service;

    /**
     * @var string
     */
    protected $containerUrl;

    /**
     * @var string
     */
    protected $containerSecretKey;

    /**
     * @throws AuthenticationFailedException
     * @throws UnexpectedHttpStatusException
     */
    protected function setUp()
    {
        parent::setUp();

        $config = include(__DIR__.'/../data/config.php');
        $this->container = new Container($config['container_name']);
        $this->containerUrl = $config['container_url'];
        $this->containerSecretKey = $config['container_secret_key'];

        $this->auth = new CredentialsAuthentication($config['auth_user'], $config['auth_key'], $config['auth_url']);
        $this->auth->authenticate();

        $this->service = new StorageService($this->auth);

        $this->testFileName = __DIR__.'/../data/test_file.txt';

    }

    protected function tearDown()
    {
        parent::tearDown();

        //Deleting file
        $file = new File($this->testFileName);
        $this->service->deleteFile($this->container, $file);
    }


    /**
     * @throws AuthenticationFailedException
     * @throws UnexpectedHttpStatusException
     * @throws CrcFailedException
     */
    public function testUploadFile()
    {
        $file = new File('test.txt');
        $file->setLocalName($this->testFileName);
        $file->setContentType();
        $file->setSize();

        $this->service->uploadFile($this->container, $file);
        (new Client())->get($this->containerUrl.'/'.$file->getServerName());
    }

    /**
     * @throws ParallelOperationException
     */
    public function testUploadFiles()
    {
        $file = new File('test.txt');
        $file->setLocalName($this->testFileName);
        $file->setContentType();
        $file->setSize();

        $this->service->uploadFiles($this->container, [$file], false);
        (new Client())->get($this->containerUrl.'/'.$file->getServerName());
    }

    /**
     * @depends testUploadFile
     * @throws AuthenticationFailedException
     * @throws UnexpectedHttpStatusException
     */
    public function testDeleteFile()
    {
        $file = new File($this->testFileName);

        $this->service->deleteFile($this->container, $file);
    }

    /**
     * @depends testUploadFile
     * @throws AuthenticationFailedException
     * @throws UnexpectedHttpStatusException
     */
    public function testDeleteFiles()
    {
        $file = new File($this->testFileName);

        $this->service->deleteFiles($this->container, [$file]);
    }

    /**
     * @depends testUploadFile
     * @throws CrcFailedException
     * @throws UnexpectedHttpStatusException
     * @throws Exception
     */
    public function testCreateSymlink()
    {
        $file = new File('test.txt');
        $file->setLocalName($this->testFileName);
        $file->setContentType();
        $file->setSize();

        $this->service->uploadFile($this->container, $file);

        $link = new SymLink();
        $link->setType(SymLink::TYPE_ONETIME);
        $link->setServerName($this->testFileName);
        $this->service->createSymlink($this->container, $link);

        //TODO: validate file is downloadable using this symlink
    }

    /**
     * @depends testUploadFile
     * @throws CrcFailedException
     * @throws UnexpectedHttpStatusException
     * @throws Exception
     */
    public function testCreateSymlinks()
    {
        $file = new File('test.txt');
        $file->setLocalName($this->testFileName);
        $file->setContentType();
        $file->setSize();

        $this->service->uploadFile($this->container, $file);

        $link = new SymLink();
        $link->setType(SymLink::TYPE_ONETIME);
        $link->setServerName($this->testFileName);
        $this->service->createSymlinks($this->container, [$link]);

        //TODO: validate file is downloadable using this symlink
    }

    /**
     * @depends testUploadFile
     * @throws CrcFailedException
     * @throws UnexpectedHttpStatusException
     * @throws Exception
     */
    public function testSignUrl()
    {
        $file = new File('test.txt');
        $file->setLocalName($this->testFileName);
        $file->setContentType();
        $file->setSize();

        $this->service->uploadFile($this->container, $file);

        //$this->service->setAccountSecretKey($this->containerSecretKey);

        $signedUrl = (new OfflineStorageService())->signFileDownloadLink(
            $this->containerUrl.'/'.$file->getServerName(),
            time() + 600,
            $this->containerSecretKey
        );

        //No exception is expected here
        (new Client())->get($signedUrl);
    }
}
