<?php

namespace Pumukit\Cmar\WebTVBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Pumukit\EncoderBundle\Services\JobService;
use Pumukit\EncoderBundle\Services\ProfileService;
use Pumukit\EncoderBundle\Event\JobEvent;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class WorkflowService
{
    private $targetProfile;
    private $logger;
    private $jobService;
    private $profileService;

    public function __construct($targetProfile, JobService $jobService, ProfileService $profileService, LoggerInterface $logger) 
    {
        $this->targetProfile = $targetProfile;
        $this->jobService = $jobService;
        $this->logger = $logger;
        $this->profiles = $profileService->getProfiles();
    }


    public function onJobSuccess(JobEvent $event)
    {
        $this->checkMultimediaObject($event->getMultimediaObject());
    }

    public function onMultimediaobjectUpdate(MultimediaObjectEvent $event)
    {
        $this->checkMultimediaObject($event->getMultimediaObject());
    }

    private function checkMultimediaObject(MultimediaObject $multimediaObject)
    {
        if (!isset($this->profiles[$this->targetProfile])) {
          $this->logger->warning(sprintf('CMAR WorkflowService profile "%s" doesn\'t exist', $this->targetProfile));
          return;
        }
      
        $sbs = $multimediaObject->getTrackWithTag('sbs');
        $publicTracks = $multimediaObject->getTracksWithTag('profile:' .  $this->targetProfile);

        if ($master && !$publicTracks) {
            $this->logger->info(sprintf("CMAR WorkflowService creates new job (%s) for SbS in multimedia object %s", $this->targetProfile, $multimediaObject->getId()));
            $jobs[] = $this->jobService->addUniqueJob($master->getPath(), $this->targetProfile, 2, $multimediaObject, $master->getLanguage());        
        }
    }
}
