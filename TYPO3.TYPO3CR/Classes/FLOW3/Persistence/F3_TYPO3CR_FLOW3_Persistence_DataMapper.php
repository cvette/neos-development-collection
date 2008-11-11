<?php
declare(ENCODING = 'utf-8');
namespace F3::TYPO3CR::FLOW3::Persistence;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

/**
 * @package TYPO3CR
 * @subpackage FLOW3
 * @version $Id$
 */

/**
 * A data mapper to map nodes to objects
 *
 * @package TYPO3CR
 * @subpackage FLOW3
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License, version 2
 */
class DataMapper {

	/**
	 * @var F3::FLOW3::Object::ManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var F3::FLOW3::Object::Builder
	 */
	protected $objectBuilder;

	/**
	 * @var F3::TYPO3CR::FLOW3::Persistence::IdentityMap
	 */
	protected $identityMap;

	/**
	 * @var F3::FLOW3::Persistence::Manager
	 */
	protected $persistenceManager;

	/**
	 * Injects a Object Manager
	 *
	 * @param F3::FLOW3::Object::ManagerInterface $objectManager
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function injectObjectManager(F3::FLOW3::Object::ManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * Injects a Object Object Builder
	 *
	 * @param F3::FLOW3::Object::Builder $objectBuilder
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function injectObjectBuilder(F3::FLOW3::Object::Builder $objectBuilder) {
		$this->objectBuilder = $objectBuilder;
	}

	/**
	 * Injects the identity map
	 *
	 * @param F3::TYPO3CR::FLOW3::Persistence::IdentityMap $identityMap
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function injectIdentityMap(F3::TYPO3CR::FLOW3::Persistence::IdentityMap $identityMap) {
		$this->identityMap = $identityMap;
	}

	/**
	 * Injects the persistence manager
	 *
	 * @param F3::FLOW3::Persistence::Manager $persistenceManager
	 * @return void
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function injectPersistenceManager(F3::FLOW3::Persistence::Manager $persistenceManager) {
		$this->persistenceManager = $persistenceManager;
	}

	/**
	 * Maps the nodes
	 *
	 * @param F3::PHPCR::NodeIteratorInterface $nodes
	 * @return array
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function map(F3::PHPCR::NodeIteratorInterface $nodes) {
		$objects = array();
		foreach ($nodes as $node) {
			$objects[] = $this->mapSingleNode($node);
		}

		return $objects;
	}

	/**
	 * Maps a single node into the object it represents
	 *
	 * @param F3::PHPCR::NodeInterface $node
	 * @return object
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function mapSingleNode(F3::PHPCR::NodeInterface $node) {
		$explodedNodeTypeName = explode(':', $node->getPrimaryNodeType()->getName(), 2);
		$className = str_replace('_', '::', array_pop($explodedNodeTypeName));
		$objectConfiguration = $this->objectManager->getObjectConfiguration($className);
		$properties = array();
		foreach ($node->getProperties() as $property) {
			$properties[$property->getName()] = $this->getPropertyValue($property);
		}
		$object = $this->objectBuilder->reconstituteObject($className, $objectConfiguration, $properties);
		$this->persistenceManager->getSession()->registerReconstitutedObject($object);
		$this->identityMap->registerObject($object, $node->getIdentifier());
		return $object;
	}

	/**
	 * Determines the type of a property and returns the value as corresponding native PHP type
	 *
	 * @param F3::PHPCR::PropertyInterface $property
	 * @return mixed
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 * @todo replace try/catch block with check against the PropertyDefinition when implemented
	 */
	protected function getPropertyValue(F3::PHPCR::PropertyInterface $property) {
		try {
			$propertyValue = $this->getValueValue($property->getValue(), $property->getType());
		} catch (F3::PHPCR::ValueFormatException $e) {
			$values = $property->getValues();
			$propertyValue = array();
			foreach ($values as $value) {
				$propertyValue[] = $this->getValueValue($value, $property->getType());
			}
		}

		return $propertyValue;
	}

	/**
	 * Determines the type of a Value and returns the value as corresponding native PHP type
	 *
	 * @param F3::PHPCR::ValueInterface $value
	 * @return mixed
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	protected function getValueValue(F3::PHPCR::ValueInterface $value, $type) {
		switch ($type) {
			case F3::PHPCR::PropertyType::BOOLEAN:
				$value = $value->getBoolean();
				break;
			case F3::PHPCR::PropertyType::DATE:
				$value = $value->getDate();
				break;
			case F3::PHPCR::PropertyType::DECIMAL:
			case F3::PHPCR::PropertyType::DOUBLE:
				$value = $value->getDouble();
				break;
			case F3::PHPCR::PropertyType::LONG:
				$value = $value->getLong();
				break;
			case F3::PHPCR::PropertyType::STRING:
				$value = $value->getString();
				break;
			case F3::PHPCR::PropertyType::REFERENCE:
				$value = $this->mapSingleNode($this->persistenceManager->getBackend()->getSession()->getNodeByIdentifier($value->getString()));
				break;
			default:
				throw new F3::TYPO3CR::FLOW3::Persistence::Exception::UnsupportedTypeException('The encountered value type (' . F3::PHPCR::PropertyType::nameFromValue($value->getType()) . ') cannot be mapped.', 1217843827);
				break;
		}

		return $value;
	}
}

?>