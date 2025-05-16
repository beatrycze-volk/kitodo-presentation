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

namespace Kitodo\Dlf\Controller;

use Kitodo\Dlf\Common\SolrPaginator;
use Psr\Http\Message\ResponseInterface;
use Kitodo\Dlf\Domain\Repository\MetadataRepository;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller class for the plugin 'ListView'.
 *
 * @package TYPO3
 * @subpackage dlf
 *
 * @access public
 */
class ListViewController extends AbstractController
{
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
     * The main method of the plugin
     *
     * @access public
     *
     * @return ResponseInterface the response
     */
    public function mainAction(): ResponseInterface
    {
        // Quit without doing anything if required variables are not set.
        if (empty($this->settings['solrcore'])) {
            $this->logger->warning('Incomplete plugin configuration for SOLR. Please check the plugin settings for UID of SOLR core.');
            return $this->htmlResponse();
        }

        $search = $this->getParametersSafely('search');
        $search = is_array($search) ? array_filter($search, 'strlen') : [];

        $searchRequestData = GeneralUtility::_GPmerged('tx_dlf_search');

        if (isset($searchRequestData['search']) && is_array($searchRequestData['search'])) {
            $search = array_merge($search ?: [], $searchRequestData['search']);
            $this->request->getAttribute('frontend.user')->setKey('ses', 'search', $search);
        }

        // Get current page from request data because the parameter is shared between plugins
        $currentPage = $this->requestData['page'] ?? 1;

        // get all sortable metadata records
        $sortableMetadata = $this->metadataRepository->findByIsSortable(true);

        // get all metadata records to be shown in results
        $listedMetadata = $this->metadataRepository->findByIsListed(true);

        if (!empty($search)) {
            $solrResults = $this->documentRepository->findSolrWithoutCollection($this->settings, $search, $listedMetadata);

            $itemsPerPage = $this->settings['list']['paginate']['itemsPerPage'] ?? 25;

            $solrPaginator = new SolrPaginator($solrResults, $currentPage, $itemsPerPage);
            $simplePagination = new SimplePagination($solrPaginator);

            $pagination = $this->buildSimplePagination($simplePagination, $solrPaginator);
            $this->view->assignMultiple([ 'pagination' => $pagination, 'paginator' => $solrPaginator ]);
        }

        $this->view->assign('viewData', $this->viewData);
        $this->view->assign('countDocuments', !empty($solrResults) ? $solrResults->count() : 0);
        $this->view->assign('countResults', !empty($solrResults) ? $solrResults->getNumFound() : 0);
        $this->view->assign('page', $currentPage);
        $this->view->assign('lastSearch', $search);
        $this->view->assign('sortableMetadata', $sortableMetadata);
        $this->view->assign('listedMetadata', $listedMetadata);

        return $this->htmlResponse();
    }
}
