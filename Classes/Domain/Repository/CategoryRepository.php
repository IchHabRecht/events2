<?php
namespace JWeiland\Events2\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Stefan Froemken <sfroemken@jweiland.net>, jweiland.net
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @package events2
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CategoryRepository extends \TYPO3\CMS\Extbase\Domain\Repository\CategoryRepository {

	/**
	 * get all categories defined in given parent UID
	 *
	 * @param string $categoryUids UIDs category
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function getCategories($categoryUids) {
		$categoryUids = GeneralUtility::intExplode(',', $categoryUids);
		$query = $this->createQuery();
		return $query->matching($query->in('uid', $categoryUids))->execute();
	}

	/**
	 * get all categories given by comma seperated list
	 *
	 * @param string $categoryUids comma seperated list of category uids
	 * @param integer $parent parent category UID
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	public function getSelectedCategories($categoryUids, $parent = NULL) {
		$selectedCategories = GeneralUtility::intExplode(',', $categoryUids);
		$query = $this->createQuery();

		$constraint = array();
		$constraint[] = $query->in('uid', $selectedCategories);

		if ($parent !== NULL) {
			$constraint[] = $query->equals('parent', $parent);
		}

		return $query->matching($query->logicalAnd($constraint))->execute();
	}

}