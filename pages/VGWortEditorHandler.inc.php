 <?php

/**
 * @file pages/VGWortEditorHandler.inc.php
 *
 * Author: Božana Bokan, Center for Digital Systems (CeDiS), Freie Universität Berlin
 * Last update: September 25, 2015
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package plugins.generic.vgWort
 * @class VGWortEditorHandler
 *
 * @brief Handle requests for editor VG Wort functions.
 */

import('classes.handler.Handler');

class VGWortEditorHandler extends Handler {

	/**
	 * Display pixel tag listing pages.
	 */
	function pixelTags($args = array(), &$request) {
		$this->setupTemplate($request);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
		$vgWortPlugin->import('classes.PixelTag');
		$vgWortPlugin->import('classes.VGWortEditorAction');
		$vgWortEditorAction = new VGWortEditorAction();

		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');

		$searchField = null;
		$searchMatch = null;
		$search = $request->getUserVar('search');
		if (!empty($search)) {
			$searchField = $request->getUserVar('searchField');
			$searchMatch = $request->getUserVar('searchMatch');
		}

		$path = !isset($args) || empty($args) ? null : $args[0];

		// sort the result by pixel_tag_id per default
		$sortBy = 'pixel_tag_id';
		$sortDirection = SORT_DIRECTION_ASC;

		$isError = false;
		$errors = array();
		switch($path) {
			case 'available':
				$action = (string) $request->getUserVar('action');
				if ($action == 'order') {
					$count = (int) $request->getUserVar('count');
					if ($count > 0 && $count <= 100) {
						// order
						$orderResult = $vgWortEditorAction->orderPixel($journalId, $count);
						$isError = !$orderResult[0];
						if ($isError) {
							$errors[] = $orderResult[1];
						} else {
							// insert ordered pixel tags in the db
							$vgWortEditorAction->insertOrderedPixel($journalId, $orderResult[1]);
						}
					} else {
						$isError = true;
						$errors[] = __('plugins.generic.vgWort.order.count');
					}
				}
				$status = PT_STATUS_AVAILABLE;
				$template = 'pixelTagsAvailable.tpl';
				break;
			case 'unregistered':
				$action = (string) $request->getUserVar('action');
				if ($action == 'register') {
					$pixelTagId = (int) $request->getUserVar('pixelTagId');
					$pixelTag = & $pixelTagDao->getPixelTag($pixelTagId, $journalId);
					// the pixel tag exists, it is unregistered and not removed
					if (isset($pixelTag) && $pixelTag->getStatus() == PT_STATUS_UNREGISTERED && !$pixelTag->getDateRemoved()) {
						// check if the requirements for the registration are fulfilled
						$checkResult = $vgWortEditorAction->check($pixelTag);
						$isError = !$checkResult[0];
						if ($isError) {
							$errors[] = $checkResult[1];
						} else {
							// register
							$registerResult = $vgWortEditorAction->newMessage($pixelTagId, $request);
							$isError = !$registerResult[0];
							$errors[] = $registerResult[1];
							if (!$isError) {
								// update the registered pixel tag
								$pixelTag->setDateRegistered(Core::getCurrentDate());
								$pixelTag->setStatus(PT_STATUS_REGISTERED);
								$pixelTagDao->updateObject($pixelTag);
								// send a notification email to the authors
								$vgWortEditorAction->notifyAuthors($journal, $pixelTag);
							}
						}
					}
				}
				$status = PT_STATUS_UNREGISTERED;
				$template = 'pixelTagsUnregistered.tpl';
				break;
			case 'registered':
				$status = PT_STATUS_REGISTERED;
				$sortBy = 'date_registered';
				$sortDirection = SORT_DIRECTION_DESC;
				$template = 'pixelTagsRegistered.tpl';
				break;
			default:
				$path = '';
				$status = null;
				$sortDirection = SORT_DIRECTION_DESC;
				$template = 'pixelTagsAll.tpl';
		}

		$rangeInfo =& Handler::getRangeInfo('pixelTags');
		$pixelTags =& $pixelTagDao->getPixelTagsByJournalId($journalId, $searchField, $search, $searchMatch, $status, $rangeInfo, $sortBy, $sortDirection);

		$pixelTagsCounts =& $pixelTagDao->getStatusCounts($journalId);
		$vgWortPixelTagMin = $vgWortPlugin->getSetting($journal->getId(), 'vgWortPixelTagMin');

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('pixelTags', $pixelTags);
		$templateMgr->assign_by_ref('pixelTagsCounts', $pixelTagsCounts);
		$templateMgr->assign('vgWortPixelTagMin', $vgWortPixelTagMin);
		$templateMgr->assign('rangeInfo', $rangeInfo);
		$templateMgr->assign('errors', $errors);
		$templateMgr->assign('isError', $isError);

		// Set search parameters
		$duplicateParameters = array(
			'searchField', 'searchMatch', 'search'
		);
		foreach ($duplicateParameters as $param)
			$templateMgr->assign($param, $request->getUserVar($param));

		$fieldOptions = Array(
			PT_FIELD_PRIVCODE => 'plugins.generic.vgWort.pixelTag.privateCode',
			PT_FIELD_PUBCODE => 'plugins.generic.vgWort.pixelTag.publicCode'
		);
		$templateMgr->assign('fieldOptions', $fieldOptions);

		$templateMgr->display($vgWortPlugin->getTemplatePath() . $template);
	}

	/**
	 * Delete an available pixel tag.
	 */
	function deletePixelTag($args = array(), &$request) {
		$pixelTagId = (int) array_shift($args);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');
		$pixelTag =& $pixelTagDao->getPixelTag($pixelTagId, $journalId);
		if (isset($pixelTag) && $pixelTag->getStatus() == PT_STATUS_AVAILABLE) {
			$pixelTagDao->deleteObject($pixelTag);
		}
		$request->redirect(null, null, 'pixelTags', 'available');
	}

	/**
	 * Display VG Wort statistics.
	 */
	function pixelStatistics($args = array(), &$request) {
		$this->setupTemplate($request);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
		$vgWortPlugin->import('classes.VGWortEditorAction');
		$vgWortEditorAction = new VGWortEditorAction();
		// get VG Wort statistics
		$qualityControlResult = $vgWortEditorAction->qualityControl($journalId);
		$isError = !$qualityControlResult[0];
		$errors = null;
		if ($isError) {
			$errors = array($qualityControlResult[1]);
		} else {
			$qualityControlResultObject =& $qualityControlResult[1];
		}
		$qualityControlValues = $orderedPixelTillToday = $startedPixelTillToday = null;
		if (isset($qualityControlResultObject)) {
			$qualityControlValues = $qualityControlResultObject->qualityControlValues;
			$orderedPixelTillToday = $qualityControlResultObject->orderedPixelTillToday;
			$startedPixelTillToday = $qualityControlResultObject->startedPixelTillToday;
		}

		// get other information to display
		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');
		$pixelTagsCounts =& $pixelTagDao->getStatusCounts($journalId);
		$vgWortPixelTagMin = $vgWortPlugin->getSetting($journal->getId(), 'vgWortPixelTagMin');

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('qualityControlValues', $qualityControlValues);
		$templateMgr->assign('orderedPixelTillToday', $orderedPixelTillToday);
		$templateMgr->assign('startedPixelTillToday', $startedPixelTillToday);
		$templateMgr->assign('errors', $errors);
		$templateMgr->assign('isError', $isError);
		$templateMgr->assign_by_ref('pixelTagsCounts', $pixelTagsCounts);
		$templateMgr->assign('vgWortPixelTagMin', $vgWortPixelTagMin);
		$templateMgr->display($vgWortPlugin->getTemplatePath() . 'pixelTagsStat.tpl');
	}

	/**
	 * Create a pixel tag manually i.e. insert already registered pixel tag
	 * (e.g. when moving from one to the other system).
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function createPixelTag($args = array(), &$request) {
		$this->setupTemplate($request);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
		$vgWortPlugin->import('classes.form.PixelTagForm');
		$pixelTagForm = new PixelTagForm($vgWortPlugin, $journalId);
		$pixelTagForm->initData();
		$pixelTagForm->display($request);
	}

	/**
	 * Save the manually created/inserted pixel tag.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function savePixelTag($args, &$request) {
		$this->setupTemplate($request);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
		$vgWortPlugin->import('classes.form.PixelTagForm');
		$pixelTagForm = new PixelTagForm($vgWortPlugin, $journalId);
		$pixelTagForm->readInputData();
		if ($pixelTagForm->validate()) {
			$pixelTagForm->execute();
			$request->redirect(null, null, 'pixelTags', 'registered');
		} else {
			$pixelTagForm->display($request);
		}
	}

	/**
	 * Assign a pixel tag to a text.
	 */
	function assignPixelTag($args = array(), &$request) {
		$articleId = (int) array_shift($args);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$article =& $articleDao->getArticle($articleId, $journalId, true);
		if (isset($article)) {
			// check if there is a card number
			$cardNoExists = false;
			foreach ($article->getAuthors() as $author) {
				$cardNo = $author->getData('cardNo');
				if (!empty($cardNo)) {
					$cardNoExists = true;
				}
			}
			if (!$cardNoExists) {
				$request->redirect(null, null, 'submission', array($articleId), array('errorCode'=>1), 'vgWort');
			}
			// assign
			$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
			$vgWortPlugin->import('classes.VGWortEditorAction');
			$vgWortEditorAction = new VGWortEditorAction();
			$vgWortTextType = (int) $request->getUserVar('vgWortTextType');
			$assigned = $vgWortEditorAction->assignPixelTag($journal, $articleId, $vgWortTextType);
			if (!$assigned) {
				$request->redirect(null, null, 'submission', array($articleId), array('errorCode'=>2), 'vgWort');
			}
			$request->redirect(null, null, 'submission', array($articleId), null, 'vgWort');
		}
		$request->redirect(null, null, 'submissions');
	}

	/**
	 * Remove a pixel tag -- will remove it from the galleys, so the tracking will stop.
	 */
	function removePixelTag($args = array(), &$request) {
		$pixelTagId = (int) array_shift($args);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');
		$pixelTag =& $pixelTagDao->getPixelTag($pixelTagId, $journalId);
		if ($pixelTag && $pixelTag->getStatus() != PT_STATUS_AVAILABLE) {
			$pixelTag->setDateRemoved(Core::getCurrentDate());
			$pixelTagDao->updateObject($pixelTag);
			$request->redirect(null, null, 'submission', array($pixelTag->getArticleId()), null, 'vgWort');
		}
		$request->redirect(null, null, 'submissions');
	}

	/**
	 * Insert the pixel tag in the galley again.
	 */
	function reinsertPixelTag($args = array(), &$request) {
		$pixelTagId = (int) array_shift($args);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');
		$pixelTag =& $pixelTagDao->getPixelTag($pixelTagId, $journalId);
		if ($pixelTag && $pixelTag->getDateRemoved()) {
			$pixelTag->setDateRemoved(NULL);
			$pixelTagDao->updateObject($pixelTag);
			$request->redirect(null, null, 'submission', array($pixelTag->getArticleId()), null, 'vgWort');
		}
		$request->redirect(null, null, 'submissions');
	}

	/**
	 * Change the text type for a pixel tag.
	 */
	function changeTextType($args = array(), &$request) {
		$pixelTagId = (int) array_shift($args);

		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		$journalId = $journal->getId();

		$pixelTagDao =& DAORegistry::getDAO('PixelTagDAO');
		$pixelTag =& $pixelTagDao->getPixelTag($pixelTagId, $journalId);
		if($pixelTag && $pixelTag->getStatus() != PT_STATUS_REGISTERED) {
			$vgWortTextType = $request->getUserVar('vgWortTextType') ? (int) $request->getUserVar('vgWortTextType') : null;
			if(isset($vgWortTextType)) {
				$pixelTag->setTextType($vgWortTextType);
				$pixelTagDao->updateObject($pixelTag);
			}
			$request->redirect(null, null, 'submission', array($pixelTag->getArticleId()), null, 'vgWort');
		}
		$request->redirect(null, null, 'submissions');
	}

	/**
	 * Ensure that we have a journal, the plugin is enabled, and the user is editor selected in the plugin settings.
	 */
	function authorize(&$request, &$args, $roleAssignments) {
		$router =& $request->getRouter();
		$journal =& $router->getContext($request);
		if (!isset($journal)) return false;

		$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);

		if (!isset($vgWortPlugin)) return false;

		if (!$vgWortPlugin->getEnabled()) return false;

		if (!Validation::isEditor($journal->getId())) Validation::redirectLogin();
		// consider editors from the plugin settings
		$editors = $vgWortPlugin->getSetting($journal->getId(), 'vgWortEditors');
		$sessionManager =& SessionManager::getManager();
		$session =& $sessionManager->getUserSession();
		if (!in_array($session->getUserId(), $editors)) Validation::redirectLogin();

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Setup common template variables.
	 * @param $subclass boolean set to true if caller is below this handler in the hierarchy
	 */
	function setupTemplate(&$request, $subclass = false) {
		$templateMgr =& TemplateManager::getManager();
		$pageCrumbs = array(
			array(
				$request->url(null, 'editor'),
				'user.role.editor'
			),
			array(
				$request->url(null, 'editor', 'pixelTags'),
				'plugins.generic.vgWort.editor.vgWort'
			)
		);
		if ($subclass) {
			$returnPage = $request->getUserVar('returnPage');

			if ($returnPage != null) {
				$validPages =& $this->getValidReturnPages();
				if (!in_array($returnPage, $validPages)) {
					$returnPage = null;
				}
			}
			$pageCrumbs[] = array(
				$request->url(null, 'editor', 'pixelTags', $returnPage),
				__('plugins.generic.vgWort.editor.vgWort'),
				true
			);
		}
		$templateMgr->assign('pageHierarchy', $pageCrumbs);
		$templateMgr->assign('helpTopicId','plugins.generic.VGWortPlugin');
		//$vgWortPlugin =& PluginRegistry::getPlugin('generic', VGWORT_PLUGIN_NAME);
		//$templateMgr->addStyleSheet($request->getBaseUrl() . '/' . $vgWortPlugin->getStyleSheet());
	}
}

?>
