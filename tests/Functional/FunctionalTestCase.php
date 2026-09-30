<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\FakeOptimizerTools;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Images;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Media;
use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots the small test application and gives every test an empty database.
 *
 * The schema is built from the entity metadata rather than from a migration: the bundle ships no
 * migrations, because the project that installs it generates its own.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('eekes_test.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        Images::cleanUp();

        parent::tearDown();
    }

    protected function repository(): ImageOptimizationRepository
    {
        $repository = self::getContainer()->get('eekes_test.repository');
        \assert($repository instanceof ImageOptimizationRepository);

        return $repository;
    }

    protected function recorder(): OptimizationRecorder
    {
        $recorder = self::getContainer()->get('eekes_test.recorder');
        \assert($recorder instanceof OptimizationRecorder);

        return $recorder;
    }

    protected function tools(): FakeOptimizerTools
    {
        $tools = self::getContainer()->get(FakeOptimizerTools::class);
        \assert($tools instanceof FakeOptimizerTools);

        return $tools;
    }

    protected function log(): TestHandler
    {
        $handler = self::getContainer()->get('eekes_test.log');
        \assert($handler instanceof TestHandler);

        return $handler;
    }

    protected function media(): Media
    {
        $media = new Media();
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    /**
     * @return list<ImageOptimization>
     */
    protected function logged(): array
    {
        $this->entityManager->clear();

        return $this->entityManager
            ->getRepository(ImageOptimization::class)
            ->findBy([], ['id' => 'ASC']);
    }
}
