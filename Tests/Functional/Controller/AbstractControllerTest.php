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

namespace Kitodo\Dlf\Tests\Functional\Controller;

use Kitodo\Dlf\Common\Solr\Solr;
use Kitodo\Dlf\Controller\AbstractController;
use Kitodo\Dlf\Domain\Model\Document;
use Kitodo\Dlf\Domain\Model\SolrCore;
use Kitodo\Dlf\Domain\Repository\DocumentRepository;
use Kitodo\Dlf\Domain\Repository\SolrCoreRepository;
use Kitodo\Dlf\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\View\GenericViewResolver;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractControllerTest extends FunctionalTestCase
{
    protected int $currentSolrUid = 1;

    protected string $currentCoreName = '';

    /**
     * Set up the data for the test.
     *
     * @access protected
     *
     * @param array $databaseFixtures
     *
     * @return void
     */
    protected function setUpData(array $databaseFixtures): void
    {
        foreach ($databaseFixtures as $filePath) {
            $this->importCSVDataSet($filePath);
        }
        $this->persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $documentRepository = $this->initializeRepository(DocumentRepository::class, 0);

        $allFixtureDocuments = $documentRepository->findAll();
        foreach ($allFixtureDocuments as $document) {
            /* @var Document $document */
            $document->setSolrCore($this->currentSolrUid);
            $documentRepository->update($document);
        }
        $this->persistenceManager->persistAll();
    }

    /**
     * Set up the request for the test.
     *
     * @access protected
     *
     * @param string $actionName
     * @param array $arguments
     *
     * @return void
     */
    protected function setUpRequest(string $actionName, array $arguments = []): Request
    {
        $request = new Request();
        $request->setControllerActionName($actionName);
        $request->setArguments($arguments);
        return $request;
    }

    /**
     * Set up the controller for the test.
     *
     * @access protected
     *
     * @template T
     *
     * @param class-string<T> $class The fully qualified class name of the repository
     * @param array $settings
     * @param string $templateHtml
     *
     * @return T
     */
    protected function setUpController(string $class, array $settings, string $templateHtml = ''): AbstractController
    {
        $view = new StandaloneView();
        $view->setTemplateSource($templateHtml);

        $controller = $this->get($class);
        $viewResolverMock = $this->getMockBuilder(GenericViewResolver::class)
            ->disableOriginalConstructor()->getMock();
        $viewResolverMock->expects(self::once())->method('resolve')->willReturn($view);
        $controller->injectViewResolver($viewResolverMock);
        $controller->setSettingsForTest($settings);
        return $controller;
    }

}
