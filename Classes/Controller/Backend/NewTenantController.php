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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
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
     * @var string Backend Template Container
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

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
     * Initialization for all actions
     *
     * @access protected
     *
     * @return void
     */
    protected function initializeAction(): void
    {
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
     * @return void
     */
    public function addFormatAction(): void
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        Initializer::insertFormats($this->formatRepository, $persistenceManager);

        $this->forward('index');
    }

    /**
     * Action adding metadata records
     *
     * @access public
     *
     * @return void
     */
    public function addMetadataAction(): void
    {
        Initializer::insertMetadata($this->pid, $this->siteLanguages, $this->formatRepository, $this->metadataRepository);

        $this->forward('index');
    }

    /**
     * Action adding Solr core records
     *
     * @access public
     *
     * @return void
     */
    public function addSolrCoreAction(): void
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        Initializer::insertSolrCores($this->pid, $this->siteLanguages, $this->solrCoreRepository, $persistenceManager);

        $this->forward('index');
    }

    /**
     * Action adding structure records
     *
     * @access public
     *
     * @return void
     */
    public function addStructureAction(): void
    {
        Initializer::insertStructures($this->pid, $this->siteLanguages, $this->structureRepository);

        $this->forward('index');
    }

    /**
     * Set up the doc header properly here
     *
     * @access protected
     *
     * @param ViewInterface $view
     *
     * @return void
     */
    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($this->actionMethodName == 'indexAction') {
            $this->pageInfo = BackendUtility::readPageAccess($this->pid, $GLOBALS['BE_USER']->getPagePermsClause(1));
            $view->getModuleTemplate()->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
        }
        if ($view instanceof BackendTemplateView) {
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        }
    }

    /**
     * Main function of the module
     *
     * @access public
     *
     * @return void
     */
    public function indexAction(): void
    {
        $recordInfos = [];

        if ($this->pageInfo['doktype'] != 254) {
            $this->forward('error');
        }

        $recordInfos['formats']['numCurrent'] = $this->formatRepository->countAll();
        $recordInfos['formats']['numDefault'] = Initializer::countDefaults('FormatDefaults.php');

        $recordInfos['structures']['numCurrent'] = $this->structureRepository->countByPid($this->pid);
        $recordInfos['structures']['numDefault'] = Initializer::countDefaults('StructureDefaults.php');;

        $recordInfos['metadata']['numCurrent'] = $this->metadataRepository->countByPid($this->pid);
        $recordInfos['metadata']['numDefault'] = Initializer::countDefaults('MetadataDefaults.php');;

        $recordInfos['solrcore']['numCurrent'] = $this->solrCoreRepository->countByPid($this->pid);

        $this->view->assign('recordInfos', $recordInfos);
    }

    /**
     * Error function - there is nothing to do at the moment.
     *
     * @access public
     *
     * @return void
     */
    // @phpstan-ignore-next-line
    public function errorAction(): void
    {
        // TODO: Call parent::errorAction() when dropping support for TYPO3 v10.
    }
}
