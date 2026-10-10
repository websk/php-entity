<?php

namespace WebSK\Entity\Tests;

use PHPUnit\Framework\TestCase;
use WebSK\Entity\EntityConfig;
use WebSK\Entity\Tests\Support\FakeDbService;
use WebSK\Entity\Tests\Support\TestEntity;
use WebSK\Entity\Tests\Support\TestRepository;
use WebSK\Entity\WeightRepository;

final class EntityRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        EntityConfig::setIgnoreMissingPropertiesOnLoad(false);
        EntityConfig::setIgnoreMissingPropertiesOnSave(false);
    }

    public function testFindByIdHydratesTypedEntity(): void
    {
        $db = new FakeDbService();
        $db->object_result = (object)[
            'id' => '7',
            'created_at_ts' => '100',
            'name' => 'Example',
            'note' => null,
            'weight' => '3',
        ];
        $repository = new TestRepository(TestEntity::class, $db);

        $entity = $repository->findById(7);

        self::assertInstanceOf(TestEntity::class, $entity);
        self::assertSame(7, $entity->getId());
        self::assertSame(100, $entity->getCreatedAtTs());
        self::assertSame('Example', $entity->getName());
        self::assertSame(3, $entity->getWeight());
    }

    public function testFindByIdReturnsNullWhenRowIsMissing(): void
    {
        $repository = new TestRepository(TestEntity::class, new FakeDbService());

        self::assertNull($repository->findById(99));
    }

    public function testMissingPropertyOnLoadCanBeRejectedOrIgnored(): void
    {
        $db = new FakeDbService();
        $db->object_result = (object)['id' => 1, 'legacy' => 'value'];
        $repository = new TestRepository(TestEntity::class, $db);

        try {
            $repository->findById(1);
            self::fail('Missing property was not rejected');
        } catch (\UnexpectedValueException $exception) {
            self::assertStringContainsString('legacy', $exception->getMessage());
        }

        EntityConfig::setIgnoreMissingPropertiesOnLoad(true);
        self::assertInstanceOf(TestEntity::class, $repository->findById(1));
    }

    public function testSaveInsertsEntityAndAssignsIntegerId(): void
    {
        $db = new FakeDbService();
        $db->objects_result = self::tableFields('id', 'created_at_ts', 'name', 'note', 'weight');
        $db->last_insert_id = '42';
        $repository = new TestRepository(TestEntity::class, $db);
        $entity = new TestEntity();
        $entity->setName('Created');

        $repository->save($entity);

        self::assertSame(42, $entity->getId());
        self::assertCount(1, $db->queries);
        self::assertStringStartsWith('INSERT INTO test_entities', $db->queries[0]['sql']);
        self::assertContains('Created', $db->queries[0]['params']);
    }

    public function testSaveUpdatesExistingEntity(): void
    {
        $db = new FakeDbService();
        $db->objects_result = self::tableFields('id', 'created_at_ts', 'name', 'note', 'weight');
        $repository = new TestRepository(TestEntity::class, $db);
        $entity = new TestEntity();
        $entity->setIdForTest(5);
        $entity->setName('Updated');

        $repository->save($entity);

        self::assertCount(1, $db->queries);
        self::assertStringStartsWith('UPDATE test_entities SET', $db->queries[0]['sql']);
        self::assertSame(5, $db->queries[0]['params'][4]);
    }

    public function testMissingPropertyOnSaveCanBeRejectedOrIgnored(): void
    {
        $db = new FakeDbService();
        $db->objects_result = self::tableFields('id', 'created_at_ts', 'legacy');
        $db->last_insert_id = '8';
        $repository = new TestRepository(TestEntity::class, $db);

        try {
            $repository->save(new TestEntity());
            self::fail('Missing property was not rejected');
        } catch (\UnexpectedValueException $exception) {
            self::assertStringContainsString('legacy', $exception->getMessage());
        }

        EntityConfig::setIgnoreMissingPropertiesOnSave(true);
        $entity = new TestEntity();
        $repository->save($entity);
        self::assertSame(8, $entity->getId());
    }

    public function testDeleteRejectsUnsavedEntityAndDeletesSavedEntity(): void
    {
        $db = new FakeDbService();
        $repository = new TestRepository(TestEntity::class, $db);

        try {
            $repository->delete(new TestEntity());
            self::fail('Unsaved entity was deleted');
        } catch (\LogicException) {
            self::assertSame([], $db->queries);
        }

        $entity = new TestEntity();
        $entity->setIdForTest(9);
        $repository->delete($entity);
        $query = $db->lastQuery();
        self::assertSame([9], $query['params']);
    }

    public function testIdListsAreNormalizedAndInvalidValuesAreRejected(): void
    {
        $db = new FakeDbService();
        $db->column_result = ['1', 2];
        $repository = new TestRepository(TestEntity::class, $db);
        self::assertSame([1, 2], $repository->getAllIdsArrByIdAsc());

        $db->column_result = ['invalid'];
        $this->expectException(\UnexpectedValueException::class);
        $repository->getAllIdsArrByIdAsc();
    }

    public function testWeightRepositoryNormalizesDatabaseValues(): void
    {
        $db = new FakeDbService();
        $repository = new WeightRepository(TestEntity::class, $db);

        $db->field_result = '12';
        self::assertSame(12, $repository->getMaxWeightForContext(['section_id' => 1]));
        self::assertStringContainsString('section_id=?', $db->queries[0]['sql']);

        $db->field_result = '4';
        self::assertSame(4, $repository->getObjectToSwapWeightsId(5));
    }

    /** @return list<object> */
    private static function tableFields(string ...$names): array
    {
        return array_values(array_map(static fn(string $name): object => (object)['Field' => $name], $names));
    }
}
