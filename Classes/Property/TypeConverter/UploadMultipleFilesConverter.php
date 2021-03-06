<?php
namespace JWeiland\Events2\Property\TypeConverter;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Stefan Frömken <froemken@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Converter for uploads.
 */
class UploadMultipleFilesConverter extends \TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter {

	/**
	 * @var array<string>
	 */
	protected $sourceTypes = array('array');

	/**
	 * @var string
	 */
	protected $targetType = 'TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage';

	/**
	 * @var integer
	 */
	protected $priority = 2;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceFactory
	 * @inject
	 */
	protected $fileFactory;

	/**
	 * This implementation always returns TRUE for this method.
	 *
	 * @param mixed $source the source data
	 * @param string $targetType the type to convert to.
	 * @return boolean TRUE if this TypeConverter can convert from $source to $targetType, FALSE otherwise.
	 * @api
	 */
	public function canConvertFrom($source, $targetType) {
		// check if $source consists of uploaded files
		foreach ($source as $uploadedFile) {
			if (!isset($uploadedFile['error']) || !isset($uploadedFile['name']) || !isset($uploadedFile['size']) || !isset($uploadedFile['tmp_name']) || !isset($uploadedFile['type'])) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Actually convert from $source to $targetType, taking into account the fully
	 * built $convertedChildProperties and $configuration.
	 *
	 * @param array $source
	 * @param string $targetType
	 * @param array $convertedChildProperties
	 * @param \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration
	 * @throws \TYPO3\CMS\Extbase\Property\Exception
	 * @return \TYPO3\CMS\Extbase\Domain\Model\AbstractFileFolder
	 * @api
	 */
	public function convertFrom($source, $targetType, array $convertedChildProperties = array(), \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface $configuration = NULL) {
		$alreadyPersistedImages = $configuration->getConfigurationValue('JWeiland\\Events2\\Property\\TypeConverter\\UploadMultipleFilesConverter', 'IMAGES');
		$originalSource = $source;
		foreach ($originalSource as $key => $uploadedFile) {
			// check if $source contains an uploaded file. 4 = no file uploaded
			if (!isset($uploadedFile['error']) || !isset($uploadedFile['name']) || !isset($uploadedFile['size']) || !isset($uploadedFile['tmp_name']) || !isset($uploadedFile['type']) || $uploadedFile['error'] === 4) {
				if ($alreadyPersistedImages[$key] !== NULL) {
					$source[$key] = $alreadyPersistedImages[$key];
				} else {
					unset ($source[$key]);
				}
				continue;
			}
			// check if uploaded file returns an error
			if (!$uploadedFile['error'] === 0) {
				return new \TYPO3\CMS\Extbase\Error\Error('Something went wrong with your uploaded files. Please try again. Error: ' . $uploadedFile['error'], 1396957314);
			}
			// now we have a valid uploaded file. Check if user has rights to upload this file
			if (!isset($uploadedFile['rights']) || empty($uploadedFile['rights'])) {
				return new \TYPO3\CMS\Extbase\Error\Error('You don\'t have checked the box for upload rights. So upload stops here', 1397464390);
			}
			// OK...we have a valid file and the user has the rights. It's time to check, if an old file can be deleted
			if ($alreadyPersistedImages[$key] instanceof \JWeiland\Events2\Domain\Model\FileReference) {
				/** @var \JWeiland\Events2\Domain\Model\FileReference $oldFile */
				$oldFile = $alreadyPersistedImages[$key];
				$oldFile->getOriginalResource()->getOriginalFile()->delete();
			}
		}

		// I will do two foreach here. First: everything must be OK, before files will be uploaded

		// upload file and add it to ObjectStorage
		/** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $references */
		$references = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage');
		$tableName = $configuration->getConfigurationValue('JWeiland\\Events2\\Property\\TypeConverter\\UploadMultipleFilesConverter', 'TABLENAME');
		foreach ($source as $uploadedFile) {
			if ($uploadedFile instanceof \JWeiland\Events2\Domain\Model\FileReference) {
				$references->attach($uploadedFile);
			} else {
				$references->attach($this->getReference($uploadedFile, $tableName));
			}
		}
		return $references;
	}

	/**
	 * upload file and get a file reference object
	 *
	 * @param array $source
	 * @param string $tableName
	 * @return \JWeiland\Events2\Domain\Model\FileReference
	 * @throws \TYPO3\CMS\Extbase\Property\Exception
	 */
	protected function getReference($source, $tableName) {
		// upload file
		$storage = ResourceFactory::getInstance()->getStorageObject(0);
		$uploadedFile = $storage->addUploadedFile($source, $storage->getFolder('uploads/tx_events2/'), $this->getTargetFileName($source), 'changeName');
		if (!$uploadedFile instanceof \TYPO3\CMS\Core\Resource\File) {
			throw new \TYPO3\CMS\Extbase\Property\Exception('Uploaded file is not of type \\TYPO3\\CMS\\Core\\Resource\\File', 1396963537);
		}
		// create reference
		/** @var $reference \JWeiland\Events2\Domain\Model\FileReference */
		$reference = $this->objectManager->get('JWeiland\\Events2\\Domain\\Model\\FileReference');
		$reference->setTablenames($tableName);
		$reference->setTitle($uploadedFile->getName());
		$reference->setUidLocal($uploadedFile->getUid());
		$reference->setOriginalResource($uploadedFile);
		return $reference;
	}

	/**
	 * Gets a Folder object from an identifier
	 *
	 * @param string $identifier
	 * @return \TYPO3\CMS\Core\Resource\Folder|\TYPO3\CMS\Core\Resource\File
	 * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidFileException
	 */
	protected function getFolderObject($identifier) {
		$object = $this->fileFactory->retrieveFileOrFolderObject($identifier);
		if (!is_object($object)) {
			throw new \TYPO3\CMS\Core\Resource\Exception\InvalidFileException('The item ' . $identifier . ' was not a file or directory!!', 1320122453);
		}
		return $object;
	}

	/**
	 * creates a target filename
	 * orig: dog.PNg --> dog_35268592817.png
	 *
	 * @param array $source
	 * @return string
	 */
	protected function getTargetFileName(array $source) {
		$fileParts = GeneralUtility::split_fileref($source['name']);
		return $fileParts['filebody'] . '_' . time() . '.' . $fileParts['fileext'];
	}

	/**
	 * @param integer $source
	 * @return \TYPO3\CMS\Core\Resource\FileReference
	 */
	protected function getOriginalResource($source) {
		return $this->fileFactory->getFileReferenceObject($source);
	}

}
