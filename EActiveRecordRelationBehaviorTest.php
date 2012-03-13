<?php

/**
 *
 * Run this test with:
 *
 * phpunit --coverage-html tmp/coverage --colors EActiveRecordRelationBehaviorTest.php
 *
 *
 */

namespace yiiext\behaviors\ActiveRecordRelation\tests;
define('TEST_NAMESPACE', 'yiiext\behaviors\ActiveRecordRelation\tests');


if (!defined('YII_PATH')) {
	$yii = dirname(__FILE__).'/../../yii/framework/yiit.php';
	require_once($yii);
}

require_once(dirname(__FILE__).'/EActiveRecordRelationBehavior.php');

/**
 *
 * @author CeBe <mail@cebe.cc>
 */
class EActiveRecordRelationBehaviorTest extends \CTestCase
{
	public $db;
	/** @var EActiveRecordRelationBehaviorTestMigration */
	protected $migration;

	public function setUp()
	{
		$basePath=dirname(__FILE__).'/tmp';
		if (!file_exists($basePath))
			mkdir($basePath, 0777, true);
		if (!file_exists($basePath.'/runtime'))
			mkdir($basePath.'/runtime', 0777, true);

		if (!$this->db)
			$this->db = $basePath.'/test.'.uniqid(time()).'.db';

		// create webapp
		if (\Yii::app()===null) {
			\Yii::createWebApplication(array(
			    'basePath'=>$basePath,
				'components' => array(
					'db' => array(
						'connectionString' => 'sqlite:'.$this->db,
					)
				)
			));
		}
		\Yii::app()->db->connectionString = 'sqlite:'.$this->db;
		\Yii::app()->db->active = false;

		// create db
		$this->migration = new EActiveRecordRelationBehaviorTestMigration();
		$this->migration->dbConnection = \Yii::app()->db;
		$this->migration->up();

	}

	public function tearDown()
	{
		if (!$this->hasFailed() && $this->migration && $this->db) {
			$this->migration->down();
			unlink($this->db);
		}
	}

	/**
	 * test creation of AR and assigning a relation with HAS_ONE
	 * this also tests the HAS_ONE oposite BELONGS_TO
	 */
	public function testHasOne()
	{
		// check if the normal thing works
		$john = $this->getJohn();
		$this->assertSaveSuccess($john);

		// create a jane to make sure her relation does not change
		$jane = $this->getJane();
		$this->assertSaveSuccess($jane);

		$this->assertNull($john->profile);
		$this->assertEquals(array(), $john->posts);
		$this->assertEquals($this->getJohn(1)->attributes, $john->attributes);

		$john->refresh();

		$this->assertNull($john->profile);
		$this->assertEquals(array(), $john->posts);
		$this->assertEquals($this->getJohn(1)->attributes, $john->attributes);

		$john->profile = new Profile();
		$this->assertNotNull($john->profile);
		$this->assertEquals(array(), $john->posts);
		$this->assertEquals($this->getJohn(1)->attributes, $john->attributes);

		$john->profile->website = 'http://www.example.com/';
		$this->assertEquals('http://www.example.com/', $john->profile->website);

		// saving with a related record that is new should fail
		$exception=false;
		try {
			$john->save();
		} catch (\CDbException $e) {
			$exception=true;
		}
		$this->assertTrue($exception, 'Expected CDbException on saving with a non saved record.');

		$this->assertSaveFailure($john->profile, array('owner_id')); // owner_id is required and not set by EActiveRecordRelationBehavior
		$john->profile->owner = $john;
		$this->assertSaveSuccess($john->profile);

		// after profile is saved, john should be saved
		$this->assertSaveSuccess($john);
		$this->assertNotNull($john->profile);

		/** @var Profile $profile */
		$this->assertNotNull($profile=Profile::model()->findByPk($john->profile->owner_id));
		/** @var User $user */
		$this->assertNotNull($user=User::model()->findByPk($john->id));

		$this->assertEquals($profile, $user->profile);
		$this->assertEquals($user, $profile->owner);

		$this->assertNull($jane->profile);
		$jane->refresh();
		$this->assertNull($jane->profile);

		// this can not work since pk of profile can not be null
		// @todo should be supported
		/*$p = $john->profile;
		$this->assertNotNull($john->profile);
		$john->profile = null;
		$this->assertNull($john->profile);
		$this->assertSaveSuccess($john);
		$this->assertNull($john->profile);
		$john->refresh();
		$this->assertNull($john->profile);
		$p->refresh();*/
	}

	/**
	 * test creation of AR and assigning a relation with HAS_MANY as pk values
	 * one record is added as object later
	 */
	public function testHasManyPk()
	{
		$author = $this->getJohn();
		$this->assertSaveSuccess($author);

		$this->assertEquals(array(), $author->posts);
		$posts = $this->getPosts(10);
		for($n=1;$n<10;$n++) {
			$posts[$n] = $posts[$n]->id;
		}
		$author->posts = $posts;
		$this->assertEquals(9, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(9, count($author->posts));
		$author->refresh();
		$this->assertEquals(9, count($author->posts));

		// remove some records
		unset($posts[1]);
		unset($posts[3]);

		$author->posts = $posts;
		$this->assertEquals(7, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(7, count($author->posts));
		$author->refresh();
		$this->assertEquals(7, count($author->posts));

		// remove some, add some records
		unset($posts[4]);
		unset($posts[5]);
		$p = new Post();
		$p->title = 'testtitle';
		$this->assertSaveSuccess($p);
		$posts[] = $p; // this one is mixed

		$author->posts = $posts;
		$this->assertEquals(6, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(6, count($author->posts));
		$author->refresh();
		$this->assertEquals(6, count($author->posts));

		// remove all records
		$author->posts = array();
		$this->assertEquals(0, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(0, count($author->posts));
		$author->refresh();
		$this->assertEquals(0, count($author->posts));
	}

	/**
	 * test creation of AR and assigning a relation with HAS_MANY as objects values
	 * one record is added as pk later
	 */
	public function testHasManyObject()
	{
		$author = $this->getJohn();
		$this->assertSaveSuccess($author);

		$this->assertEquals(array(), $author->posts);
		$posts = $this->getPosts();
		$author->posts = $posts;
		$this->assertEquals(9, count($author->posts));
		// saving with a related record that is new should fail
		$exception=false;
		try {
			$author->save();
		} catch (\CDbException $e) {
			$exception=true;
		}
		$this->assertTrue($exception, 'Expected CDbException on saving with a non saved record.');
		// saving last record
		$this->assertSaveSuccess(end($posts));
		$this->assertEquals(9, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(9, count($author->posts));
		$author->refresh();
		$this->assertEquals(9, count($author->posts));

		// remove some records
		unset($posts[1]);
		unset($posts[3]);

		$author->posts = $posts;
		$this->assertEquals(7, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(7, count($author->posts));
		$author->refresh();
		$this->assertEquals(7, count($author->posts));

		// remove some, add some records
		unset($posts[4]);
		unset($posts[5]);
		$p = new Post();
		$p->title = 'testtitle';
		$this->assertSaveSuccess($p);
		$posts[] = $p->id; // this one is mixed

		$author->posts = $posts;
		$this->assertEquals(6, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(6, count($author->posts));
		$author->refresh();
		$this->assertEquals(6, count($author->posts));

		// remove all records
		$author->posts = array();
		$this->assertEquals(0, count($author->posts));
		$this->assertSaveSuccess($author);

		$this->assertEquals(0, count($author->posts));
		$author->refresh();
		$this->assertEquals(0, count($author->posts));
	}

	/**
	 * @return array
	 */
	protected function beforeManyMany()
	{
		$untouchedCategory1 = new Category();
		$untouchedCategory1->name = 'untouched1';
		$this->assertEquals(0, count($untouchedCategory1->posts));
		$this->assertSaveSuccess($untouchedCategory1);
		$this->assertEquals(0, count($untouchedCategory1->posts));

		$untouchedCategory2 = new Category();
		$untouchedCategory2->name = 'untouched2';
		$untouchedCategory2->posts = $this->getPosts(10, true);
		$this->assertEquals(9, count($untouchedCategory2->posts));
		$this->assertSaveSuccess($untouchedCategory2);
		$this->assertEquals(9, count($untouchedCategory2->posts));

		return array($untouchedCategory1, $untouchedCategory2);
	}

	/**
	 * @param Category $untouchedCategory1
	 * @param Category $untouchedCategory2
	 */
	protected function afterManyMany($untouchedCategory1, $untouchedCategory2)
	{
		$this->assertEquals('untouched1', $untouchedCategory1->name);
		$this->assertEquals(0, count($untouchedCategory1->posts));
		$untouchedCategory1->refresh();
		$this->assertEquals('untouched1', $untouchedCategory1->name);
		$this->assertEquals(0, count($untouchedCategory1->posts));

		$this->assertEquals('untouched2', $untouchedCategory2->name);
		$this->assertEquals(9, count($untouchedCategory2->posts));
		$untouchedCategory2->refresh();
		$this->assertEquals('untouched2', $untouchedCategory2->name);
		$this->assertEquals(9, count($untouchedCategory2->posts));
	}

	public function manymanyData()
	{
		return array(
			array(true, 1), // only pks
			array(true, 2), // mixed
			array(false, 1), // only model objects
		);
	}

	/**
	 * test creation of AR and assigning a relation with MANY_MANY as pk values
	 *
	 * @dataProvider manymanyData
	 */
	public function testManyMany($postsAsPk, $modulo)
	{
		if ($postsAsPk) { // first with pk data
			$posts=$this->getPosts(10, true);
			for($n=1;$n<10;$n++) {
				if ($n % $modulo == 0) {
					$posts[$n] = $posts[$n]->id;
				}
			}
		} else {
			$posts = $this->getPosts(10, true); // second with object data
		}

		list($un1, $un2) = $this->beforeManyMany();

		// begin real test
		$category = new Category();
		$category->name = 'my new cat';
		$this->assertEquals(array(), $category->posts);
		$category->save();
		$this->assertEquals(array(), $category->posts);

		$category->save();

		$this->assertEquals(0, count($category->posts));
		$category->posts = $posts;
		$this->assertEquals(9, count($category->posts));
		$category->save();
		$this->assertEquals(9, count($category->posts));

		// remove some records
		unset($posts[1]);
		unset($posts[3]);

		$this->assertEquals(9, count($category->posts));
		$category->posts = $posts;
		$this->assertEquals(7, count($category->posts));
		$category->save();
		$this->assertEquals(7, count($category->posts));

		// remove some, add some records
		unset($posts[4]);
		unset($posts[5]);
		$p = new Post();
		$p->title = 'testtitle';
		$this->assertSaveSuccess($p);
		$posts[] = $p->id; // this one is mixed

		$this->assertEquals(7, count($category->posts));
		$category->posts = $posts;
		$this->assertEquals(6, count($category->posts));
		$category->save();
		$this->assertEquals(6, count($category->posts));


		// @todo test if additional relation data is touched

		// end real test, checking untouched
		$this->afterManyMany($un1, $un2);
	}

	/**
	 * test creation of AR and assigning a relation with MANY_MANY as objects values
	 */
	public function testManyManyObject()
	{
		list($un1, $un2) = $this->beforeManyMany();

		// begin real test

		// @todo write test

		// end real test, checking untouched
		$this->afterManyMany($un1, $un2);
	}

	/**
	 * @expectedException CDbException
	 */
	public function testManyManyException()
	{
		$cat = new Category();
		$cat->posts = 1;
		$cat->save();
	}

	/**
	 * @expectedException CDbException
	 */
	public function testYiiException1()
	{
		$cat = new Category();
		$cat->broken = true;
		$cat->posts = array();
		$cat->save();
	}

	/**
	 * @expectedException CDbException
	 */
	public function testYiiException2()
	{
		$cat = new Category();
		$this->migration->dropRelationTable();
		$cat->posts = array();
		$cat->save();
	}

	/**
	 * @param \CActiveRecord $ar
	 */
	public function assertSaveSuccess($ar)
	{
		$this->assertTrue($ar->save(), 'Expected saving of AR '.get_class($ar).' without errors: '.print_r($ar->getErrors(),true));
	}

	/**
	 * @param \CActiveRecord $ar
	 */
	public function assertSaveFailure($ar, $failedAttributes=null)
	{
		$this->assertFalse($ar->save(), 'Expected saving of AR '.get_class($ar).' to fail.');
		if ($failedAttributes!==null)
			$this->assertEquals($failedAttributes, array_keys($ar->getErrors()));
	}

	public static function assertEquals($expected, $actual, $message = '', $delta = 0, $maxDepth = 10)
	{
		if ($expected instanceof \CActiveRecord) {
			/** @var \CActiveRecord $expected */
			self::assertNotNull($actual, 'Failed asserting that two ActiveRecords are equal. Second is null. '.$message);
			self::assertTrue($expected->equals($actual), 'Failed asserting that two ActiveRecords are equal. '.$message);
		} elseif ($actual instanceof \CActiveRecord) {
			/** @var \CActiveRecord $actual */
			self::assertNotNull($expected, 'Failed asserting that two ActiveRecords are equal. First is null. '.$message);
			self::assertTrue($actual->equals($expected), 'Failed asserting that two ActiveRecords are equal. '.$message);
		} else {
			parent::assertEquals($expected, $actual, $message, $delta, $maxDepth);
		}
	}

	/**
	 * @return User
	 */
	protected function getJohn($id=null, $save=false)
	{
		$john = new User();
		if ($id)
			$john->id = $id;
		$john->username = 'John';
		$john->password = '123456';
		$john->email = 'john@doe.com';
		if ($save)
			$this->assertSaveSuccess($john);
		return $john;
	}

	/**
	 * @return User
	 */
	protected function getJane($id=null, $save=false)
	{
		$jane = new User();
		if ($id)
			$jane->id = $id;
		$jane->username = 'Jane';
		$jane->password = '654321';
		$jane->email = 'jane@doe.com';
		if ($save)
			$this->assertSaveSuccess($jane);
		return $jane;
	}

	protected function getPosts($saveUntil=9, $addAuthor=false)
	{
		$posts = array();
		for($n=1;$n<10;$n++) {
			$p = new Post();
			$p->title = 'title'.$n;
			$p->content = 'content'.$n;
			if ($n < $saveUntil) {
				if ($addAuthor) {
					$p->author = ($n % 2 == 0) ? $this->getJane(null, true) : $this->getJohn(null, true);
				}
				$this->assertSaveSuccess($p);
			}
			$posts[$n] = $p;
		}
		return $posts;
	}

}

class EActiveRecordRelationBehaviorTestMigration extends \CDbMigration
{
	private $relationTableDropped=true;
	public function up()
	{
		ob_start();
		// these are the tables from yii definitive guide
		// http://www.yiiframework.com/doc/guide/1.1/en/database.arr
		$this->createTable('tbl_user', array(
             'id'=>'pk',
             'username'=>'string',
             'password'=>'string',
             'email'=>'string',
		));
		$this->createTable('tbl_profile', array(
             'owner_id'=>'pk',
             'photo'=>'binary',
             'website'=>'string',
		     'FOREIGN KEY (owner_id) REFERENCES tbl_user(id)',
		));
		$this->createTable('tbl_post', array(
             'id'=>'pk',
             'title'=>'string',
             'content'=>'text',
             'create_time'=>'timestamp',
             'author_id'=>'integer',
             'FOREIGN KEY (author_id) REFERENCES tbl_user(id)',
		));
		$this->createTable('tbl_post_category', array(
             'post_id'=>'integer',
             'category_id'=>'integer',
             'post_order'=>'integer', // added this row to test additional attributes added to a relation
		     'PRIMARY KEY(post_id, category_id)',
		     'FOREIGN KEY (post_id) REFERENCES tbl_post(id)',
		     'FOREIGN KEY (category_id) REFERENCES tbl_category(id)',
		));
		$this->relationTableDropped=false;
		$this->createTable('tbl_category', array(
             'id'=>'pk',
             'name'=>'string',
		));
		ob_end_clean();
	}

	public function down()
	{
		ob_start();
		if (!$this->relationTableDropped) {
			$this->dropTable('tbl_post_category');
		}
		$this->dropTable('tbl_category');
		$this->dropTable('tbl_post');
		$this->dropTable('tbl_profile');
		$this->dropTable('tbl_user');
		ob_end_clean();
	}

	public function dropRelationTable()
	{
		ob_start();
		$this->dropTable('tbl_post_category');
		$this->relationTableDropped=true;
		ob_end_clean();
	}
}

/**
 * This is the model class for table "tbl_profile".
 *
 * @property int $owner_id
 * @property string $photo
 * @property string $website
 *
 * The followings are the available model relations:
 * @property User $owner
 */
class Profile extends \CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Profile the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_profile';
	}

	/*
	 * @return array the behavior configurations (behavior name=>behavior configuration)
	 */
	public function behaviors()
	{
		return array('activeRecordRelationBehavior'=>'EActiveRecordRelationBehavior');
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('owner_id', 'required'),
			array('photo, website', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'owner' => array(self::BELONGS_TO, TEST_NAMESPACE.'\User', 'owner_id'),
		);
	}
}

/**
 * This is the model class for table "tbl_user".
 *
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $email
 *
 * The followings are the available model relations:
 * @property Profile $profile
 * @property Post[] $posts
 */
class User extends \CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_user';
	}

	/*
	 * @return array the behavior configurations (behavior name=>behavior configuration)
	 */
	public function behaviors()
	{
		return array('activeRecordRelationBehavior'=>'EActiveRecordRelationBehavior');
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
//			array('id', 'required'),
			array('username, password, email', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'posts' => array(self::HAS_MANY, TEST_NAMESPACE.'\Post', 'author_id'),
			'profile' => array(self::HAS_ONE, TEST_NAMESPACE.'\Profile', 'owner_id'),
		);
	}
}

/**
 * This is the model class for table "tbl_post".
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string $create_time
 * @property int $author_id
 *
 * The followings are the available model relations:
 * @property User $author
 * @property Category[] $categories
 */
class Post extends \CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Post the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_post';
	}

	/*
	 * @return array the behavior configurations (behavior name=>behavior configuration)
	 */
	public function behaviors()
	{
		return array('activeRecordRelationBehavior'=>'EActiveRecordRelationBehavior');
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
//			array('id', 'required'),
			array('title, content, create_time, author_id', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'categories' => array(self::MANY_MANY, TEST_NAMESPACE.'\Category', 'tbl_post_category(post_id, category_id)'),
			'author' => array(self::BELONGS_TO, TEST_NAMESPACE.'\User', 'author_id'),
		);
	}
}

/**
 * This is the model class for table "tbl_category".
 *
 * @property int $id
 * @property string $name
 *
 * The followings are the available model relations:
 * @property Post[] $posts
 */
class Category extends \CActiveRecord
{
	public $broken = false;
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Category the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_category';
	}

	/*
	 * @return array the behavior configurations (behavior name=>behavior configuration)
	 */
	public function behaviors()
	{
		return array('activeRecordRelationBehavior'=>'EActiveRecordRelationBehavior');
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
//			array('id', 'required'),
			array('name', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		if (!$this->broken) {
			return array(
				'posts' => array(self::MANY_MANY, TEST_NAMESPACE.'\Post', 'tbl_post_category(category_id, post_id)'),
			);
		}
		return array(
			'posts' => array(self::MANY_MANY, TEST_NAMESPACE.'\Post', 'tbl_post_category'),
		);
	}
}
