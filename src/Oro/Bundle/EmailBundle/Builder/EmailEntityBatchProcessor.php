<?php

namespace Oro\Bundle\EmailBundle\Builder;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailAddress;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailOrigin;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Entity\Manager\EmailAddressManager;
use Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProvider;
use Oro\Bundle\EmailBundle\Event\EmailUserAdded;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailEntityBatchProcessor implements EmailEntityBatchInterface
{
    /**
     * @var EmailAddressManager
     */
    protected $emailAddressManager;

    /**
     * @var EmailOwnerProvider
     */
    protected $emailOwnerProvider;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var array
     */
    protected $changes = [];

    /**
     * @var EmailUser[]
     */
    protected $emailUsers = [];

    /**
     * @var EmailAddress[]
     */
    protected $addresses = [];

    /**
     * @var EmailFolder[]
     */
    protected $folders = [];

    /**
     * @var EmailOrigin[]
     */
    protected $origins = [];

    /**
     * Constructor
     *
     * @param EmailAddressManager $emailAddressManager
     * @param EmailOwnerProvider $emailOwnerProvider
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        EmailAddressManager $emailAddressManager,
        EmailOwnerProvider $emailOwnerProvider,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->emailAddressManager = $emailAddressManager;
        $this->emailOwnerProvider = $emailOwnerProvider;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Register EmailUser object
     *
     * @param EmailUser $obj
     */
    public function addEmailUser(EmailUser $obj)
    {
        $this->emailUsers[] = $obj;
    }

    /**
     * Register EmailAddress object
     *
     * @param EmailAddress $obj
     * @throws \LogicException
     */
    public function addAddress(EmailAddress $obj)
    {
        $key = transliterator_transliterate('Any-Latin;Latin-ASCII;Lower;', $obj->getEmail());
        if (isset($this->addresses[$key])) {
            throw new \LogicException(sprintf('The email address "%s" already exists in the batch.', $obj->getEmail()));
        }
        $this->addresses[$key] = $obj;
    }

    /**
     * Get EmailAddress if it exists in the batch
     *
     * @param string $email The email address
     * @return EmailAddress|null
     */
    public function getAddress($email)
    {
        $key = strtolower($email);

        return isset($this->addresses[$key])
            ? $this->addresses[$key]
            : null;
    }

    /**
     * Register EmailFolder object
     *
     * @param EmailFolder $obj
     * @throws \LogicException
     */
    public function addFolder(EmailFolder $obj)
    {
        $key = strtolower(sprintf('%s_%s', $obj->getType(), $obj->getFullName()));
        if (isset($this->folders[$key])) {
            throw new \LogicException(
                sprintf('The folder "%s" (type: %s) already exists in the batch.', $obj->getFullName(), $obj->getType())
            );
        }
        $this->folders[$key] = $obj;
    }

    /**
     * Get EmailFolder if it exists in the batch
     *
     * @param string $type The folder type
     * @param string $fullName The full name of a folder
     * @return EmailFolder|null
     */
    public function getFolder($type, $fullName)
    {
        $key = strtolower(sprintf('%s_%s', $type, $fullName));

        return isset($this->folders[$key])
            ? $this->folders[$key]
            : null;
    }

    /**
     * Tell the given EntityManager to manage this batch
     *
     * @param EntityManager $em
     */
    public function persist(EntityManager $em)
    {
        $this->persistFolders($em);
        $this->persistAddresses($em);
        $this->persistEmailUsers($em);
    }

    /**
     * Get the list of all changes made by {@see persist()} method
     * For example new objects can be replaced by existing ones from a database
     *
     * @return array [old, new] The list of changes
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * {@inhericDoc}
     */
    public function clear()
    {
        $this->changes = [];
        $this->emailUsers = [];
        $this->folders = [];
        $this->origins = [];
        $this->addresses = [];
    }

    /**
     * Removes all email objects from this batch processor
     */
    public function removeEmailUsers()
    {
        $this->emailUsers = [];
    }

    /**
     * Persist EmailUser objects
     *
     * @param EntityManager $em
     */
    protected function persistEmailUsers(EntityManager $em)
    {
        $this->processDuplicateEmails($em);

        foreach ($this->emailUsers as $emailUser) {
            $em->persist($emailUser);

            $this->eventDispatcher->dispatch(new EmailUserAdded($emailUser), EmailUserAdded::NAME);
        }
    }

    /**
     * Replaces emails with already existing in DB emails to avoid duplicates
     *
     * @param EntityManager $em
     */
    protected function processDuplicateEmails(EntityManager $em)
    {
        $existingEmails = $this->getExistingEmails($em);
        if (!empty($existingEmails)) {
            // add existing emails to new folders and remove these emails from the list
            foreach ($existingEmails as $existingEmail) {
                foreach ($this->emailUsers as $key => $emailUser) {
                    if ($this->areEmailsEqual($emailUser->getEmail(), $existingEmail)) {
                        $oldEmail = $emailUser->getEmail();
                        $emailUser->setEmail($existingEmail);

                        $this->changes[] = ['old' => $oldEmail, 'new' => $existingEmail];
                    }
                }
            }
        }
    }

    /**
     * Loads emails already exist in the database for the current batch
     *
     * @param EntityManager $em
     * @return Email[]
     */
    protected function getExistingEmails(EntityManager $em)
    {
        // get distinct list of Message-ID
        $messageIds = [];
        foreach ($this->emailUsers as $emailUser) {
            $messageId = $emailUser->getEmail()->getMessageId();
            if (!empty($messageId)) {
                $messageIds[$messageId] = $messageId;
            }
        }
        if (empty($messageIds)) {
            return [];
        }

        return $em->getRepository('OroEmailBundle:Email')
            ->findBy(['messageId' => array_values($messageIds)]);
    }

    /**
     * Determines whether two emails are the same email message
     *
     * @param Email $email1
     * @param Email $email2
     * @return bool
     */
    protected function areEmailsEqual(Email $email1, Email $email2)
    {
        return $email1->getMessageId() === $email2->getMessageId();
    }

    /**
     * Determines whether two email addresses are the same
     *
     * @param EmailAddress|null $address1
     * @param EmailAddress|null $address2
     *
     *@return bool
     */
    protected function areAddressesEqual($address1, $address2)
    {
        if ($address1 === $address2) {
            return true;
        }
        if (null === $address1 || null === $address2) {
            return false;
        }

        return strtolower($address1->getEmail()) === strtolower($address2->getEmail());
    }

    /**
     * Tell the given EntityManager to manage EmailAddress objects in this batch
     *
     * @param EntityManager $em
     */
    protected function persistAddresses(EntityManager $em)
    {
        $repository = $this->emailAddressManager->getEmailAddressRepository($em);
        foreach ($this->addresses as $key => $obj) {
            /** @var EmailAddress $dbObj */
            $dbObj = $repository->findOneBy(['email' => $obj->getEmail()]);
            if ($dbObj === null) {
                $obj->setOwner($this->emailOwnerProvider->findEmailOwner($em, $obj->getEmail()));
                $em->persist($obj);
            } else {
                $this->updateAddressReferences($obj, $dbObj);
                $this->addresses[$key] = $dbObj;
            }
        }
    }

    /**
     * Tell the given EntityManager to manage EmailFolder objects in this batch
     *
     * @param EntityManager $em
     */
    protected function persistFolders(EntityManager $em)
    {
        $repository = $em->getRepository('OroEmailBundle:EmailFolder');
        foreach ($this->folders as $key => $obj) {
            if ($obj->getId() !== null) {
                continue;
            }
            /** @var EmailFolder $dbObj */
            $dbObj = $repository->findOneBy(['fullName' => $obj->getFullName(), 'type' => $obj->getType()]);
            if ($dbObj === null) {
                $em->persist($obj);
            } else {
                $this->changes[] = ['old' => $obj, 'new' => $dbObj];
                $this->updateFolderReferences($obj, $dbObj);
                $this->folders[$key] = $dbObj;
            }
        }
    }

    /**
     * Make sure that all objects in this batch have correct EmailAddress references
     *
     * @param EmailAddress $old
     * @param EmailAddress $new
     */
    protected function updateAddressReferences(EmailAddress $old, EmailAddress $new)
    {
        foreach ($this->emailUsers as $emailUser) {
            if ($emailUser->getEmail()->getFromEmailAddress() === $old) {
                $emailUser->getEmail()->setFromEmailAddress($new);
            }
            foreach ($emailUser->getEmail()->getRecipients() as $recipient) {
                if ($recipient->getEmailAddress() === $old) {
                    $recipient->setEmailAddress($new);
                }
            }
        }
    }

    /**
     * Make sure that all objects in this batch have correct EmailFolder references
     *
     * @param EmailFolder $oldFolder
     * @param EmailFolder $newFolder
     */
    protected function updateFolderReferences(EmailFolder $oldFolder, EmailFolder $newFolder)
    {
        foreach ($this->emailUsers as $emailUser) {
            $emailUser->removeFolder($oldFolder);
            $emailUser->addFolder($newFolder);
        }
    }
}
