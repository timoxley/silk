<?php // -*- mode:php; tab-width:4; indent-tabs-mode:t; c-basic-offset:4; -*-

class SilkObjectRelationalMappingTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		SilkCache::clear();
		@SilkDatabase::drop_table('test_orm_table');
		$db_prefix = db_prefix();
		db()->Execute("DELETE FROM {$db_prefix}serialized_versions");
		SilkDatabase::create_table('test_orm_table', "
			id I KEY AUTO,
			test_field C(255),
			another_test_field C(255),
			some_int I,
			some_float F,
			version I,
			create_date T,
			modified_date T
		");
		db()->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, another_test_field, some_int, some_float, create_date, modified_date) VALUES ('test', 'blah', 5, 5.501, now() - 10, now() - 10)");
		db()->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, create_date, modified_date) VALUES ('test2', now(), now())");
		db()->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, create_date, modified_date) VALUES ('test3', now(), now())");
		
		@SilkDatabase::drop_table('test_orm_table_child');
		SilkDatabase::create_table('test_orm_table_child', "
			id I KEY AUTO,
			parent_id I,
			some_other_field C(255),
			version I,
			create_date T,
			modified_date T
		");
		
		db()->Execute("INSERT INTO {$db_prefix}test_orm_table_child (parent_id, some_other_field, create_date, modified_date) VALUES (1, 'test', now(), now())");
	}
	
	public function tearDown()
	{
		SilkDatabase::drop_table('test_orm_table_child');
		SilkDatabase::drop_table('test_orm_table');
		SilkCache::clear();
	}
	
	public function testGetTableShouldKnowFancyPrefixStuff()
	{
		$this->assertEquals(SilkDatabase::get_prefix() . 'test_orm_table', orm('test_orm_table')->get_table());
		$this->assertEquals(SilkDatabase::get_prefix() . 'test_orm_table.test_field', orm('test_orm_table')->get_table('test_field'));
	}
	
	public function testGetColumnsInTableShouldWork()
	{
		$result = orm('test_orm_table')->get_columns_in_table();
		$this->assertEquals(8, count($result));
		$this->assertEquals('int', $result['id']->type);
		$this->assertEquals('varchar', $result['test_field']->type);
		$this->assertEquals('datetime', $result['create_date']->type);
	}
	
	public function testFindAllShouldReturnAllRows()
	{
		$result = orm('test_orm_table')->find_all();
		$this->assertType('array', $result);
		$this->assertEquals(3, count($result));
	}
	
	public function testFindOneShouldReturnOneRow()
	{
		$result = orm('test_orm_table')->find();
		$this->assertType('TestOrmTable', $result);
		$this->assertEquals(1, count($result));
	}
	
	public function testFindCountShouldReturnACountDuh()
	{
		$result = orm('test_orm_table')->find_count();
		$this->assertEquals(3, $result);
	}
	
	public function testDateTimeShouldBeASilkDateTimeObject()
	{
		$result = orm('test_orm_table')->find();
		$this->assertNotType('SilkDateTime', $result->test_field);
		$this->assertType('SilkDateTime', $result->create_date);
		$this->assertType('SilkDateTime', $result->modified_date);
	}
	
	public function testOtherFieldsShouldBeString()
	{
		$result = orm('test_orm_table')->find();
		$this->assertType('string', $result->test_field);
	}
	
	public function testAutoNumberingShouldWork()
	{
		$result = orm('test_orm_table')->find();
		$this->assertEquals(1, $result->id);
	}
	
	public function testArrayAccessorsShouldWork()
	{
		$result = orm('test_orm_table')->find();
		$this->assertEquals(1, $result->id);
		$this->assertEquals(1, $result['id']);
	}
	
	public function testDynamicFindersShouldRawk()
	{
		$result = orm('test_orm_table')->find_all_by_test_field('test2');
		$this->assertEquals(1, count($result));
		$result = orm('test_orm_table')->find_all_by_test_field_or_another_test_field('test2', 'blah');
		$this->assertEquals(2, count($result));
		$result = orm('test_orm_table')->find_all_by_test_field_and_another_test_field('test', 'blah');
		$this->assertEquals(1, count($result));
		$result = orm('test_orm_table')->find_all_by_test_field_and_another_test_field('test2', 'blah');
		$this->assertEquals(0, count($result));
		$result = orm('test_orm_table')->find_by_test_field('test2');
		$this->assertEquals(1, count($result));
		$result = orm('test_orm_table')->find_count_by_test_field('test2');
		$this->assertEquals(1, $result);
		$result = orm('test_orm_table')->find_count_by_test_field_or_another_test_field('test2', 'blah');
		$this->assertEquals(2, $result);
		$result = orm('test_orm_table')->find_count_by_test_field_and_another_test_field('test', 'blah');
		$this->assertEquals(1, $result);
		$result = orm('test_orm_table')->find_count_by_test_field_and_another_test_field('test2', 'blah');
		$this->assertEquals(0, $result);
	}
	
	public function testFindByQueryShouldRawkAsWellJustNotQuiteAsHard()
	{
		$db_prefix = db_prefix();
		$result = orm('test_orm_table')->find_all_by_query("SELECT * FROM {$db_prefix}test_orm_table ORDER BY id ASC");
		$this->assertEquals(3, count($result));
		$result = orm('test_orm_table')->find_all_by_query("SELECT * FROM {$db_prefix}test_orm_table WHERE test_field = ? ORDER BY id ASC", array('test'));
		$this->assertEquals(1, count($result));
	}
	
	public function testSaveShouldWorkAndBumpTimestampAndTheDirtyFlagShouldWork()
	{
		#Once without a change
		$result = orm('test_orm_table')->find();
		$old_timestamp = $result->modified_date->timestamp();
		$result->save();
		$result = orm('test_orm_table')->find();
		$this->assertEquals($old_timestamp, $result->modified_date->timestamp());
		
		#Once with
		$old_timestamp = $result->modified_date->timestamp();
		$result->test_field = 'test10';
		$result->save();
		$result = orm('test_orm_table')->find();
		$this->assertNotEquals($old_timestamp, $result->modified_date->timestamp());
		$this->assertEquals('test10', $result->test_field);
	}
	
	public function testHasParameterDoesItsThing()
	{
		$result = orm('test_orm_table')->find();
		$this->assertTrue($result->has_parameter('test_field'));
		$this->assertTrue($result->has_parameter('another_test_field'));
		$this->assertTrue($result->has_parameter('create_date'));
		$this->assertTrue($result->has_parameter('modified_date'));
		$this->assertFalse($result->has_parameter('i_made_this_up'));
	}
	
	public function testValidatorWillNotAllowSaves()
	{
		$result = orm('test_orm_table')->find();
		$result->test_field = '';
		$result->another_test_field = '';
		$this->assertFalse($result->save());
		$result->test_field = 'test';
		$this->assertFalse($result->save());
		$result->another_test_field = 'blah';
		$this->assertTrue($result->save());
	}
	
	public function testNumericalityOfValidatorShouldActuallyWork()
	{
		$result = orm('test_orm_table')->find();
		$result->some_int = '';  #We're testing numbers, not empty strings -- do another validation
		$this->assertTrue($result->save());
		$result->some_int = '5';
		$this->assertTrue($result->save());
		$result->some_int = 5;
		$this->assertTrue($result->save());
		$result->some_float = 'sdfsdfsdfsfd';
		$this->assertFalse($result->save());
		$result->some_float = '5.501';
		$this->assertTrue($result->save());
		$result->some_float = 5.501;
		$this->assertTrue($result->save());
	}
	
	public function testHasManyShouldWork()
	{
		$result = orm('test_orm_table')->find_by_id(1);
		$this->assertNotNull($result);
		$this->assertEquals(1, count($result->children));
		$this->assertEquals('test', $result->children[0]->some_other_field);
	}
	
	public function testBelongsToShouldWorkAsWell()
	{
		$result = orm('test_orm_table')->find_by_id(1);
		$this->assertNotNull($result);
		$this->assertEquals(1, count($result->children));
		$this->assertNotNull(count($result->children[0]->parent));
		$this->assertEquals(1, $result->children[0]->parent->id);
	}
	
	public function testDeleteShouldActuallyDelete()
	{
		$result = orm('test_orm_table')->find_by_id(1);
		$this->assertNotNull($result);
		$result->delete();
		$result = orm('test_orm_table')->find_all();
		$this->assertEquals(2, count($result));
	}
	
	public function testLoadCallbacksShouldGetCalled()
	{
		$result = orm('test_orm_table')->find();
		$this->assertNotNull($result);
		$this->assertEquals(1, $result->counter);
	}
	
	public function testSaveCallbacksShouldGetCalled()
	{
		$result = orm('test_orm_table')->find();
		$this->assertNotNull($result);
		$this->assertEquals(1, $result->counter);
		
		#First no updates -- before gets called, after doesn't
		$result->save();
		$this->assertEquals(2, $result->counter);
		
		#Now with updates -- before and after get called
		$result->test_field = 'test10';
		$result->save();
		$this->assertEquals(5, $result->counter);
	}
	
	public function testDeleteCallbacksShouldGetCalled()
	{
		$result = orm('test_orm_table')->find();
		$this->assertNotNull($result);
		$this->assertEquals(1, $result->counter);
		
		$result->delete();
		$this->assertEquals(4, $result->counter);
		
		$result = orm('test_orm_table')->find_all();
		$this->assertEquals(2, count($result));
	}
	
	/*
	public function testBadTransactionsShouldFail()
	{
		$db_prefix = db_prefix();
		$db = db();
		orm('test_orm_table')->begin_transaction();
		$db->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, another_test_field, some_int, some_float, create_date, modified_date) VALUES ('test good', 'blah', 5, 5.501, now() - 10, now() - 10)"); //Good SQL
		try {
			@$db->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, another_test_field, some_int, some_float, create_date, modified_date) VALUES ('test bad', 'blah', 5, 5.501, now() - 10, now() - 10, 'kjk')"); //Bad SQL
		}
		catch (Exception $e) {}
		$this->assertFalse(orm('test_orm_table')->complete_transaction());
		$this->assertNull(orm('test_orm_table')->find_by_test_field('test good'));
	}
	*/
	
	public function testGoodTransactionsShouldWork()
	{
		$db_prefix = db_prefix();
		$db = db();
		orm('test_orm_table')->begin_transaction();
		$db->Execute("INSERT INTO {$db_prefix}test_orm_table (test_field, another_test_field, some_int, some_float, create_date, modified_date) VALUES ('test good', 'blah', 5, 5.501, now() - 10, now() - 10)"); //Good SQL
		$this->assertTrue(orm('test_orm_table')->complete_transaction());
		$this->assertNotNull(orm('test_orm_table')->find_by_test_field('test good'));
	}
	
	/*
	public function testVersioningShouldWorkCorrectly()
	{
		$obj = orm('test_orm_table')->find_by_test_field('test');
		$this->assertNotNull($obj);
		$obj->another_test_field = 'blah';
		$obj->save();
		$obj->another_test_field = 'blah';
		$obj->save();
		$obj->another_test_field = 'blah';
		$obj->save();
		$this->assertEquals(count($obj->get_versions()), 2);
		$this->assertEquals($obj->version, 3);
	}
	*/
}

class TestOrmTable extends SilkObjectRelationalMapping
{
	var $counter = 0;

	public function setup()
	{
		$this->create_has_many_association('children', 'TestOrmTableChild', 'parent_id');
		//$this->assign_acts_as('Versioned');
	}

	public function validate()
	{
		$this->validate_not_blank('test_field');
		if (strlen($this->another_test_field) == 0)
		{
			$this->add_validation_error('can\'t be blank');
		}
		$this->validate_numericality_of('some_int');
		$this->validate_numericality_of('some_float');
	}
	
	public function after_load()
	{
		$this->counter++;
	}
	
	public function before_save()
	{
		$this->counter++;
	}
	
	public function after_save()
	{
		$this->counter++;
		$this->counter++;
	}
	
	public function before_delete()
	{
		$this->counter++;
	}
	
	public function after_delete()
	{
		$this->counter++;
		$this->counter++;
	}
}

class TestOrmTableChild extends SilkObjectRelationalMapping
{	
	public function setup()
	{
		$this->create_belongs_to_association('parent', 'test_orm_table', 'parent_id');
	}
}

# vim:ts=4 sw=4 noet
?>