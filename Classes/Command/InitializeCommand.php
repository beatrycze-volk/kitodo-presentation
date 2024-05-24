<?php

/**
 * (c) Kitodo. Key to digital objects e.V. <contact@kitodo.org>
 *
 * This file is part of the Kitodo and TYPO3 projects.
 *
 * @license GNU General Public License version 3 or later.
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Kitodo\Dlf\Command;

use Kitodo\Dlf\Common\Initializer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CLI Command for initialize data.
 *
 * @package TYPO3
 * @subpackage dlf
 *
 * @access public
 */
class InitializeCommand extends BaseCommand
{

    /**
     * Configure the command by defining the name, options and arguments
     *
     * @access public
     *
     * @return void
     */
    public function configure(): void
    {
        $this
            ->setDescription('Initialize the default data.')
            ->setHelp('')
            ->addOption(
                'pid',
                'p',
                InputOption::VALUE_REQUIRED,
                'UID of the page where elements should be stored.'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'If this option is set, the check if data is actually inserted will be performed.'
            );
    }

    /**
     * Executes the command to insert initial data.
     *
     * @access protected
     *
     * @param InputInterface $input The input parameters
     * @param OutputInterface $output The Symfony interface for outputs on console
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $type = (string) $input->getOption('type');
        $pid = (int) $input->getOption('pid');

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pid);
        } catch (SiteNotFoundException $e) {
            $site = new NullSite();
        }
        $siteLanguages = $site->getLanguages();

        $success = true;

        switch ($type) {
            case 'all':
                $allInserted = $this->isAllInserted($io, $pid);

                $success = $allInserted['format'] && $allInserted['metadata'] && $allInserted['structure'] && $allInserted['solr'];

                if (!$allInserted['format']) {
                    $io->writeln('Inserting Format data...');
                    $success = Initializer::insertFormats($this->formatRepository, $this->persistenceManager);
                    $io->writeln('Finished inserting Format data...');
                }

                if (!$allInserted['metadata']) {
                    $io->writeln('Inserting Metadata data...');
                    $success = Initializer::insertMetadata($pid, $siteLanguages, $this->formatRepository, $this->metadataRepository);
                    $io->writeln('Finished inserting Metadata data...');
                }

                if (!$allInserted['structure']) {
                    $io->writeln('Inserting Structure data...');
                    $success = Initializer::insertStructures($pid, $siteLanguages, $this->structureRepository);
                    $io->writeln('Finished inserting Structure data...');
                }

                if (!$allInserted['solr']) {
                    $io->writeln('Inserting SOLR cores...');
                    $success = Initializer::insertSolrCores($pid, $siteLanguages, $this->solrCoreRepository, $this->persistenceManager);
                    $io->writeln('Finished inserting SOLR cores...');
                }

                if ($success) {
                    $io->success('All data inserted!');
                    return BaseCommand::SUCCESS;
                }
                break;
            case 'format':
                if (!$this->isFormatInserted($io)) {
                    $io->writeln('Inserting Format data...');
                    $success = Initializer::insertFormats($this->formatRepository, $this->persistenceManager);
                }

                if ($success) {
                    $io->success('Format data inserted!');
                    return BaseCommand::SUCCESS;
                }
                break;
            case 'metadata':
                if (!$this->isMetadataInserted($io, $pid)) {
                    $io->writeln('Inserting Metadata data...');
                    $success = Initializer::insertMetadata($pid, $siteLanguages, $this->formatRepository, $this->metadataRepository);
                }

                if ($success) {
                    $io->success('Metadata data inserted!');
                    return BaseCommand::SUCCESS;
                }
                break;
            case 'structure':
                if (!$this->isStructureInserted($io, $pid)) {
                    $io->writeln('Inserting Structure data...');
                    $success = Initializer::insertStructures($pid, $siteLanguages, $this->structureRepository);
                }

                if ($success) {
                    $io->success('Structure data inserted!');
                    return BaseCommand::SUCCESS;
                }
                break;
            case 'solr':
                if (!$this->isSolrCoreInserted($io, $pid)) {
                    $io->writeln('Inserting SOLR cores...');
                    $success = Initializer::insertSolrCores($pid, $siteLanguages, $this->solrCoreRepository, $this->persistenceManager);
                }

                if ($success) {
                    return BaseCommand::SUCCESS;
                }
                break;
            default:
                $io->error('ERROR: Required parameter --type|-t is not a valid data type.');
                return BaseCommand::FAILURE;
                break;
        }

        $io->error('ERROR: Initial data was not inserted for "' . $type . '" type.');
        return BaseCommand::FAILURE;
    }

    private function isAllInserted(SymfonyStyle $io, int $pid): array
    {
        return [
            'format' => $this->isFormatInserted($io),
            'metadata' => $this->isMetadataInserted($io, $pid),
            'structure' => $this->isStructureInserted($io, $pid),
            'solr' => $this->isSolrCoreInserted($io, $pid)
        ];
    }

    private function isFormatInserted(SymfonyStyle $io): bool
    {
        $countAll = $this->formatRepository->countAll();
        $countDefault = Initializer::countDefaults('FormatDefaults.php');
        $isInserted = $countAll >= $countDefault;

        if ($isInserted) {
            $io->writeln('Default Format data is inserted: ' . $countAll . ' records stored in the database.');
            return true;
        }
        
        $io->writeln('Default Format data needs to be inserted: ' . $countDefault . ' records still missing.');
        return false;
    }

    private function isMetadataInserted(SymfonyStyle $io, int $pid): bool
    {
        $countAll = $this->solrCoreRepository->countByPid($pid);
        $countDefault = Initializer::countDefaults('MetadataDefaults.php');
        $isInserted = $countAll >= $countDefault;
        $io->writeln($pid);
        $io->writeln($countAll);
        $io->writeln($countDefault);
        $io->writeln($isInserted);
        if ($isInserted) {
            $io->writeln('Default Metadata data is already inserted: ' . $countAll . ' records stored in the database.');
            return true;
        }
        
        $io->writeln('Default Metadata data needs to be inserted: ' . $countDefault . ' records still missing.');
        return false;
    }

    private function isStructureInserted(SymfonyStyle $io, int $pid): bool
    {
        $countAll = $this->solrCoreRepository->countByPid($pid);
        $countDefault = Initializer::countDefaults('StructureDefaults.php');
        $isInserted = $countAll >= $countDefault;

        if ($isInserted) {
            $io->writeln('Default Structure data is already inserted: ' . $countAll . ' records stored in the database.');
            return true;
        }

        $io->writeln('Default Structure data needs to be inserted: ' . $countDefault . ' records still missing.');
        return false;
    }

    private function isSolrCoreInserted(SymfonyStyle $io, int $pid): bool
    {
        $countAll = $this->solrCoreRepository->countByPid($pid);

        if ($countAll > 0) {
            $io->writeln('There are ' . $countAll . ' SOLR core(s) available.');
            return true;
        }
        
        $io->writeln('There must be at least one SOLR core available.');
        return false;
    }
}
