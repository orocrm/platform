<?php

namespace Oro\Bundle\UserBundle\Command;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates user
 */
class UpdateUserCommand extends CreateUserCommand
{
    /** @var string */
    protected static $defaultName = 'oro:user:update';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Update user.')
            ->addArgument(
                'user-name',
                InputArgument::REQUIRED,
                'Username of user to update'
            )
            ->addOption('user-name', null, InputOption::VALUE_REQUIRED, 'User name')
            ->addOption('user-email', null, InputOption::VALUE_REQUIRED, 'User email')
            ->addOption('user-firstname', null, InputOption::VALUE_REQUIRED, 'User first name')
            ->addOption('user-lastname', null, InputOption::VALUE_REQUIRED, 'User last name')
            ->addOption('user-password', null, InputOption::VALUE_REQUIRED, 'User password')
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
        $username = $input->getArgument('user-name');
        /** @var User $user */
        $user     = $this->userManager->findUserByUsername($username);
        $options  = $input->getOptions();

        if (!$user) {
            throw new \InvalidArgumentException(sprintf('User "%s" not found.', $username));
        }

        try {
            $this->updateUser($user, $options);
        } catch (InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());
        }
    }
}
