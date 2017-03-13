<?php

namespace Pumukit\NotificationBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Pumukit\NotificationBundle\Services\SenderService;

class SenderServiceTest extends WebTestCase
{
    private $dm;
    private $repo;
    private $senderService;
    private $mailer;
    private $templating;
    private $translator;
    private $enable;
    private $senderName;
    private $senderEmail;
    private $adminEmail;
    private $notificateErrorsToAdmin;
    private $platformName;
    private $sendPersonName;
    private $environment;

    public function setUp()
    {
        $options = array('environment' => 'dev');
        static::bootKernel($options);
        $container = static::$kernel->getContainer();

        $this->dm = $container->get('doctrine_mongodb')->getManager();

        $this->mailer = $container->get('mailer');
        $this->templating = $container->get('templating');
        $this->translator = $container->get('translator');
        $this->enable = true;
        $this->senderEmail = 'sender@pumukit.org';
        $this->senderName = 'Sender Pumukit';
        $this->enableMultiLang = true;
        $this->locales = array('en', 'es');
        $this->subjectSuccessTrans = array(0 => array('locale' => 'en', 'subject' => 'Job Success'), 1 => array('locale' => 'es', 'subject' => 'Trabajo exitoso'));
        $this->subjectFailsTrans = array(0 => array('locale' => 'en', 'subject' => 'Job Fails'), 1 => array('locale' => 'es', 'subject' => 'Trabajo fallido'));
        $this->adminEmail = 'admin@pumukit.org';
        $this->notificateErrorsToAdmin = true;
        $this->platformName = 'Pumukit tv';
        $this->sendPersonName = false;
        $this->environment = 'dev';

        $this->senderService = new SenderService($this->mailer, $this->templating, $this->translator, $this->dm, $this->enable, $this->senderEmail, $this->senderName, $this->enableMultiLang, $this->locales, $this->subjectSuccessTrans, $this->subjectFailsTrans, $this->adminEmail, $this->notificateErrorsToAdmin, $this->platformName, $this->sendPersonName, $this->environment);
    }

    public function tearDown()
    {
        if (isset($this->dm)) {
            $this->dm->close();
        }
        $this->dm = null;
        $this->mailer = null;
        $this->templating = null;
        $this->translator = null;
        $this->enable = null;
        $this->senderEmail = null;
        $this->senderName = null;
        $this->adminEmail = null;
        $this->notificateErrorsToAdmin = null;
        $this->platformName = null;
        $this->sendPersonName = null;
        $this->environment = null;
        $this->senderService = null;
        gc_collect_cycles();
        parent::tearDown();
    }

    public function testIsEnabled()
    {
        $this->assertEquals($this->enable, $this->senderService->isEnabled());
    }

    public function testGetSenderEmail()
    {
        $this->assertEquals($this->senderEmail, $this->senderService->getSenderEmail());
    }

    public function testGetSenderName()
    {
        $this->assertEquals($this->senderName, $this->senderService->getSenderName());
    }

    public function testGetAdminEmail()
    {
        $this->assertEquals($this->adminEmail, $this->senderService->getAdminEmail());
    }

    public function testDoNotificateErrorsToAdmin()
    {
        $this->assertEquals($this->notificateErrorsToAdmin, $this->senderService->doNotificateErrorsToAdmin());
    }

    public function testGetPlatformName()
    {
        $this->assertEquals($this->platformName, $this->senderService->getPlatformName());
    }

    public function testIsEnabledSendPersonName()
    {
        $this->assertEquals($this->sendPersonName, $this->senderService->isEnabledSendPersonName());
    }

    public function testSendNotification()
    {
        $this->markTestSkipped('S');

        $mailTo = 'receiver@pumukit.org';
        $subject = 'Test sender service';
        $body = 'test send notification';
        $template = 'PumukitNotificationBundle:Email:notification.html.twig';
        $parameters = array('subject' => $subject, 'body' => $body, 'sender_name' => 'Sender Pumukit');
        $output = $this->senderService->sendNotification($mailTo, $subject, $template, $parameters, false);
        $this->assertEquals(1, $output);
    }
}
