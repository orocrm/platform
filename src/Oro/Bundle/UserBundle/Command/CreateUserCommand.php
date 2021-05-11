<?php

namespace Oro\Bundle\UserBundle\Command;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Bundle\UserBundle\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a user.
 */
class CreateUserCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'oro:user:create';

    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @param UserManager $userManager
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(UserManager $userManager, EntityManagerInterface $entityManager)
    {
        $this->userManager = $userManager;
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Create user.')
            ->addOption('user-role', null, InputOption::VALUE_REQUIRED, 'User role')
            ->addOption('user-business-unit', null, InputOption::VALUE_REQUIRED, 'User business unit (required)')
            ->addOption('user-name', null, InputOption::VALUE_REQUIRED, 'User name (required)')
            ->addOption('user-email', null, InputOption::VALUE_REQUIRED, 'User email (required)')
            ->addOption('user-firstname', null, InputOption::VALUE_REQUIRED, 'User first name')
            ->addOption('user-lastname', null, InputOption::VALUE_REQUIRED, 'User last name')
            ->addOption('user-password', null, InputOption::VALUE_OPTIONAL, 'User password (password will be generated if ommited)')
            ->addOption(
                'user-organizations',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'User organizations'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var User $user */
        $user = $this->userManager->createUser();
        $user->setEnabled(true);

        $options = $input->getOptions();

        try {
            $this
                ->checkRequiredOptions($options)
                ->updateUser($user, $options);
        } catch (InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());
        } catch (DBALException $exception) {
            $output->writeln('User exists');
        }
    }

    /**
     * @param array $options
     * @throws InvalidArgumentException
     * @return $this
     */
    protected function checkRequiredOptions($options)
    {
        $requiredOptions = [
            'user-business-unit',
            'user-name',
            'user-email'
        ];

        foreach ($requiredOptions as $requiredOption) {
            if (empty($options[$requiredOption])) {
                throw new InvalidArgumentException('--' . $requiredOption . ' option required');
            }
        }

        return $this;
    }

    /**
     * @param User  $user
     * @param array $options
     * @throws InvalidArgumentException
     */
    protected function updateUser(User $user, $options)
    {
        if (!empty($options['user-name'])) {
            $user->setUsername($options['user-name']);
        }

        if (!empty($options['user-password'])) {
            $user->setPlainPassword($options['user-password']);
        } else {
            $user->setPlainPassword($this->userManager->generatePassword());
        }

        $this
            ->setRole($user, $options)
            ->setBusinessUnit($user, $options)
            ->setOrganizations($user, $options)
            ->setProperties($user, $options);

        $this->userManager->updateUser($user);
    }

    /**
     * @param User $user
     * @param array $options
     * @throws InvalidArgumentException
     * @return $this
     */
    protected function setRole(User $user, $options)
    {
        $roleName = null;
        if (!empty($options['user-role'])) {
            $roleName = $options['user-role'];
        } elseif (null === $user->getId()) {
            $roleName = User::ROLE_DEFAULT;
        }
        if ($roleName) {
            $role = $this->entityManager
                ->getRepository(Role::class)
                ->findOneBy(['role' => $roleName]);

            if (!$role) {
                throw new InvalidArgumentException('Invalid Role');
            }

            $user->addRole($role);
        }

        return $this;
    }

    /**
     * @param User $user
     * @param array $options
     * @throws InvalidArgumentException
     * @return $this
     */
    protected function setBusinessUnit(User $user, $options)
    {
        if (!empty($options['user-business-unit'])) {
            $businessUnit = $this->entityManager
                ->getRepository(BusinessUnit::class)
                ->findOneBy(['name' => $options['user-business-unit']]);

            if (!$businessUnit) {
                throw new InvalidArgumentException('Invalid Business Unit');
            }

            $user
                ->setOwner($businessUnit)
                ->setOrganization($businessUnit->getOrganization())
                ->addOrganization($businessUnit->getOrganization())
                ->addBusinessUnit($businessUnit);
        }

        return $this;
    }

    /**
     * @param User $user
     * @param array $options
     * @throws InvalidArgumentException
     * @return $this
     */
    protected function setOrganizations(User $user, $options)
    {
        if (!empty($options['user-organizations'])) {
            foreach ($options['user-organizations'] as $organizationName) {
                try {
                    $organization = $this->entityManager
                        ->getRepository(Organization::class)
                        ->getOrganizationByName($organizationName);
                } catch (NoResultException $e) {
                    throw new InvalidArgumentException('Invalid organization "' . $organizationName .
                        '" in "--user-organizations" parameter');
                }

                $user->addOrganization($organization);
            }
        }

        return $this;
    }

    /**
     * @param User $user
     * @param array $options
     * @throws InvalidArgumentException
     * @return $this
     */
    protected function setProperties(User $user, $options)
    {
        $properties = ['email', 'firstname', 'lastname'];
        foreach ($properties as $property) {
            if (!empty($options['user-' . $property])) {
                $user->{'set' . str_replace('-', '', $property)}($options['user-' . $property]);
            }
        }

        return $this;
    }
}
