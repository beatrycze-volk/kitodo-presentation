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

use Kitodo\Dlf\Common\AbstractDocument;
use Kitodo\Dlf\Command\BaseCommand;
use Kitodo\Dlf\Common\Indexer;
use Kitodo\Dlf\Common\Solr\Solr;
use Kitodo\Dlf\Domain\Model\Document;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * CLI Command for re-indexing collections into database and Solr.
 *
 * @package TYPO3
 * @subpackage dlf
 *
 * @access public
 */
class ReindexCommand extends BaseCommand
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
            ->setDescription('Reindex a collection into database and Solr.')
            ->setHelp('')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the files will not actually be processed but the location URI is shown.'
            )
            ->addOption(
                'coll',
                'c',
                InputOption::VALUE_REQUIRED,
                'UID of the collection.'
            )
            ->addOption(
                'pid',
                'p',
                InputOption::VALUE_REQUIRED,
                'UID of the page the documents should be added to.'
            )
            ->addOption(
                'solr',
                's',
                InputOption::VALUE_REQUIRED,
                '[UID|index_name] of the Solr core the document should be added to.'
            )
            ->addOption(
                'owner',
                'o',
                InputOption::VALUE_OPTIONAL,
                '[UID|index_name] of the Library which should be set as owner of the documents.'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Reindex all documents on the given page.'
            );
    }

    /**
     * Executes the command to index the given document to DB and SOLR.
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
        $dryRun = $input->getOption('dry-run') != false ? true : false;

        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $this->initializeRepositories($input->getOption('pid'));

        if ($this->storagePid == 0) {
            $io->error('ERROR: No valid PID (' . $this->storagePid . ') given.');
            return BaseCommand::FAILURE;
        }

        if (
            !empty($input->getOption('solr'))
            && !is_array($input->getOption('solr'))
        ) {
            $allSolrCores = $this->getSolrCores($this->storagePid);
            $solrCoreUid = $this->getSolrCoreUid($allSolrCores, $input->getOption('solr'));

            // Abort if solrCoreUid is empty or not in the array of allowed solr cores.
            if (empty($solrCoreUid) || !in_array($solrCoreUid, $allSolrCores)) {
                $output_solrCores = [];
                foreach ($allSolrCores as $index_name => $uid) {
                    $output_solrCores[] = $uid . ' : ' . $index_name;
                }
                if (empty($output_solrCores)) {
                    $io->error('ERROR: No valid Solr core ("' . $input->getOption('solr') . '") given. No valid cores found on PID ' . $this->storagePid . ".\n");
                    return BaseCommand::FAILURE;
                } else {
                    $io->error('ERROR: No valid Solr core ("' . $input->getOption('solr') . '") given. ' . "Valid cores are (<uid>:<index_name>):\n" . implode("\n", $output_solrCores) . "\n");
                    return BaseCommand::FAILURE;
                }
            }
        } else {
            $io->error('ERROR: Required parameter --solr|-s is missing or array.');
            return BaseCommand::FAILURE;
        }

        if (!empty($input->getOption('owner'))) {
            if (MathUtility::canBeInterpretedAsInteger($input->getOption('owner'))) {
                $this->owner = $this->libraryRepository->findByUid(MathUtility::forceIntegerInRange((int) $input->getOption('owner'), 1));
            } else {
                $this->owner = $this->libraryRepository->findOneByIndexName((string) $input->getOption('owner'));
            }
        } else {
            $this->owner = null;
        }

        if (!empty($input->getOption('all'))) {
            $count = $this->documentRepository->countAll();
            $offset = 0;
            $limit = 100;

            while ($offset < $count) {
                $io->writeln('Offset: ' . $offset);
                $io->writeln('------------------------------------------------------');
                $io->writeln('Current usage: ' . round(memory_get_usage() / 1024) . ' KB before reindexDocuments');
                $io->writeln('------------------------------------------------------');
                $documents = $this->documentRepository->findAll()->getQuery()->setLimit($limit)->setOffset($offset)->execute();
                $this->reindexDocuments($dryRun, $count, $offset, $solrCoreUid, $documents, $io);
                $io->writeln('------------------------------------------------------');
                $io->writeln('Current usage: ' . round(memory_get_usage() / 1024) . ' KB after reindexDocuments');
                $io->writeln('Collected cycles: ' . gc_collect_cycles());
                $io->writeln('------------------------------------------------------');
                unset($documents);
                $offset += $limit;
            }
        } elseif (
            !empty($input->getOption('coll'))
            && !is_array($input->getOption('coll'))
        ) {
            // "coll" may be a single integer or a comma-separated list of integers.
            if (empty(array_filter(GeneralUtility::intExplode(',', $input->getOption('coll'), true)))) {
                $io->error('ERROR: Parameter --coll|-c is not a valid comma-separated list of collection UIDs.');
                return BaseCommand::FAILURE;
            }
            // Get all documents of given collections.
            $documents = $this->documentRepository->findAllByCollectionsLimited(GeneralUtility::intExplode(',', $input->getOption('coll'), true), 0);
        } else {
            $io->error('ERROR: One of parameters --all|-a or --coll|-c must be given.');
            return BaseCommand::FAILURE;
        }

        $io->success('All done!');

        $io->writeln('Current usage: ' . round(memory_get_usage() / 1024) . ' KB');
        $io->writeln('   Peak usage: ' . round(memory_get_peak_usage() / 1024) . ' KB');

        return BaseCommand::SUCCESS;
    }

    private function reindexDocuments(bool $dryRun, int $count, int $offset, int $solrCoreUid, $documents, SymfonyStyle $io): void
    {
        foreach ($documents as $id => $document) {
            $this->reindexDocument($dryRun, $count, $offset, $solrCoreUid, $document, $id, $io);
            unset($id, $document); 
         }
    }

    /**
     * Undocumented function
     *
     * @access private
     *
     * @param boolean $dryRun
     * @param integer $count
     * @param integer $solrCoreUid
     * @param Document $document
     * @param integer $id
     * @param SymfonyStyle $io
     *
     * @return void
     */
    private function reindexDocument(bool $dryRun, int $count, int $offset, int $solrCoreUid, Document $document, int $id, SymfonyStyle $io): void
    {
        $doc = AbstractDocument::getInstance($document->getLocation(), ['storagePid' => $this->storagePid], true);

        if ($doc === null) {
            $io->warning('WARNING: Document "' . $document->getLocation() . '" could not be loaded. Skip to next document.');
        } else {
            if ($dryRun) {
                $io->writeln('DRY RUN: Would index ' . ($id + $offset + 1) . '/' . $count . ' with UID "' . $document->getUid() . '" ("' . $document->getLocation() . '") on PID ' . $this->storagePid . ' and Solr core ' . $solrCoreUid . '.');
            } else {
                if ($io->isVerbose()) {
                    $io->writeln(date('Y-m-d H:i:s') . ' Indexing ' . ($id + 1) . '/' . $count . ' with UID "' . $document->getUid() . '" ("' . $document->getLocation() . '") on PID ' . $this->storagePid . ' and Solr core ' . $solrCoreUid . '.');
                }
                $io->writeln('Peak usage - begin: ' . round(memory_get_peak_usage() / 1024) . ' KB');
                $io->writeln('------------------------------------------------------');
                $document->setCurrentDocument($doc);
                $io->writeln('Current usage 1: ' . round(memory_get_usage() / 1024) . ' KB - after setCurrentDocument()');
                // save to database
                $this->saveToDatabase($document);
                $io->writeln('Current usage 2: ' . round(memory_get_usage() / 1024) . ' KB - after saveToDatabase()');
                // add to index
                Indexer::add($document, $this->documentRepository);
                $io->writeln('Current usage 3: ' . round(memory_get_usage() / 1024) . ' KB - after add()');
            }
            // Clear document and persistence cache to prevent memory exhaustion.
            AbstractDocument::clearDocumentCache();
            $io->writeln('Current usage 4: ' . round(memory_get_usage() / 1024) . ' KB - after clearDocumentCache()');
            $this->persistenceManager->clearState();
            $io->writeln('Current usage 5: ' . round(memory_get_usage() / 1024) . ' KB - after clearState()');
            Solr::clearRegistry();
            $io->writeln('Current usage 6: ' . round(memory_get_usage() / 1024) . ' KB - after clearRegistry()');
            $io->writeln('------------------------------------------------------');
            $io->writeln('Peak usage - end: ' . round(memory_get_peak_usage() / 1024) . ' KB');
        }
    }
}
