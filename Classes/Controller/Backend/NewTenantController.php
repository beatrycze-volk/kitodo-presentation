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

namespace Kitodo\Dlf\Controller\Backend;

use Kitodo\Dlf\Common\Initializer;
use Kitodo\Dlf\Controller\AbstractController;
use Kitodo\Dlf\Domain\Repository\FormatRepository;
use Kitodo\Dlf\Domain\Repository\MetadataRepository;
use Kitodo\Dlf\Domain\Repository\SolrCoreRepository;
use Kitodo\Dlf\Domain\Repository\StructureRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Controller class for the backend module 'New Tenant'.
 *
 * @package TYPO3
 * @subpackage dlf
 *
 * @access public
 */
class NewTenantController extends AbstractController
{
    /**
     * @access protected
     * @var int
     */
    protected int $pid;

    /**
     * @access protected
     * @var array
     */
    protected array $pageInfo;

    /**
     * @access protected
     * @var array All configured site languages
     */
    protected array $siteLanguages;

    /**
     * @access protected
     * @var LocalizationFactory Language factory to get language key/values by our own.
     */
    protected LocalizationFactory $languageFactory;

    /**
     * @access protected
     * @var FormatRepository
     */
    protected FormatRepository $formatRepository;

    /**
     * @access public
     *
     * @param FormatRepository $formatRepository
     *
     * @return void
     */
    public function injectFormatRepository(FormatRepository $formatRepository): void
    {
        $this->formatRepository = $formatRepository;
    }

    /**
     * @access protected
     * @var MetadataRepository
     */
    protected MetadataRepository $metadataRepository;

    /**
     * @access public
     *
     * @param MetadataRepository $metadataRepository
     *
     * @return void
     */
    public function injectMetadataRepository(MetadataRepository $metadataRepository): void
    {
        $this->metadataRepository = $metadataRepository;
    }

    /**
     * @access protected
     * @var StructureRepository
     */
    protected StructureRepository $structureRepository;

    /**
     * @access public
     *
     * @param StructureRepository $structureRepository
     *
     * @return void
     */
    public function injectStructureRepository(StructureRepository $structureRepository): void
    {
        $this->structureRepository = $structureRepository;
    }

    /**
     * @access protected
     * @var SolrCoreRepository
     */
    protected SolrCoreRepository $solrCoreRepository;

    /**
     * @access public
     *
     * @param SolrCoreRepository $solrCoreRepository
     *
     * @return void
     */
    public function injectSolrCoreRepository(SolrCoreRepository $solrCoreRepository): void
    {
        $this->solrCoreRepository = $solrCoreRepository;
    }

    /**
     * Returns a response object with either the given html string or the current rendered view as content.
     * 
     * @access protected
     * 
     * @param ?string $html optional html
     * 
     * @return ResponseInterface the response
     */
    protected function htmlResponse(?string $html = null): ResponseInterface
    {
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();

        $moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
        $moduleTemplate = $moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        $moduleTemplate->setFlashMessageQueue($messageQueue);
        return parent::htmlResponse(($html ?? $moduleTemplate->renderContent()));
    }

    /**
     * Initialization for all actions
     *
     * @access protected
     *
     * @return void
     */
    protected function initializeAction(): void
    {
        // replace with $this->request->getQueryParams() when dropping support for Typo3 v11, see Deprecation-100596
        $this->pid = (int) GeneralUtility::_GP('id');

        $frameworkConfiguration = $this->configurationManager->getConfiguration($this->configurationManager::CONFIGURATION_TYPE_FRAMEWORK);
        $frameworkConfiguration['persistence']['storagePid'] = $this->pid;
        $this->configurationManager->setConfiguration($frameworkConfiguration);

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($this->pid);
        } catch (SiteNotFoundException $e) {
            $site = new NullSite();
        }
        $this->siteLanguages = $site->getLanguages();
    }

    /**
     * Action adding formats records
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function addFormatAction(): ResponseInterface
    {
        // Include formats definition file.
        $formatsDefaults = $this->getRecords('Format');
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        Initializer::insertFormats($this->formatRepository, $persistenceManager);

        return $this->redirect('index');
    }

    /**
     * Action adding metadata records
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function addMetadataAction(): ResponseInterface
    {
        Initializer::insertMetadata($this->pid, $this->siteLanguages, $this->formatRepository, $this->metadataRepository);

        return $this->redirect('index');
    }

    /**
     * Action adding Solr core records
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function addSolrCoreAction(): ResponseInterface
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        Initializer::insertSolrCores($this->pid, $this->siteLanguages, $this->solrCoreRepository, $persistenceManager);

        return $this->redirect('index');
    }

    /**
     * Action adding structure records
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function addStructureAction(): ResponseInterface
    {
        Initializer::insertStructures($this->pid, $this->siteLanguages, $this->structureRepository);

        $this->forward('index');
    }

    /**
     * Main function of the module
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function indexAction(): ResponseInterface
    {
        $recordInfos = [];

        $this->pageInfo = BackendUtility::readPageAccess($this->pid, $GLOBALS['BE_USER']->getPagePermsClause(1));

        if (!isset($this->pageInfo['doktype']) || $this->pageInfo['doktype'] != 254) {
            return $this->redirect('error');
        }

        $formatsDefaults = $this->getRecords('Format');
        $recordInfos['formats']['numCurrent'] = $this->formatRepository->countAll();
        $recordInfos['formats']['numDefault'] = Initializer::countDefaults('FormatDefaults.php');


        $structuresDefaults = $this->getRecords('Structure');
        $recordInfos['structures']['numCurrent'] = $this->structureRepository->countByPid($this->pid);
        $recordInfos['structures']['numDefault'] = Initializer::countDefaults('StructureDefaults.php');;

        $metadataDefaults = $this->getRecords('Metadata');
        $recordInfos['metadata']['numCurrent'] = $this->metadataRepository->countByPid($this->pid);
        $recordInfos['metadata']['numDefault'] = Initializer::countDefaults('MetadataDefaults.php');;

        $recordInfos['solrcore']['numCurrent'] = $this->solrCoreRepository->countByPid($this->pid);

        $this->view->assign('recordInfos', $recordInfos);

        return $this->htmlResponse();
    }

    /**
     * Error function - there is nothing to do at the moment.
     *
     * @access public
     *
     * @return void
     */
    // @phpstan-ignore-next-line
    public function errorAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    /**
     * Get language label for given key and language.
     * 
     * @access private
     *
     * @param string $index
     * @param string $lang
     * @param array $langArray
     *
     * @return string
     */
    private function getLLL(string $index, string $lang, array $langArray): string
    {
        if (isset($langArray[$lang][$index][0]['target'])) {
            return $langArray[$lang][$index][0]['target'];
        } elseif (isset($langArray['default'][$index][0]['target'])) {
            return $langArray['default'][$index][0]['target'];
        } else {
            return 'Missing translation for ' . $index;
        }
    }

    /**
     * Get records from file for given record type.
     *
     * @access private
     *
     * @param string $recordType
     *
     * @return array
     */
    private function getRecords(string $recordType): array
    {
        $filePath = GeneralUtility::getFileAbsFileName('EXT:dlf/Resources/Private/Data/' . $recordType . 'Defaults.json');
        if (file_exists($filePath)) {
            $fileContents = file_get_contents($filePath);
            $records = json_decode($fileContents, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $records;
            }
        }
        return [];
    }
}
