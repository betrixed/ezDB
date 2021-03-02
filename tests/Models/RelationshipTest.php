<?php

namespace TS\ezDB\Tests\Models;

use TS\ezDB\Connections;
use TS\ezDB\DatabaseConfig;
use TS\ezDB\Tests\Data\Test2Model;
use TS\ezDB\Tests\Data\TestModel;
use TS\ezDB\Tests\Data\TestRelatedModel;

class RelationshipTest extends \TS\ezDB\Tests\TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Connections::addConnection(new DatabaseConfig(self::$dbConfig['mysqli']), 'TestModelConnection');
        Connections::addConnection(new DatabaseConfig(self::$dbConfig['pdo']), 'TestRelatedModelConnection');
        Connections::connection('TestModelConnection')->getDriver()->exec(self::$dummyData);
    }

    public function testGetSetRelation()
    {
        $m = new TestModel();
        $m->setRelation('test', ['name' => 'ezDB']);

        $relation = $m->getRelations();

        $this->assertCount(1, $relation);
        $this->assertEquals('test', array_key_first($relation));
    }

    public function testHasOne()
    {
        $test = TestModel::find(1);

        $results = $test->hasOne(Test2Model::class)->get();

        $this->assertInstanceOf(Test2Model::class, $results);
    }

    public function testHasMany()
    {
        $test = TestModel::find(1);

        $results = $test->hasMany(Test2Model::class)->get();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(Test2Model::class, $results[0]);
    }

    public function testBelongsTo()
    {
        $test2 = Test2Model::find(1);

        $results = $test2->belongsTo(TestModel::class)->get();
        $this->assertInstanceOf(TestModel::class, $results);
    }

    public function testBelongsToMany()
    {
        $test = TestModel::find(1);

        $results = $test->belongsToMany(TestRelatedModel::class, 'test_intermediate')->get();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(TestRelatedModel::class, $results[0]);
        $this->assertTrue(isset($results[0]->pivot));
    }

    public function testAutoRelation()
    {
        $test = TestModel::find(1);

        $this->assertCount(0, $test->getRelations());

        $test->test2;

        $this->assertCount(1, $test->getRelations());
        $this->assertEquals('test2', array_key_first($test->getRelations()));
    }

    /**
     * @inheritDoc
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        Connections::connection('TestModelConnection')->getDriver()
            ->exec("TRUNCATE TABLE `test`; TRUNCATE TABLE `test_intermediate`; TRUNCATE TABLE `test_related`;");
    }
}