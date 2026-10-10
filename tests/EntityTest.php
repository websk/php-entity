<?php

namespace WebSK\Entity\Tests;

use PHPUnit\Framework\TestCase;
use WebSK\Entity\EntityRepository;
use WebSK\Entity\EntityService;
use WebSK\Entity\Tests\Support\FakeDbService;
use WebSK\Entity\Tests\Support\InvalidTableEntity;
use WebSK\Entity\Tests\Support\TestEntity;
use WebSK\Entity\Tests\Support\TestRepository;

final class EntityTest extends TestCase
{
    public function testEntityDefaultsAndWeightAccessors(): void
    {
        $entity = new TestEntity();

        self::assertNull($entity->getId());
        self::assertGreaterThan(0, $entity->getCreatedAtTs());
        self::assertSame(0, $entity->getWeight());

        $entity->setCreatedAtTs(100);
        $entity->setWeight(7);

        self::assertSame(100, $entity->getCreatedAtTs());
        self::assertSame(7, $entity->getWeight());
    }

    public function testUnknownPropertyReadIsRejected(): void
    {
        $entity = new TestEntity();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Accessing invalid property missing');

        $entity->__get('missing');
    }

    public function testUnknownPropertyWriteIsRejected(): void
    {
        $entity = new TestEntity();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Accessing invalid property missing');

        $entity->__set('missing', 'value');
    }

    public function testContainerIdsUseConfiguredConstants(): void
    {
        self::assertSame('test.repository', EntityRepository::getContainerIdByClassName(TestEntity::class));
        self::assertSame('test.service', EntityService::getContainerIdByClassName(TestEntity::class));
    }

    public function testRepositoryRejectsClassThatDoesNotImplementEntityContract(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $repository_class = TestRepository::class;
        $reflection = new \ReflectionClass($repository_class);
        $reflection->newInstance(\stdClass::class, new FakeDbService());
    }

    public function testRepositoryRejectsInvalidTableConstant(): void
    {
        $repository = new TestRepository(InvalidTableEntity::class, new FakeDbService());

        $this->expectException(\UnexpectedValueException::class);
        $repository->getTableName();
    }
}
