<?php

namespace Matthewkp\EzCleanUpVersionsBundle\Command;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\UserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\API\Repository\Values\Content\Location;
use Symfony\Bridge\Monolog\Logger;

class EzCleanUpVersionsCommand extends Command
{
    /** @var \eZ\Publish\API\Repository\Repository */
    protected $repository;

    /** @var \eZ\Publish\API\Repository\LocationService */
    protected $locationService;

    /** @var \eZ\Publish\API\Repository\ContentService */
    protected $contentService;

    /** @var \eZ\Publish\API\Repository\SearchService */
    protected $searchService;

    /** @var \eZ\Publish\API\Repository\UserService */
    protected $userService;

    /** @var \Symfony\Bridge\Monolog\Logger */
    protected $logger;

    /** @var int */
    protected $rootLocationId;

    /** @var int */
    protected $adminId;

    /** @var int */
    protected $numberOfVersionsToKeep;

    public function __construct(
        Repository $repository,
        LocationService $locationService,
        ContentService $contentService,
        SearchService $searchService,
        UserService $userService,
        Logger $logger,
        $rootLocationId,
        $adminId,
        $numberOfVersionsToKeep
    )
    {
        $this->repository = $repository;
        $this->locationService = $locationService;
        $this->contentService = $contentService;
        $this->searchService = $searchService;
        $this->userService = $userService;
        $this->logger = $logger;
        $this->rootLocationId = $rootLocationId;
        $this->adminId = $adminId;
        $this->numberOfVersionsToKeep = $numberOfVersionsToKeep;

        parent::__construct();
    }

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('matthewkp:ez-clean-up-versions')
            ->setDescription('This script will remove old versions of all contents');
    }

    /**
     * Execute script
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Start removing versions from contents");

        $permissionResolver = $this->repository->getPermissionResolver();

        $adminUser = $this->userService->loadUser($this->adminId);
        $permissionResolver->setCurrentUserReference($adminUser);

        $location = $this->locationService->loadLocation($this->rootLocationId);
        $this->browseLocation($location);

        $output->writeln("End");
    }

    /**
     * Clean ups versions for a content Id given
     *
     * @param $contentId
     */
    private function cleanUpVersions($contentId)
    {
        $content = $this->contentService->loadContent($contentId);

        $contentVersions = $this->contentService->loadVersions($content->contentInfo);
        $versionsToRemove = count($contentVersions) - $this->numberOfVersionsToKeep;

        if ($versionsToRemove > $this->numberOfVersionsToKeep) {
            $i = 0;
            foreach ($contentVersions as $contentVersion) {
                if ($i < $versionsToRemove) {
                    try {
                        $this->contentService->deleteVersion($contentVersion);
                    } catch (\Exception $e) {
                        $this->logger->error('Exception threw for content Id ' .  $contentId . '. Message : ' . $e->getMessage());
                    }
                }
                $i++;
            }
        }
    }

    /**
     * This function browse location children recursively
     *
     * @param Location $location
     * @param int $depth
     */
    private function browseLocation(Location $location, $depth = 0)
    {
        $this->cleanUpVersions($location->contentId);

        $childLocations = $this->locationService->loadLocationChildren($location);
        foreach ($childLocations->locations as $childLocation) {
            $this->browseLocation($childLocation, $depth + 1);
        }
    }
}
