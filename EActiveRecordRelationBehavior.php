<?php
/**
 * EActiveRecordRelationBehavior class file.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @link http://yiiext.github.com/extensions/activerecord-relation-behavior/index.html
 * @copyright Copyright &copy; 2012 Carsten Brandt
 * @license https://github.com/yiiext/activerecord-relation-behavior/blob/master/LICENSE#L1
 */

/**
 * Inspired by and put together the awesomeness of the following yii extensions:
 *
 * - can save MANY_MANY relations like cadvancedarbehavior, eadvancedarbehavior and advancedrelationsbehavior
 * - cares about relations when records get deleted like eadvancedarbehavior
 * - can save BELONGS_TO, HAS_MANY, HAS_ONE like eadvancedarbehavior and advancedrelationsbehavior
 * - saves with transaction and can handle external transactions like with-related-behavior and saverbehavior
 *
 * - does not touch additional data in MANY_MANY table (cadvancedarbehavior deleted them)
 * - does not support assigning one record to many_many relation (unclean code)
 *
 *
 * - cadvancedarbehavior        http://www.yiiframework.com/extension/cadvancedarbehavior/
 * - eadvancedarbehavior        http://www.yiiframework.com/extension/eadvancedarbehavior
 * - saverbehavior              http://www.yiiframework.com/extension/saverbehavior
 * - advancedrelationsbehavior  http://www.yiiframework.com/extension/advancedrelationsbehavior
 * - with-related-behavior      https://github.com/yiiext/with-related-behavior
 * - CSaveRelationsBehavior     http://code.google.com/p/yii-save-relations-ar-behavior/
 *
 * to be revived:
 * - esaverelatedbehavior       http://www.yiiframework.com/extension/esaverelatedbehavior
 *
 * reviewed but did not take something out:
 * - xrelationbehavior          http://www.yiiframework.com/extension/xrelationbehavior
 * - save-relations-ar-behavior http://www.yiiframework.com/extension/save-relations-ar-behavior
 *
 *
 *
 * API:
 *
 * Features:
 *
 * Todo:
 * - write documentation, add more tests
 * - read this: http://stackoverflow.com/questions/5143254/its-possible-extend-ar-relationships
 * - might want to have ignoreRelations as in http://code.google.com/p/yii-user-management/source/browse/trunk/user/components/CAdvancedArBehavior.php#84
 * - delete MANY_MANY relations before delete()
 * - set other relations to null on delete when dbms does not do it
 * - update foreign keys on pk change
 * - ensure first column of many_many_table is used as this->owners column
 * - might want to allow saving of a relation explicitly
 * - might want to save related records
 * - ensure all relations are objects, not pks after save
 *
 *
 * reloading relations:
 * if you saved a belongs_to relation you have to reload the corresponding has_one relation on the object you set.
 * if you saved a...
 *
 *
 *
 * Limitations:
 * - This will only work for AR that have PrimaryKey defined!
 * - relations defined with 'through' are not supported yet (http://www.yiiframework.com/doc/guide/1.1/en/database.arr#relational-query-with-through)
 *
 * @property CActiveRecord $owner
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @package yiiext.behaviors.activeRecordRelation
 */
class EActiveRecordRelationBehavior extends CActiveRecordBehavior
{
	/**
	 * @var bool set this to false if you dbms does not support transaction
	 * This behavior will use transactions to save MANY_MANY tables to ensure consistent data
	 * If you start a transaction yourself you can use without anything to configure. this behavior will
	 * run inside your transaction without touching it
	 */
	public $useTransaction=true;
	/** @var CDbTransaction */
	private $_transaction;

	/**
	 * Declares events and the corresponding event handler methods.
	 * @return array events (array keys) and the corresponding event handler methods (array values).
	 * @see CBehavior::events
	 */
	public function events()
	{
		return array(
			'onBeforeValidate'=>'beforeValidate',
			'onBeforeSave'=>'beforeSave',
			'onAfterSave'=>'afterSave',
//			'onBeforeDelete'=>'beforeDelete',
//			'onAfterDelete'=>'afterDelete',
		);
	}

	/**
	 * Responds to {@link CModel::onBeforeValidate} event.
	 * @throws CDbException
	 * @param CModelEvent $event event parameter
	 */
	public function beforeValidate($event)
	{
		foreach($this->owner->relations() as $name => $relation)
		{
			switch($relation[0]) // relation type such as BELONGS_TO, HAS_ONE, HAS_MANY, MANY_MANY
			{
				// BELONGS_TO: if the relationship between table A and B is one-to-many, then B belongs to A
				//             (e.g. Post belongs to User);
				// attribute of $this->owner has to be changed
				case CActiveRecord::BELONGS_TO:

					if (isset($relation['through'])) // do not do anything with relations defined with 'through'
						break;

					$pk=null;
					if (($related=$this->owner->getRelated($name, false))!==null) {
						if (is_object($related)) {
							/** @var CActiveRecord $related */
							if ($related->isNewRecord)
								throw new CDbException('You can not save a record that has new related records!');
							$pk=$related->getPrimaryKey();
						} else {
							$pk=$related;
						}
					}

					// @todo add support for composite primary keys
					if (!is_array($pk)) {
						$this->owner->setAttribute($relation[2], $pk);
					}
				break;
			}
		}
	}

	/**
	 * Responds to {@link CActiveRecord::onBeforeSave} event.
	 * @param CModelEvent $event event parameter
	 */
	public function beforeSave($event)
	{
		// ensure transactions
		if ($this->useTransaction && $this->owner->dbConnection->currentTransaction===null)
			$this->_transaction=$this->owner->dbConnection->beginTransaction();
	}

	/**
	 * Responds to {@link CActiveRecord::onAfterSave} event.
	 * @throws CDbException
	 * @param CModelEvent $event event parameter
	 */
	public function afterSave($event)
	{
		try {
			/** @var CDbCommandBuilder $commandBuilder */
			$commandBuilder=$this->owner->dbConnection->commandBuilder;

			foreach($this->owner->relations() as $name => $relation)
			{
				switch($relation[0]) // relation type such as BELONGS_TO, HAS_ONE, HAS_MANY, MANY_MANY
				{
					/* MANY_MANY: this corresponds to the many-to-many relationship in database.
					 *            An associative table is needed to break a many-to-many relationship into one-to-many
					 *            relationships, as most DBMS do not support many-to-many relationship directly.
					 */
					case CActiveRecord::MANY_MANY:

						if (isset($relation['through'])) // do not do anything with relations defined with 'through'
							break;

						Yii::trace('updating MANY_MANY table for relation '.get_class($this->owner).'.'.$name,'system.db.ar.CActiveRecord');

						// get table and fk information
						list($relationTable, $fks)=$this->parseManyManyFk($name, $relation);

						// get pks of the currently related records
						$newPKs=$this->getNewManyManyPks($name);


						// 1. delete relation table entries for records that have been removed from relation
						// @todo add support for composite primary keys
						$criteria=new CDbCriteria();
						$criteria->addNotInCondition($fks[1], $newPKs)
								 ->addColumnCondition(array($fks[0]=>$this->owner->getPrimaryKey()));
						$commandBuilder->createDeleteCommand($relationTable, $criteria)->execute();


						// 2. add new entries to relation table
						// @todo add support for composite primary keys
						$oldPKs=$this->getOldManyManyPks($name);
						foreach($newPKs as $fk) {
							if (!in_array($fk, $oldPKs)) {
								$commandBuilder->createInsertCommand($relationTable, array(
									$fks[0] => $this->owner->getPrimaryKey(),
									$fks[1] => $fk,
								))->execute();
							}
						}

						// refresh relation data
						$this->owner->getRelated($name, true);

					break;
					// HAS_MANY: if the relationship between table A and B is one-to-many, then A has many B
					//           (e.g. User has many Post);
					// HAS_ONE: this is special case of HAS_MANY where A has at most one B
					//          (e.g. User has at most one Profile);
					// need to change the foreign ARs attributes
					case CActiveRecord::HAS_MANY:
					case CActiveRecord::HAS_ONE:

						if (isset($relation['through'])) // do not do anything with relations defined with 'through'
							break;

						$newRelatedRecords=$this->owner->getRelated($name, false);
						if ($relation[0]==CActiveRecord::HAS_MANY && !is_array($newRelatedRecords))
							throw new CDbException('A HAS_MANY relation needs to be an array of records or primary keys!');
						if ($relation[0]==CActiveRecord::HAS_ONE) {
							// if relation set to null update all possibly related records
							if ($newRelatedRecords===null) {
								CActiveRecord::model($relation[1])->updateAll(
									array($relation[2]=>null),
									$relation[2].'=:pk',
									// @todo add support for composite primary keys
									array(':pk'=>$this->owner->getPrimaryKey())
								);
								break;
							}
							$newRelatedRecords=array($newRelatedRecords);
						}

						// @todo ensure hasone has only one
						// @todo update belongsto
						// @todo add support for composite primary keys

						// get related records primary keys
						$newRelatedRecords=$this->primaryKeysToObjects($newRelatedRecords, $relation[1]);
						$newPKs=$this->objectsToPrimaryKeys($newRelatedRecords);

						// update all not anymore related records
						$criteria=new CDbCriteria();

						$criteria->addNotInCondition(CActiveRecord::model($relation[1])->tableSchema->primaryKey, $newPKs)
								 ->addColumnCondition(array($relation[2]=>$this->owner->getPrimaryKey()));
						CActiveRecord::model($relation[1])->updateAll(array($relation[2]=>null), $criteria);

						Yii::trace('set HAS_ONE foreign-key field for '.get_class($this->owner),'system.db.ar.CActiveRecord'); //@todo rewrite

						/** @var CActiveRecord $record */
						foreach($newRelatedRecords as $record) {
							// only save if relation did not exist
							// @todo add support for composite primary keys
							if ($record->{$relation[2]}===null || $record->{$relation[2]} !=  $this->owner->getPrimaryKey()) {
								$record->saveAttributes(array($relation[2] => $this->owner->getPrimaryKey()));
							}
						}

					break;
				}
			}
			// commit internal transaction if one exists
			if ($this->_transaction!==null)
				$this->_transaction->commit();

		} catch(Exception $e) {
			// roll back internal transaction if one exists
			if ($this->_transaction!==null)
				$this->_transaction->rollback();
			// re-throw exception
			throw $e;
		}
	}

	/**
	 * converts an array of AR objects to primary keys
	 *
	 * @throws CDbException
	 * @param CActiveRecord[] $records
	 * @return array
	 */
	protected function objectsToPrimaryKeys($records)
	{
		$pks=array();
		foreach($records as $record) {
			if (is_object($record) && $record->isNewRecord)
				throw new CDbException('You can not save a record that has new related records!');

			$pks[]=is_object($record) ? $record->getPrimaryKey() : $record;
		}
		return $pks;
	}

	/**
	 * converts an array of primary keys to AR objects
	 *
	 * @throws CDbException
	 * @param CActiveRecord[] $pks
	 * @param string $className classname of the ARs to instantiate
	 * @return array
	 */
	protected function primaryKeysToObjects($pks, $className)
	{
		// @todo increase performance by running on query with findAllByPk()
		$records=array();
		foreach($pks as $pk) {
			$record=$pk;
			if (is_object($record) && $record->isNewRecord)
				throw new CDbException('You can not save a record that has new related records!');
			if (!is_object($record))
				$record=CActiveRecord::model($className)->findByPk($pk);
			if ($record===null)
				throw new CDbException('Related record with primary key "'.print_r($pk,true).'" does not exist!');

			$records[]=$record;
		}
		return $records;
	}

	/**
	 * returns all primary keys of the currently assigned records
	 *
	 * @throws CDbException
	 * @param string $relationName name of the relation
	 * @return array
	 */
	protected function getNewManyManyPks($relationName)
	{
		$newRelatedRecords=$this->owner->getRelated($relationName, false);
		if (!is_array($newRelatedRecords)) {
			throw new CDbException('A MANY_MANY relation needs to be an array of records or primary keys!');
		}
		// get new related records primary keys
		return $this->objectsToPrimaryKeys($newRelatedRecords);
	}

	/**
	 * returns all primary keys of the old assigned records(in database)
	 *
	 * @param string $relationName name of the relation
	 * @return array
	 */
	protected function getOldManyManyPks($relationName)
	{
		// @todo improve performance by doing simple select query instead of using AR
		$tmpAr=CActiveRecord::model(get_class($this->owner))->findByPk($this->owner->getPrimaryKey());
		return $this->objectsToPrimaryKeys($tmpAr->getRelated($relationName, true));
	}

	/**
	 * parses the foreign key definition of a MANY_MANY relation
	 *
	 * the first 7 lines are copied from CActiveFinder:561-568
	 * https://github.com/yiisoft/yii/blob/2353e0adf98c8a912f0faf29cc2558c0ccd6fec7/framework/db/ar/CActiveFinder.php#L561
	 *
	 * @throws CDbException
	 * @param string $name name of the relation
	 * @param array $relation relation definition
	 * @return array ($joinTable, $fks)
	 *               joinTable is the many-many-relation-table
	 *               fks are primary key of that table defining the relation
	 */
	protected function parseManyManyFk($name, $relation)
	{
		if(!preg_match('/^\s*(.*?)\((.*)\)\s*$/',$relation[2],$matches))
			throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key. The format of the foreign key must be "joinTable(fk1,fk2,...)".',
				array('{class}'=>get_class($this->owner),'{relation}'=>$name)));
		if(($joinTable=$this->owner->dbConnection->schema->getTable($matches[1]))===null)
			throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is not specified correctly: the join table "{joinTable}" given in the foreign key cannot be found in the database.',
				array('{class}'=>get_class($this->owner), '{relation}'=>$name, '{joinTable}'=>$matches[1])));
		$fks=preg_split('/\s*,\s*/',$matches[2],-1,PREG_SPLIT_NO_EMPTY);

		return array($joinTable, $fks);
	}
}
