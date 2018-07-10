<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\ScormBundle\Listener;

use Claroline\AppBundle\API\SerializerProvider;
use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Event\Resource\CopyResourceEvent;
use Claroline\CoreBundle\Event\Resource\DeleteResourceEvent;
use Claroline\CoreBundle\Event\Resource\DownloadResourceEvent;
use Claroline\CoreBundle\Event\Resource\LoadResourceEvent;
use Claroline\CoreBundle\Event\Resource\OpenResourceEvent;
use Claroline\CoreBundle\Manager\Resource\ResourceEvaluationManager;
use Claroline\ScormBundle\Entity\Sco;
use Claroline\ScormBundle\Entity\Scorm;
use Claroline\ScormBundle\Manager\ScormManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @DI\Service
 */
class ScormListener
{
    /** @var string */
    private $filesDir;
    /** @var ObjectManager */
    private $om;
    /** @var ResourceEvaluationManager */
    private $resourceEvalManager;
    /** @var ScormManager */
    private $scormManager;
    /** @var SerializerProvider */
    private $serializer;
    /** @var TwigEngine */
    private $templating;
    /** @var TokenStorageInterface */
    private $tokenStorage;
    /** @var string */
    private $uploadDir;

    private $scormResourcesPath;

    private $scoTrackingRepo;

    /**
     * @DI\InjectParams({
     *     "filesDir"            = @DI\Inject("%claroline.param.files_directory%"),
     *     "om"                  = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceEvalManager" = @DI\Inject("claroline.manager.resource_evaluation_manager"),
     *     "scormManager"        = @DI\Inject("claroline.manager.scorm_manager"),
     *     "serializer"          = @DI\Inject("claroline.api.serializer"),
     *     "templating"          = @DI\Inject("templating"),
     *     "tokenStorage"        = @DI\Inject("security.token_storage"),
     *     "uploadDir"           = @DI\Inject("%claroline.param.uploads_directory%")
     * })
     *
     * @param string                    $filesDir
     * @param ObjectManager             $om
     * @param ScormManager              $scormManager
     * @param SerializerProvider        $serializer
     * @param TwigEngine                $templating
     * @param TokenStorageInterface     $tokenStorage
     * @param ResourceEvaluationManager $resourceEvalManager
     * @param string                    $uploadDir
     */
    public function __construct(
        $filesDir,
        ObjectManager $om,
        ResourceEvaluationManager $resourceEvalManager,
        ScormManager $scormManager,
        SerializerProvider $serializer,
        TwigEngine $templating,
        TokenStorageInterface $tokenStorage,
        $uploadDir
    ) {
        $this->filesDir = $filesDir;
        $this->om = $om;
        $this->resourceEvalManager = $resourceEvalManager;
        $this->scormManager = $scormManager;
        $this->serializer = $serializer;
        $this->templating = $templating;
        $this->tokenStorage = $tokenStorage;
        $this->uploadDir = $uploadDir;

        $this->scormResourcesPath = $uploadDir.DIRECTORY_SEPARATOR.'scorm'.DIRECTORY_SEPARATOR;

        $this->scoTrackingRepo = $om->getRepository('ClarolineScormBundle:ScoTracking');
    }

    /**
     * @DI\Observe("open_claroline_scorm")
     *
     * @param OpenResourceEvent $event
     */
    public function onOpen(OpenResourceEvent $event)
    {
        $scorm = $event->getResource();
        $user = $this->tokenStorage->getToken()->getUser();

        if ('anon.' === $user) {
            $user = null;
        }
        $content = $this->templating->render(
            'ClarolineScormBundle::scorm.html.twig', [
                '_resource' => $scorm,
                'userEvaluation' => is_null($user) ?
                    null :
                    $this->resourceEvalManager->getResourceUserEvaluation($scorm->getResourceNode(), $user),
                'trackings' => $this->scormManager->generateScosTrackings($scorm->getRootScos(), $user),
            ]
        );

        $event->setResponse(new Response($content));
        $event->stopPropagation();
    }

    /**
     * @DI\Observe("load_claroline_scorm")
     *
     * @param LoadResourceEvent $event
     */
    public function onLoad(LoadResourceEvent $event)
    {
        $scorm = $event->getResource();
        $user = $this->tokenStorage->getToken()->getUser();

        if ('anon.' === $user) {
            $user = null;
        }
        $event->setAdditionalData([
            'scorm' => $this->serializer->serialize($scorm),
            'evaluation' => is_null($user) ?
                null :
                $this->serializer->serialize(
                    $this->resourceEvalManager->getResourceUserEvaluation($scorm->getResourceNode(), $user)
                ),
            'trackings' => $this->scormManager->generateScosTrackings($scorm->getRootScos(), $user),
        ]);
        $event->stopPropagation();
    }

    /**
     * @DI\Observe("delete_claroline_scorm")
     *
     * @param DeleteResourceEvent $event
     */
    public function onDelete(DeleteResourceEvent $event)
    {
        $ds = DIRECTORY_SEPARATOR;
        $scorm = $event->getResource();
        $workspace = $scorm->getResourceNode()->getWorkspace();
        $hashName = $scorm->getHashName();
        $scormArchiveFile = $this->filesDir.$ds.'scorm'.$ds.$workspace->getUuid().$ds.$hashName;
        $scormResourcesPath = $this->scormResourcesPath.$hashName;

        $nbScorm = (int) ($this->scormResourceRepo->getNbScormWithHashName($hashName));

        if (1 === $nbScorm) {
            if (file_exists($scormArchiveFile)) {
                $event->setFiles([$scormArchiveFile]);
            }
            if (file_exists($scormResourcesPath)) {
                try {
                    $this->deleteFiles($scormResourcesPath);
                } catch (\Exception $e) {
                }
            }
        }
        $this->om->remove($event->getResource());
        $event->stopPropagation();
    }

    /**
     * @DI\Observe("copy_claroline_scorm")
     *
     * @param CopyResourceEvent $event
     */
    public function onCopy(CopyResourceEvent $event)
    {
        $resource = $event->getResource();
        $copy = new Scorm();
        $copy->setHashName($resource->getHashName());
        $copy->setName($resource->getName());
        $copy->setVersion($resource->getVersion());
        $copy->setRatio($resource->getRatio());
        $this->om->persist($copy);

        $scos = $resource->getScos();

        foreach ($scos as $sco) {
            if (is_null($sco->getScoParent())) {
                $this->copySco($sco, $copy);
            }
        }

        $event->setCopy($copy);
        $event->stopPropagation();
    }

    /**
     * @DI\Observe("download_claroline_scorm")
     *
     * @param DownloadResourceEvent $event
     */
    public function onDownload(DownloadResourceEvent $event)
    {
        $ds = DIRECTORY_SEPARATOR;
        $scorm = $event->getResource();
        $workspace = $scorm->getResourceNode()->getWorkspace();
        $event->setItem($this->filesDir.$ds.'scorm'.$ds.$workspace->getUuid().$ds.$scorm->getHashName());
        $event->setExtension('zip');
        $event->stopPropagation();
    }

    /**
     * Deletes recursively a directory and its content.
     *
     * @param $dirPath The path to the directory to delete
     */
    private function deleteFiles($dirPath)
    {
        foreach (glob($dirPath.DIRECTORY_SEPARATOR.'{*,.[!.]*,..?*}', GLOB_BRACE) as $content) {
            if (is_dir($content)) {
                $this->deleteFiles($content);
            } else {
                unlink($content);
            }
        }
        rmdir($dirPath);
    }

    /**
     * Copy given sco and its children.
     *
     * @param Sco   $sco
     * @param Scorm $resource
     * @param Sco   $scoParent
     */
    private function copySco(Sco $sco, Scorm $resource, Sco $scoParent = null)
    {
        $scoCopy = new Sco();
        $scoCopy->setScorm($resource);
        $scoCopy->setScoParent($scoParent);

        $scoCopy->setEntryUrl($sco->getEntryUrl());
        $scoCopy->setIdentifier($sco->getIdentifier());
        $scoCopy->setTitle($sco->getTitle());
        $scoCopy->setVisible($sco->isVisible());
        $scoCopy->setParameters($sco->getParameters());
        $scoCopy->setLaunchData($sco->getLaunchData());
        $scoCopy->setMaxTimeAllowed($sco->getMaxTimeAllowed());
        $scoCopy->setTimeLimitAction($sco->getTimeLimitAction());
        $scoCopy->setBlock($sco->isBlock());
        $scoCopy->setScoreToPassInt($sco->getScoreToPassInt());
        $scoCopy->setScoreToPassDecimal($sco->getScoreToPassDecimal());
        $scoCopy->setCompletionThreshold($sco->getCompletionThreshold());
        $scoCopy->setPrerequisites($sco->getPrerequisites());
        $this->om->persist($scoCopy);

        foreach ($sco->getScoChildren() as $scoChild) {
            $this->copySco($scoChild, $resource, $scoCopy);
        }
    }
}