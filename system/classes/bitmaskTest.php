﻿<?php

require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'phpunit_bootstrap.php';

class system_classes_BitmaskTest extends PHPUnit_Framework_TestCase
{

	public function setup()
	{
		$this->access_names = array( 'read', 'edit', 'delete', 'create' );
		$this->bitmask = new Bitmask($this->access_names);
	}

	public function test_constructor()
	{
		$mask = new Bitmask( array( 'dog', 'cat' ), 3 );
		$this->assertTrue( $mask->dog );
		$this->assertTrue( $mask->cat );

		$mask = new Bitmask( array( 'dog', 'cat' ), 'dog' );
		$this->assertTrue( $mask->dog );
		$this->assertFalse( $mask->cat );

		$mask = new Bitmask( array( 'dog', 'cat' ), 'full' );
		$this->assertTrue( $mask->dog );
		$this->assertTrue( $mask->cat );
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_constructor_invalid_first_argument()
	{
		$mask = new Bitmask( 'brute' );
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_constructor_invalid_second_argument()
	{
		$mask = new Bitmask( array( 'dog', 'cat' ), 'giraffe' );
	}

	public function test_write_by_name()
	{
		$this->bitmask->read = true;
		$this->assertEquals(1, $this->bitmask->value);

		$this->bitmask->edit = true;
		$this->assertEquals(3, $this->bitmask->value);

		$this->bitmask->delete = true;
		$this->assertEquals(7, $this->bitmask->value);

		$this->bitmask->create = true;
		$this->assertEquals(15, $this->bitmask->value);

		$this->bitmask->read = false;
		$this->assertEquals(14, $this->bitmask->value);

		$this->bitmask->edit = false;
		$this->assertEquals(12, $this->bitmask->value);

		$this->bitmask->delete = false;
		$this->assertEquals(8, $this->bitmask->value);
	}

	public function test_write_by_value()
	{
		$this->bitmask->value = 1;
		$this->assertTrue($this->bitmask->read, 'Read bit should be true and is not.');
		$this->assertFalse($this->bitmask->edit, 'Edit bit should be false and is not.');

		$this->bitmask->value = 2;
		$this->assertTrue($this->bitmask->edit);
		$this->assertFalse($this->bitmask->delete);

		$this->bitmask->value = 4;
		$this->assertTrue($this->bitmask->delete);
		$this->assertFalse($this->bitmask->create);

		$this->bitmask->value = 8;
		$this->assertTrue($this->bitmask->create);
		$this->assertFalse($this->bitmask->read);

		$this->bitmask->value = 14;
		$this->assertTrue($this->bitmask->create);
		$this->assertFalse($this->bitmask->read);
		$this->assertTrue($this->bitmask->delete);
		$this->assertTrue($this->bitmask->edit);

		$this->bitmask->full = true;
		$this->assertEquals( 15, $this->bitmask->value );
		$this->bitmask->full = false;
		$this->assertEquals( 0, $this->bitmask->value );
	}

	public function test_write_by_array()
	{
		// TODO Bitmask should support this but the current implementation uses a public variable called value rather than the value setter.
		$mask = array(true, false, false, true);
		$this->bitmask->value = $mask;

		$this->assertEquals(9, $this->bitmask->value);
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_write_nonexistent()
	{
		$this->bitmask->bogus = true;
	}

	public function test__tostring()
	{
		$this->bitmask->value = 1;
		$this->assertEquals('read', (string)$this->bitmask);

		$this->bitmask->value = 0;
		$this->assertEquals( 'none', (string)$this->bitmask );

		$this->bitmask->value = 15;
		$this->assertEquals( 'full', (string)$this->bitmask );
	}

	/**
	 * Ported from old test suite
	 */
	function test_bitmask()
	{
		define('POST_FLAG_ALLOWS_COMMENTS'  ,1);
		define('POST_FLAG_ALLOWS_TRACKBACKS',1 << 1);
		define('POST_FLAG_ALLOWS_PINGBACKS' ,1 << 2);

//		$flags= array(
//			'allows_comments'=>POST_FLAG_ALLOWS_COMMENTS,
//			'allows_trackbacks'=>POST_FLAG_ALLOWS_TRACKBACKS,
//			'allows_pingbacks'=>POST_FLAG_ALLOWS_PINGBACKS
//		);
		$flags= array(
			'allows_comments',
			'allows_trackbacks',
			'allows_pingbacks'
		);

		$bitmask= new Bitmask($flags);

		$bitmask->allows_comments   = true;
		$bitmask->allows_trackbacks = false;
		$bitmask->allows_pingbacks  = true;

		$this->assertTrue($bitmask->allows_comments);
		$this->assertFalse($bitmask->allows_trackbacks);
		$this->assertTrue($bitmask->allows_pingbacks);
	}
}
?>
