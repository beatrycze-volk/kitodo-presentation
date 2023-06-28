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

use Kitodo\Dlf\TableOfContent\Table;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Controller class for plugin 'Table Of Contents'.
 *
 * @author Sebastian Meyer <sebastian.meyer@slub-dresden.de>
 * @package TYPO3
 * @subpackage dlf
 * @access public
 */
class TableOfContentsController extends AbstractController
{
    /**
     * The main method of the plugin
     *
     * @return void
     */
    public function mainAction()
    {
        $this->view->assign('toc', $this->makeMenuArray());
    }

    /**
     * This builds a menu array for HMENU
     *
     * @access public
     * @return array HMENU array
     */
    public function makeMenuArray()
    {
        // Load current document.
        $this->loadDocument($this->requestData);
        if (
            $this->document === null
            || $this->document->getDoc() === null
        ) {
            // Quit without doing anything if required variables are not set.
            return [];
        } else {
            if (!empty($this->requestData['logicalPage'])) {
                $this->requestData['page'] = $this->document->getDoc()->getPhysicalPage($this->requestData['logicalPage']);
                // The logical page parameter should not appear again
                unset($this->requestData['logicalPage']);
            }
            // Set default values for page if not set.
            // $this->piVars['page'] may be integer or string (physical structure @ID)
            if (
                (int) $this->requestData['page'] > 0
                || empty($this->requestData['page'])
            ) {
                $this->requestData['page'] = MathUtility::forceIntegerInRange((int) $this->requestData['page'],
                    1, $this->document->getDoc()->numPages, 1);
            } else {
                $this->requestData['page'] = array_search($this->requestData['page'], $this->document->getDoc()->physicalStructure);
            }
            $this->requestData['double'] = MathUtility::forceIntegerInRange($this->requestData['double'],
                0, 1, 0);
        }
        $table = new Table($this->document, $this->documentRepository, $this->requestData, $this->settings);
        return $table->getTable();
    }
}
