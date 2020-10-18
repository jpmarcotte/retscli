<?php
declare(strict_types=1);

namespace ArielAllon\RetsCli\Command;

use ArielAllon\RetsCli\Configuration;
use ArielAllon\RetsCli\PHRETS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Query extends Command
{
    private const ARGUMENT_KEY = 'key';
    private const ARGUMENT_QUERY = 'query';
    private const ARGUMENT_RESOURCE_ALIAS = 'resource_alias';

    private const OPTION_RESOURCE = 'resource';
    private const OPTION_CLASS = 'class';
    private const OPTION_OFFSET = 'offset';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_COUNT = 'count';

    protected static $defaultName = 'query';

    /** @var \PHRETS\Session */
    private $phrets_session;

    /** @var string */
    private $resource_alias;

    /** @var array */
    private $resources_and_classes;

    protected function configure()
    {
        $this->setDescription('Send a Query to the RETS server')
            ->addArgument(
                self::ARGUMENT_KEY,
                InputArgument::REQUIRED,
                'Key of configuration to use'
            )
            ->addArgument(
                self::ARGUMENT_QUERY,
                InputArgument::REQUIRED,
                'Query to send to RETS server'
            )
            ->addArgument(
                self::ARGUMENT_RESOURCE_ALIAS,
                InputArgument::REQUIRED,
                'Alias in config file for resource+class(es) to query'
            )
            ->addOption(
                self::OPTION_RESOURCE,
                'r',
                InputOption::VALUE_OPTIONAL,
                'Specific resource for this query. If not provided, will run against all in config file.',
                null
            )
            ->addOption(
                self::OPTION_CLASS,
                    'c',
                InputOption::VALUE_OPTIONAL,
                'Specific class for this query. If not provided, will run against all in config file.',
                null
                )
            ->addOption(
                self::OPTION_OFFSET,
                'o',
                InputOption::VALUE_OPTIONAL,
                'Starting offset for query.',
                0
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit for query.',
                100
            )
            ->addOption(
                self::OPTION_COUNT,
                'C',
                InputOption::VALUE_OPTIONAL,
                'Is this a count query',
                false
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $mlsConfigurationArray = (new Configuration\FromYaml())->getConfigurationByKey($input->getArgument(self::ARGUMENT_KEY));

        $sessionBuilder = new PHRETS\SessionBuilder(); // @todo move to Di
        $phretsSession = $sessionBuilder->fromConfigurationArray($mlsConfigurationArray);
        $this->setPhretsSession($phretsSession);

        $this->setResourceAlias($input->getArgument(self::ARGUMENT_RESOURCE_ALIAS));

        $specificResource = $input->getOption(self::OPTION_RESOURCE);
        $specificClass = $input->getOption(self::OPTION_CLASS);
        if ($specificResource === null xor $specificClass === null) {
            throw new \RuntimeException('If a class is specified, a resource must also be specified, and vice versa');
        }
        if ($specificResource !== null && $specificClass !== null) {
            $this->setResourcesAndClasses(
                [
                    'resource' => $specificResource,
                    'classes' => [$specificClass],
                ]
            );
        } else {
            $this->setResourcesAndClasses($mlsConfigurationArray['resources'][$this->getResourceAlias()]);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getPhretsSession()->Login();
        foreach ($this->getResourcesAndClasses()['classes'] as $class) {
            do {
                $results = $this->getPhretsSession()->Search(
                    $this->getResourcesAndClasses()['resource'],
                    $class,
                    $input->getArgument(self::ARGUMENT_QUERY),
                    [
                        'Format' => 'COMPACT-DECODED',
                        'Offset' => $input->getOption(self::OPTION_OFFSET),
                        'Limit' => $input->getOption(self::OPTION_LIMIT),
                        // @todo standardnames
                    ]
                );
                var_export($results->toArray());
            } while (count($results) > $input->getOption(self::OPTION_LIMIT));

        }
        $this->getPhretsSession()->Disconnect();
        return Command::SUCCESS;
    }

    private function getPhretsSession(): \PHRETS\Session
    {
        if ($this->phrets_session === null) {
            throw new \LogicException('Query phretsSession has not been set.');
        }

        return $this->phrets_session;
    }

    private function setPhretsSession(\PHRETS\Session $phretsSession): self
    {
        if ($this->phrets_session !== null) {
            throw new \LogicException('Query phretsSession already set.');
        }

        $this->phrets_session = $phretsSession;

        return $this;
    }

    private function getResourceAlias(): string
    {
        if ($this->resource_alias === null) {
            throw new \LogicException('Query resource_alias has not been set.');
        }

        return $this->resource_alias;
    }

    private function setResourceAlias(string $resource_alias): self
    {
        if ($this->resource_alias !== null) {
            throw new \LogicException('Query resource_alias already set.');
        }

        $this->resource_alias = $resource_alias;

        return $this;
    }

    private function getResourcesAndClasses(): array
    {
        if ($this->resources_and_classes === null) {
            throw new \LogicException('Query resources_and_classes has not been set.');
        }

        return $this->resources_and_classes;
    }

    private function setResourcesAndClasses(array $resources_and_classes): self
    {
        if ($this->resources_and_classes !== null) {
            throw new \LogicException('Query resources_and_classes already set.');
        }

        $this->resources_and_classes = $resources_and_classes;

        return $this;
    }
}
