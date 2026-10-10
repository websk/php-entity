<?php

namespace WebSK\Entity\Tests;

use PHPUnit\Framework\TestCase;
use WebSK\Entity\Tests\Support\ArrayCacheEngine;
use WebSK\Entity\Tests\Support\FakeDbService;
use WebSK\Entity\Tests\Support\StubRepository;
use WebSK\Entity\Tests\Support\TestEntity;
use WebSK\Entity\Tests\Support\TestService;
use function WebSK\Entity\Tests\Support\createCacheService;

final class EntityServiceTest extends TestCase
{
    public function testGetByIdUsesRepositoryAndThenCache(): void
    {
        $repository = $this->createRepository();
        $entity = new TestEntity();
        $entity->setIdForTest(1);
        $repository->entities[1] = $entity;
        $cache = createCacheService($engine);
        $service = new TestService(TestEntity::class, $repository, $cache);

        $loaded_from_repository = $service->getById(1);
        self::assertNotNull($loaded_from_repository);
        self::assertSame(1, $loaded_from_repository->getId());
        unset($repository->entities[1]);
        $loaded_from_cache = $service->getById(1);
        self::assertNotNull($loaded_from_cache);
        self::assertSame(1, $loaded_from_cache->getId());
        self::assertArrayHasKey(TestEntity::class . '::1', $engine->values);
    }

    public function testInvalidCachedEntityIsDiscarded(): void
    {
        $repository = $this->createRepository();
        $entity = new TestEntity();
        $entity->setIdForTest(2);
        $repository->entities[2] = $entity;
        $cache = createCacheService($engine);
        $cache->set(TestEntity::class . '::2', 'corrupt');
        $service = new TestService(TestEntity::class, $repository, $cache);

        self::assertSame($entity, $service->getById(2));
        self::assertSame($entity, $engine->values[TestEntity::class . '::2']);
    }

    public function testNotFoundBehaviorIsExplicit(): void
    {
        $service = new TestService(
            TestEntity::class,
            $this->createRepository(),
            createCacheService($engine)
        );

        self::assertNull($service->getById(null));
        self::assertNull($service->getById(404, false));

        $this->expectException(\UnexpectedValueException::class);
        $service->getById(404);
    }

    public function testIdListCacheNormalizesValuesAndRecoversFromCorruption(): void
    {
        $repository = $this->createRepository();
        $repository->ids = [3, 4];
        $cache = createCacheService($engine);
        $service = new TestService(TestEntity::class, $repository, $cache);
        $key = 'all_ids_arr_' . TestEntity::class;

        $cache->set($key, ['1', 2]);
        self::assertSame([1, 2], $service->getAllIdsArrByIdAsc());

        $cache->set($key, ['invalid']);
        self::assertSame([3, 4], $service->getAllIdsArrByIdAsc());
        self::assertSame([3, 4], $engine->values[$key]);
    }

    public function testSaveOwnsAndCommitsTransaction(): void
    {
        $repository = $this->createRepository();
        $service = new TestService(TestEntity::class, $repository, createCacheService($engine));
        $entity = new TestEntity();

        $service->save($entity);

        self::assertSame(10, $entity->getId());
        self::assertSame(1, $repository->begin_count);
        self::assertSame(1, $repository->commit_count);
        self::assertSame(0, $repository->rollback_count);
        self::assertSame(1, $service->after_save_count);
    }

    public function testSaveRollsBackFailuresFromEveryHook(): void
    {
        $repository = $this->createRepository();
        $service = new TestService(TestEntity::class, $repository, createCacheService($engine));
        $service->throw_before_save = true;

        try {
            $service->save(new TestEntity());
            self::fail('beforeSave failure was not propagated');
        } catch (\RuntimeException $exception) {
            self::assertSame('beforeSave failed', $exception->getMessage());
        }
        self::assertSame(1, $repository->rollback_count);

        $service->throw_before_save = false;
        $repository->throw_on_save = true;
        try {
            $service->save(new TestEntity());
            self::fail('Repository failure was not propagated');
        } catch (\RuntimeException $exception) {
            self::assertSame('save failed', $exception->getMessage());
        }
        self::assertSame(2, $repository->rollback_count);
    }

    public function testAfterSaveFailureRollsBackAndClearsRecursionGuard(): void
    {
        $repository = $this->createRepository();
        $service = new TestService(TestEntity::class, $repository, createCacheService($engine));
        $service->throw_after_save = true;
        $entity = new TestEntity();

        try {
            $service->save($entity);
            self::fail('afterSave failure was not propagated');
        } catch (\RuntimeException $exception) {
            self::assertSame('afterSave failed', $exception->getMessage());
        }

        $service->save($entity);

        self::assertSame(2, $service->after_save_count);
        self::assertSame(1, $repository->rollback_count);
        self::assertSame(1, $repository->commit_count);
    }

    public function testDeleteRollsBackRepositoryHookAndPolicyFailures(): void
    {
        $repository = $this->createRepository();
        $service = new TestService(TestEntity::class, $repository, createCacheService($engine));
        $entity = new TestEntity();
        $entity->setIdForTest(5);

        $repository->throw_on_delete = true;
        try {
            $service->delete($entity);
            self::fail('Delete failure was not propagated');
        } catch (\RuntimeException $exception) {
            self::assertSame('delete failed', $exception->getMessage());
        }

        $repository->throw_on_delete = false;
        $service->throw_after_delete = true;
        try {
            $service->delete($entity);
            self::fail('afterDelete failure was not propagated');
        } catch (\RuntimeException $exception) {
            self::assertSame('afterDelete failed', $exception->getMessage());
        }

        $service->throw_after_delete = false;
        $service->allow_delete = false;
        try {
            $service->delete($entity);
            self::fail('Delete policy failure was not propagated');
        } catch (\LogicException $exception) {
            self::assertSame('delete denied', $exception->getMessage());
        }

        self::assertSame(3, $repository->rollback_count);
        self::assertSame(1, $repository->delete_count);
    }

    public function testUnsavedEntityCannotBeRemovedFromCache(): void
    {
        $service = new TestService(
            TestEntity::class,
            $this->createRepository(),
            createCacheService($engine)
        );

        $this->expectException(\LogicException::class);
        $service->removeFromCache(new TestEntity());
    }

    private function createRepository(): StubRepository
    {
        return new StubRepository(TestEntity::class, new FakeDbService());
    }
}
