<?php

namespace WebSK\Entity\Tests;

use PHPUnit\Framework\TestCase;
use WebSK\Entity\Tests\Support\FakeDbService;
use WebSK\Entity\Tests\Support\TestEntity;
use WebSK\Entity\Tests\Support\TestWeightRepository;
use WebSK\Entity\Tests\Support\TestWeightService;
use function WebSK\Entity\Tests\Support\createCacheService;

final class WeightServiceTest extends TestCase
{
    public function testInitWeightAssignsNextWeightOnlyToNewUnweightedEntity(): void
    {
        $repository = new TestWeightRepository(TestEntity::class, new FakeDbService());
        $repository->max_weight = 4;
        $service = new TestWeightService(TestEntity::class, $repository, createCacheService($engine));
        $entity = new TestEntity();

        $service->initWeight($entity, ['section_id' => 1]);
        self::assertSame(5, $entity->getWeight());

        $entity->setWeight(9);
        $service->initWeight($entity, []);
        self::assertSame(9, $entity->getWeight());
    }

    public function testSwapWeightsUpdatesAndSavesBothEntities(): void
    {
        $repository = new TestWeightRepository(TestEntity::class, new FakeDbService());
        $repository->swap_id = 2;
        $current = new TestEntity();
        $current->setIdForTest(1);
        $current->setWeight(20);
        $other = new TestEntity();
        $other->setIdForTest(2);
        $other->setWeight(10);
        $repository->entities = [1 => $current, 2 => $other];
        $service = new TestWeightService(TestEntity::class, $repository, createCacheService($engine));

        $service->swapWeights($current);

        self::assertSame(10, $current->getWeight());
        self::assertSame(20, $other->getWeight());
        self::assertSame([$current, $other], $repository->saved);
    }

    public function testSwapWeightsRejectsLoadedEntityWithoutWeightContract(): void
    {
        $repository = new TestWeightRepository(TestEntity::class, new FakeDbService());
        $repository->swap_id = 2;
        $current = new TestEntity();
        $current->setIdForTest(1);
        $repository->entities[2] = new class implements \WebSK\Entity\InterfaceEntity {
            public function getId(): int
            {
                return 2;
            }
        };
        $service = new TestWeightService(TestEntity::class, $repository, createCacheService($engine));

        $this->expectException(\UnexpectedValueException::class);
        $service->swapWeights($current);
    }
}
