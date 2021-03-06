<?php

namespace Tests\AppBundle\Mailjet\EventSubscriber;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\CommitteeFeedItem;
use AppBundle\Entity\MailjetEmail;
use AppBundle\Mailjet\Event\MailjetEvent;
use AppBundle\Mailjet\EventSubscriber\EmailPersisterEventSubscriber;
use AppBundle\Mailjet\EmailTemplate;
use AppBundle\Mailjet\Message\CommitteeMessageNotificationMessage;
use AppBundle\Repository\MailjetEmailRepository;
use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Tests\AppBundle\Test\Mailjet\Message\DummyMessage;

class EmailPersisterEventSubscriberTest extends TestCase
{
    /** @var EmailPersisterEventSubscriber */
    private $subscriber;
    private $repository;
    private $manager;

    public function testOnMailjetDeliveryMessage()
    {
        $this->manager->expects($this->exactly(1))->method('persist');
        $this->manager->expects($this->once())->method('flush');

        $adherents[] = $this->createAdherentMock('john@smith.tld', 'John', 'Smith');
        $adherents[] = $this->createAdherentMock('johana156@gmail.com', 'Johana', 'Durand');

        $item = $this->createCommitteeFeedItemMock('25c5762d-5a50-4c68-8f6c-106bcbff862e', 'Aurélien', 'Un message !');

        $this->subscriber->onMailjetDeliveryMessage(new MailjetEvent(
            $message = CommitteeMessageNotificationMessage::create($adherents, $item),
            EmailTemplate::createWithMailjetMessage($message, 'noreply@en-marche.fr')
        ));
    }

    public function testOnMailjetDeliverySuccessWithEmptyJsonResponsePayload()
    {
        $this->repository->expects($this->never())->method('findOneByUuid');
        $this->manager->expects($this->never())->method('flush');

        $this->subscriber->onMailjetDeliverySuccess(new MailjetEvent(
            $message = DummyMessage::create(),
            EmailTemplate::createWithMailjetMessage($message, 'noreply@en-marche.fr')
        ));
    }

    public function testOnMailjetDeliverySuccessWithEmptyMailjetMessageCollection()
    {
        $responsePayload = <<<'EOF'
{
    "Sent": [
        {"Email": "dummy@example.tld", "MessageID": 111111111111111}
    ]
}
EOF;

        $message = DummyMessage::create();
        $email = EmailTemplate::createWithMailjetMessage($message, 'noreply@en-marche.fr');
        $email->delivered($responsePayload, $email->getHttpRequestPayload());

        $this->repository->expects($this->once())->method('findOneByUuid')->willReturn(null);
        $this->manager->expects($this->never())->method('flush');

        $this->subscriber->onMailjetDeliverySuccess(new MailjetEvent($message, $email));
    }

    public function testOnMailjetDeliverySuccess()
    {
        $message = DummyMessage::create();
        $message->addRecipient('vincent777h@example.tld', 'Vincent Durand');

        $responsePayload = <<<'EOF'
{
    "Sent": [
        {"Email": "dummy@example.tld", "MessageID": 111111111111111},
        {"Email": "vincent777h@example.tld", "MessageID": 222222222222222}
    ]
}
EOF;

        $email = EmailTemplate::createWithMailjetMessage($message, 'noreply@en-marche.fr');
        $email->delivered($responsePayload, $requestPayload = $email->getHttpRequestPayload());

        $this->repository
            ->expects($this->once())
            ->method('findOneByUuid')
            ->willReturn($message1 = MailjetEmail::createFromMessage($message, $requestPayload));

        $this->manager->expects($this->once())->method('flush');

        $this->subscriber->onMailjetDeliverySuccess(new MailjetEvent($message, $email));

        $this->assertTrue($message1->isDelivered());
        $this->assertSame($requestPayload, $message1->getRequestPayloadJson());
        $this->assertSame($responsePayload, $message1->getResponsePayloadJson());
    }

    private function createAdherentMock(string $emailAddress, string $firstName, string $lastName)
    {
        $mock = $this->getMockBuilder(Adherent::class)->disableOriginalConstructor()->getMock();
        $mock->expects($this->any())->method('getEmailAddress')->willReturn($emailAddress);
        $mock->expects($this->any())->method('getFirstName')->willReturn($firstName);
        $mock->expects($this->any())->method('getFullName')->willReturn($firstName.' '.$lastName);

        return $mock;
    }

    private function createCommitteeFeedItemMock(string $uuid, string $author, string $content)
    {
        $mock = $this->getMockBuilder(CommitteeFeedItem::class)->disableOriginalConstructor()->getMock();
        $mock->expects($this->any())->method('getUuid')->willReturn(Uuid::fromString($uuid));
        $mock->expects($this->any())->method('getAuthorFirstName')->willReturn($author);
        $mock->expects($this->any())->method('getContent')->willReturn($content);

        return $mock;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $this->repository = $this->getMockBuilder(MailjetEmailRepository::class)->disableOriginalConstructor()->getMock();

        $this->subscriber = new EmailPersisterEventSubscriber(
            $this->manager,
            $this->repository
        );
    }

    protected function tearDown()
    {
        $this->manager = null;
        $this->repository = null;
        $this->subscriber = null;

        parent::tearDown();
    }
}
