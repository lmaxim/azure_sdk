<?php
/**
 * Created by JetBrains PhpStorm.
 * User: lmaxim
 * Date: 6/13/12
 * Time: 4:20 PM
 * To change this template use File | Settings | File Templates.
 */

//require_once(dirname(__FILE__).'/../vendor/azure-sdk-for-php/WindowsAzure/WindowsAzure.php');
require_once(dirname(__FILE__).'/../util/WindowsAzureAutoload.class.php');
spl_autoload_register(array('WindowsAzureAutoload', 'loadClass'), true, true);

# AZURE configuration
use WindowsAzure\Common\Configuration;

# AZURE container
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;


# AZURE blob
use WindowsAzure\Blob\BlobSettings;
use WindowsAzure\Blob\BlobService;
use WindowsAzure\Blob\Models\CreateBlobOptions;


class AzureBlobManager {

	// http://msdn.microsoft.com/en-us/library/windowsazure/dd179439.aspx
	const CONTAINER_ALREADY_EXISTS_REASON = 'The specified container already exists.';
	const BLOB_ALREADY_EXISTS = 'The specified blob already exists.';

	protected
		$_blobRestProxy = null;

	public function __construct($account_name, $account_key, $uri)
	{

		//$azure_loader = new WindowsAzureLoader();
		$config = new Configuration();
		$config->setProperty(BlobSettings::ACCOUNT_NAME, $account_name);
		$config->setProperty(BlobSettings::ACCOUNT_KEY, $account_key);
		$config->setProperty(BlobSettings::URI, $uri);

		$this->_blobRestProxy = BlobService::create($config);

	}

	protected function createContainer($container_name, $createContainerOptions)
	{
		try {
			$this->_blobRestProxy->createContainer($container_name, $createContainerOptions);
		} catch(ServiceException $e) {
			throw new ExtendedServiceException($e);
		}
	}

	public function createContainerNX($container_name)
	{
		/**
		 * @var   $blobRestProxy BlobRestProxy
		 */

		try {
			$createContainerOptions = new CreateContainerOptions();
			$createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);
			$createContainerOptions->addMetaData("public", $container_name);
			$this->createContainer($container_name, $createContainerOptions);
		}
		catch(ExtendedServiceException $e){
			if ("ContainerAlreadyExists" == $e->getErrorCode())
				return true;
			return false;
		}

		return true;
	}

	protected function createBlockBlob($container_name, $blob_name, $content, $options)
	{
		try {
			$this->_blobRestProxy->createBlockBlob($container_name, $blob_name, $content, $options);
		} catch(ServiceException $e) {
			throw new ExtendedServiceException($e);
		}
	}

	protected function deleteFile($container_name, $blob_name)
	{
		try {
			$this->_blobRestProxy->deleteBlob($container_name, $blob_name);
		} catch(ServiceException $e) {
			throw new ExtendedServiceException($e);
		}
	}

	public function putFile($container_name, $blob_name, $file_path)
	{
		# About naming and other
		# http://msdn.microsoft.com/en-us/library/windowsazure/dd135715.aspx

		$result = $this->createContainerNX($container_name);

		try {
			//$content = fopen($file_path, "r");
			$content = file_get_contents($file_path); //<--avaible

			$mime_typer = new FileMimeType();

			$options = new CreateBlobOptions();
			$options->setContentType($mime_typer->getMimeType($file_path, 'image/png'));
			/**
			 * Если setBlobContentType не задан, то будет использоваться setContentType , причем azure в конце не
			 * определяет тот ли это mime-type
			 */
			$this->createBlockBlob($container_name, $blob_name, $content, $options);

		}
		catch(ExtendedServiceException $e){
			/**
			 * But NEVER throws!
			 */

			if ("BlobAlreadyExists" == $e->getErrorCode())
				$this->deleteFile($container_name, $blob_name);
				return $this->putFile($container_name, $blob_name, $file_path);
			throw $e;
		}
		catch(HTTP_Request2_MessageException $e)
		{
			throw $e;
		}
		catch(Exception $e) {
			return false;
		}

		return true;
	}

	public function getBlobListFromContainer($container_name)
	{
		try {
			$list = array();
			$blob_list = $this->_blobRestProxy->listBlobs($container_name);
			$blobs = $blob_list->getBlobs();

			foreach($blobs as $blob)
			{
				$list[$blob->getName()] = $blob->getUrl();
			}

			return $list;
		}
		catch(ServiceException $e){
			throw $e;
		}
	}
}