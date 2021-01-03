<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminBaseGUI.php');

/**
 * GUI for Exam administration in group object
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilExamAdminCourseGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilExamAdminCourseGUI:
 */
class ilExamAdminCourseGUI extends ilExamAdminBaseGUI
{

	/** @var  int parent object ref_id */
	protected $parent_ref_id;

	/** @var  string parent object type */
	protected $parent_type;

	/** @var  string parent gui class */
	protected $parent_gui_class;

	/** @var  ilObjCourse $parent_obj */
	protected $parent_obj;

	/** @var ilExamAdminData */
	protected $data;

	/** @var ilExamAdminCourseUsers */
	protected $users;

	/**
	 * constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->ctrl->saveParameter($this, 'ref_id');

		$this->parent_ref_id = $_GET['ref_id'];
		$this->parent_type = 'grp';
		$this->parent_obj = ilObjectFactory::getInstanceByRefId($this->parent_ref_id);
		$this->parent_gui_class = ilObjectFactory::getClassByType($this->parent_type).'GUI';

		require_once(__DIR__ . '/class.ilExamAdminCourseUsers.php');
        require_once(__DIR__ . '/param/class.ilExamAdminData.php');

        $this->data = new ilExamAdminData($this->plugin, $this->parent_obj->getId());
		$this->users = new ilExamAdminCourseUsers($this->plugin, $this->parent_obj);
    }

    /**
     * Get the plugin object
     * @return ilExamAdminPlugin|null
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * Get the data object
     * @return ilExamAdminData
     */
    public function getData()
    {
        return $this->data;
    }

	/**
	 * Get the users object
	 * @return ilExamAdminCourseUsers
	 */
	protected function getUsers()
	{
		return $this->users;
	}


    /**
	* Handles all commands
	*/
	public function executeCommand()
	{
		$fallback_url = "goto.php?target=".$this->parent_type.'_'.$this->parent_ref_id;

        // Only Course administrators
		if (!$this->access->checkAccess('write','', $_GET['ref_id']))
		{
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->ctrl->redirectToURL($fallback_url);
		}

		$this->ctrl->saveParameter($this, 'ref_id');

		$next_class = $this->ctrl->getNextClass($this);
		switch ($next_class)
		{
//			case 'ilexamadminexample':
//				$this->prepareOutput();
//				$this->plugin->includeClass('class.ilExampleGUI.php');
//				$gui = new ilExamAdminExampleGUI();
//				$this->ctrl->forwardCommand($gui);
//				break;

			default:
                $cmd = $this->ctrl->getCmd('showOverview');

				switch ($cmd)
				{
                    case 'showOverview':
                    case 'listUsers':
                    case 'showUserSearch':
                    case 'addLecturer':
                    case 'addCorrector':
                    case 'addMember':
                    case 'activateUser':
                    case 'activateUsers':
                    case 'deactivateUser':
                    case 'deactivateUsers':
					case 'rewriteUser':
					case 'rewriteUserConfirm':
					case 'rewriteUserConfirmed':
                    case 'synchronizeUser':
                    case 'synchronizeUsers':
                    case 'showUserImportForm':
                    case 'showUserImportList':
                    case 'importUsersByList':
                    case 'listExams':
                        $this->$cmd();
                        break;

					default:
					    $this->prepareObjectOutput();
					    $this->tpl->setContent($cmd);
					    $this->tpl->show();
						break;
				}
		}
	}


    /**
     * Show an overview screen of the exam
     */
    protected function showOverview()
    {
        $this->plugin->includeClass('tables/class.ilExamAdminUserOverviewTableGUI.php');

        $table = new ilExamAdminUserOverviewTableGUI($this, 'showOverview');
        $table->setData($this->getUsers()->getOverviewData());

        $this->prepareObjectOutput();
        $this->tpl->setContent($table->getHTML());
        $this->tpl->show();
    }


    /**
     * Show an overview screen of the exam
     */
    protected function listUsers()
    {
        $this->prepareObjectOutput();

        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this,'showOverview'));
        $button->setCaption($this->plugin->txt('overview'), false);
        $this->toolbar->addButtonInstance($button);

        $this->ctrl->saveParameter($this, 'category');
        switch ($_GET['category'])
        {
            case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
                $this->addToolbarSearch($this->plugin->txt('addLecturer'));
                break;

            case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
                $this->addToolbarSearch($this->plugin->txt('addCorrector'));
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
                $this->addToolbarSearch($this->plugin->txt('addMember'));

                $this->toolbar->addSeparator();
                $button = ilLinkButton::getInstance();
                $button->setUrl($this->ctrl->getLinkTarget($this,'showUserImportForm'));
                $button->setCaption($this->plugin->txt('import_members_list'), false);
                $this->toolbar->addButtonInstance($button);
                break;
        }

        $usersObj = $this->getUsers();
        $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
        $table = new ilExamAdminUserListTableGUI($this, 'listUsers');
        $table->setTitle($this->plugin->txt($_GET['category']));
        $table->setData($usersObj->getCategoryUserData($_GET['category']));
        $table->setRowCommands($usersObj->getUserCommands($_GET['category']));
        $table->setLinkUser($this->plugin->hasAdminAccess());
        $this->ctrl->saveParameter($this, 'category');

        $this->tpl->setContent($table->getHTML());
        $this->tpl->show();
    }

    /**
     * Show a list of exams
     */
    protected function listExams()
    {
        $this->prepareObjectOutput();

        require_once (__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');
        $records = ilExamAdminOrgaRecord::getCollection()->get();
        $titles = [];
        /** @var ilExamAdminOrgaRecord $record */
        foreach ($records as $record)
        {
                $titles[] = $record->exam_title;
        }

        $this->tpl->setContent(implode('<br />', $titles));
        $this->tpl->show();
    }

    /**
     * Add the user search to the toolba
     * @param $caption
     */
    protected function addToolbarSearch($caption)
    {
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));
        $this->toolbar->addSeparator();

        $input = new ilTextInputGUI($this->plugin->txt('login_or_name'), 'pattern');
        $input->setDataSource($this->getAutocompleteUrl());
        $input->setDisableHtmlAutoComplete(true);
        $input->setSize(15);
        $this->toolbar->addInputItem($input);

        $button = ilSubmitButton::getInstance();
        $button->setCommand('showUserSearch');
        $button->setCaption($caption, false);
        $this->toolbar->addButtonInstance($button);

//        $this->toolbar->addSeparator();
//        $button = ilLinkButton::getInstance();
//        $button->setUrl($this->ctrl->getLinkTarget($this, 'listExams'));
//        $button->setCaption('list exams', false);
//        $this->toolbar->addButtonInstance($button);
    }


    /**
     * Initialize the search form for users
     * @param string $title
     * @param string $pattern
     * @return ilPropertyFormGUI
     */
    protected function initSearchForm($title, $pattern)
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($title);

        $input = new ilTextInputGUI($this->plugin->txt('login_or_name'), 'pattern');
        $input->setValue($pattern);
        $input->setDataSource($this->getAutocompleteUrl());
        $input->setDisableHtmlAutoComplete(true);
        $input->setSize(15);

        $form->addItem($input);

        $form->addCommandButton('showUserSearch', $this->lng->txt('search'));
        $form->addCommandButton('listUsers', $this->lng->txt('cancel'));

        return $form;
    }


    /**
     * Show the search form and search result for lecturers
     */
    protected function showUserSearch()
    {
        $this->prepareObjectOutput();

        // POST has precedence but GET is needed for table navigation
        $pattern = '';
        if (!empty($_POST['pattern']))
        {
            $pattern = $_POST['pattern'];
        }
        elseif (!empty($_GET['pattern']))
        {
            $pattern = $_GET['pattern'];
        }

        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->setParameter($this, 'pattern', urlencode($pattern));

        switch ($_GET['category'])
        {
            case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
				$searchCategory = ilExamAdminUsers::CAT_GLOBAL_LECTURER;
                $form = $this->initSearchForm($this->plugin->txt('addLecturer'), $pattern);
                $content = $form->getHTML();
                $commands = ['addLecturer'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
                $searchCategory = ilExamAdminUsers::CAT_GLOBAL_LECTURER;
                $form = $this->initSearchForm($this->plugin->txt('addCorrector'), $pattern);
                $content = $form->getHTML();
                $commands = ['addCorrector'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
				$searchCategory = ilExamAdminUsers::CAT_GLOBAL_PARTICIPANT;
                $form = $this->initSearchForm($this->plugin->txt('addMember'), $pattern);
                $content = $form->getHTML();
                $commands = ['addMember'];
                break;

			case ilExamAdminUsers::CAT_LOCAL_MEMBER_REGISTERED:
				$searchCategory = ilExamAdminUsers::CAT_GLOBAL_PARTICIPANT;
				$this->ctrl->saveParameter($this, 'orig_usr_id');
				$user = $this->getUsers()->getSingleUserDataById($_GET['orig_usr_id']);
				$title = sprintf($this->plugin->txt('rewrite_user_x'), $this->getUsers()->getUserDisplay($user));
				$form = $this->initSearchForm($title, $pattern);
				$content = $form->getHTML();
				$commands = ['rewriteUserConfirm'];
				break;
		}

        if ($pattern)
        {
            $usersObj = $this->getUsers();
            $internal = $usersObj->getUserDataByPattern($pattern, false, $searchCategory);

            // only one user found: add directly
            if (count($internal) == 1)
            {
				switch ($_GET['category']) {
					case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
						$this->ctrl->setParameter($this, 'usr_id', $internal[0]['usr_id']);
						$this->ctrl->redirect($this, 'addLecturer');
						break;
					case  ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
						$this->ctrl->setParameter($this, 'usr_id', $internal[0]['usr_id']);
						$this->ctrl->redirect($this, 'addMember');
						break;
				}
            }

            // show results of internal search
            $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
            $table1 = new ilExamAdminUserListTableGUI($this, 'showUserSearch');
            $table1->setTitle($this->plugin->txt('internal'));
            $table1->setData($internal);
            $table1->setIdParameter('usr_id');
            $table1->setRowCommands($commands);
            $table1->setLinkUser($this->plugin->hasAdminAccess());
            $content .= $table1->getHTML();

            // show results of external search
			// clear the usr_id parmeter of the previous table
			$this->ctrl->setParameter($this, 'usr_id', '');
            $connObj = $this->plugin->getConnector();
            $external = $connObj->getUserDataByPattern($pattern, false);
            $table2 = new ilExamAdminUserListTableGUI($this, 'showUserSearch');
            $table2->setTitle($this->plugin->txt('external'));
            $table2->setData($external);
            $table2->setIdParameter('conn_usr_id');
            $table2->setRowCommands($commands);
            $content .= $table2->getHTML();
        }

        $this->tpl->setContent($content);
        $this->tpl->show();
    }

    /**
     * Init the the form to import users by a list
     * @return ilPropertyFormGUI
     */
    protected function initUserImportForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt('import_members_list'));
        $form->setDescription($this->plugin->txt('import_members_desc'));

        $source = new ilRadioGroupInputGUI($this->plugin->txt('source'), 'source');
        $source->setValue($this->data->get('source'));

        $sm = new ilRadioOption($this->plugin->txt('source_matriculations'), 'matriculations');
        $matriculations = new ilTextAreaInputGUI('', 'matriculations');
        $matriculations->setInfo($this->plugin->txt('matriculations_format'));
        $sm->addSubItem($matriculations);
        $source->addOption($sm);

        $sr =  new ilRadioOption($this->plugin->txt('source_ref_id'), 'ref_id');
        $ref_id = new ilNumberInputGUI($this->plugin->txt('ref_id'), 'ref_id');
        $ref_id->allowDecimals(false);
        $ref_id->setSize(10);
        $ref_id->setInfo($this->plugin->txt('ref_id_info'));
        $ref_id->setValue($this->data->get('source_ref_id'));
        $sr->addSubItem($ref_id);
        $source->addOption($sr);

        $form->addItem($source);

        $form->addCommandButton('showUserImportList', $this->plugin->txt('list_users'));
        $form->addCommandButton('listUsers', $this->lng->txt('cancel'));

        return $form;

    }

    /**
     * Show the form to import users by a list
     */
    protected function showUserImportForm()
    {
        $this->prepareObjectOutput();
        $this->ctrl->saveParameter($this, 'category');

        $form = $this->initUserImportForm();
        $this->tpl->setContent($form->getHTML());
        $this->tpl->show();
    }

    /**
     * Show the list of users
     * @throws ilDatabaseException
     */
    protected function showUserImportList()
    {
        $this->prepareObjectOutput();
        $this->ctrl->saveParameter($this, 'category');

        $connObj = $this->plugin->getConnector();

        $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
        $table = new ilExamAdminUserListTableGUI($this, 'showUserImportList');
        $table->setTitle($this->plugin->txt('external'));
        $table->setShowCheckboxes(true);
        $table->addCommandButton('listUsers', $this->lng->txt('cancel'));

        $external = [];
        $info = [];

        switch ($_POST['source'])
        {
            case 'matriculations':
                $this->data->set('source', 'matriculations');
                $this->data->write();

                $list = $connObj->getArrayFromListInput($_POST['matriculations']);
                $info[] = sprintf($this->plugin->txt('x_matriculations_searched'), count($list));
                $external = $connObj->getUserDataByMatriculationList($list);
                break;

            case 'ref_id':
                $ref_id = (int) $_POST['ref_id'];
                $this->data->set('source', 'ref_id');
                $this->data->set('source_ref_id', $ref_id);
                $this->data->write();

                $object = $connObj->getObjectDataByRefId($ref_id);
                if (is_array($object) && ($object['type'] == 'crs' || $object['type'] == 'grp'))
                {
                    $type = $object['type'];
                    $info[] = sprintf($this->plugin->txt('members_of_'.$type), $object['title']);
                    $external = $connObj->getUserDataByMembership($ref_id, $type);
                }
                else
                {
                    ilUtil::sendFailure($this->plugin->txt('no_membership_object'), true);
                    $this->ctrl->redirect($this, 'showUserImportForm');
                }
                break;

            default:
                // table sorting does not produce a POST
                if (is_array($_SESSION['showUserImportList']))
                {
                    $external = $connObj->getUserDataByIds($_SESSION['showUserImportList']);
                }
        }
        $_SESSION['showUserImportList'] = $connObj->extractUserIds($external);

        $table->setData($external);
        $table->addMultiCommand('importUsersByList', $this->plugin->txt('import_members'));

        $info[] = sprintf($this->plugin->txt('x_users_found'), count($external));
        ilUtil::sendInfo(implode('<br />', $info));

        $this->tpl->setContent($table->getHTML());
        $this->tpl->show();
    }

    /**
     * Import the users by a posted list of ids
     * @throws Exception
     */
    protected function importUsersByList()
    {
        unset($_SESSION['showUserImportList']);

        $added = [];
        if (is_array($_POST['usr_id']))
        {
			$added = $this->getUsers()->addMembers($_POST['usr_id'], false);
        }

        if ($added)
        {
            ilUtil::sendSuccess($this->plugin->txt('members_added_to_course'). '<br />' . implode('<br />', $added), true);
        }

        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Add a lecturer
     * @throws Exception
     */
    protected function addLecturer()
    {
        $added = [];
        if ($_GET['usr_id'])
        {
           	$added = $this->getUsers()->addLecturers([$_GET['usr_id']], true);
        }
        elseif ($_GET['conn_usr_id'])
        {
        	$added = $this->getUsers()->addLecturers([$_GET['conn_usr_id']], false);
		}

        if (!empty($added))
        {
            ilUtil::sendSuccess($this->plugin->txt('lecturer_added_to_course'). '<br />' . implode('<br />', $added), true);
        }
        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }

    /**
     * Add a Corrector
     * @throws Exception
     */
    protected function addCorrector()
    {
        $added = [];
        if ($_GET['usr_id'])
        {
            $added = $this->getUsers()->addCorrectors([$_GET['usr_id']], true);
        }
        elseif ($_GET['conn_usr_id'])
        {
            $added = $this->getUsers()->addCorrectors([$_GET['conn_usr_id']], false);
        }

        if (!empty($added))
        {
            ilUtil::sendSuccess($this->plugin->txt('corrector_added_to_course'). '<br />' . implode('<br />', $added), true);
        }
        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }



    /**
     * Add a member
     * @throws Exception
     */
    protected function addMember()
    {
        $added = [];
		if ($_GET['usr_id'])
		{
			$added = $this->getUsers()->addMembers([$_GET['usr_id']], true);
		}
		elseif ($_GET['conn_usr_id'])
		{
			$added = $this->getUsers()->addMembers([$_GET['conn_usr_id']], false);
		}

        if (!empty($added))
        {
            ilUtil::sendSuccess($this->plugin->txt('member_added_to_course'). '<br />' . implode('<br />', $added), true);
        }

        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Activate the users of a category
     */
    protected function activateUsers()
    {
        $this->getUsers()->setActiveByCategory($_GET['category'], 1);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Activate the users of a category
     */
    protected function activateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsers()->setActiveByUserId($_GET['usr_id'], 1);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Deactivate the users of a category
     */
    protected function deactivateUsers()
    {
        $this->getUsers()->setActiveByCategory($_GET['category'], 0);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Deactivate the users of a category
     */
    protected function deactivateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsers()->setActiveByUserId($_GET['usr_id'], 0);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Synchronize the users of a category
     * @throws ilException
     */
    protected function synchronizeUsers()
    {
        $count = $this->getUsers()->synchronizeByCategory($_GET['category']);
        if ($count > 0) {
        	ilUtil::sendSuccess(sprintf($this->plugin->txt('x_users_synchronized'), $count), true);
		}
		else {
			ilUtil::sendFailure($this->plugin->txt('no_users_synchronized'), true);
		}
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Synchronize a single user
     * @throws ilException
     */
    protected function synchronizeUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsers()->synchronizeByUserId($_GET['usr_id']);
        $this->ctrl->redirect($this, 'listUsers');
    }

	/**
	 * Rename a user
	 */
    protected function rewriteUser()
	{
		$usersObj =  new ilExamAdminCourseUsers($this->plugin, $this->parent_obj);

		$pattern = '';
		$user = $usersObj->getSingleUserDataById( $_GET['usr_id']);
		if (isset($user))
		{
			if (!empty($user['matriculation'])) {
				$pattern = $user['matriculation'];
			}
			elseif (!empty($user['firstname']) && !empty($user['lastname'])) {
				$pattern = $user['firstname'] . ' ' . $user['lastname'];
			}
			elseif (!empty($user['lastname'])) {
				$pattern = $user['lastname'];
			}
		}

		$this->ctrl->saveParameter($this, 'category');
		$this->ctrl->setParameter($this, 'orig_usr_id', $_GET['usr_id']);
		$this->ctrl->setParameter($this, 'pattern', urlencode($pattern));

		$this->ctrl->redirect($this, 'showUserSearch');
	}

	/**
	 * Show Confirmation for rewriting a user
	 * @throws Exception
	 */
	protected function rewriteUserConfirm()
	{
		$this->ctrl->saveParameter($this, 'category');

		if ($_GET['usr_id']) {
			$check = $this->getUsers()->rewriteUser($_GET['orig_usr_id'], $_GET['usr_id'], true, false);
		}
		elseif ($_GET['conn_usr_id']) {
			$check = $this->getUsers()->rewriteUser($_GET['orig_usr_id'], $_GET['conn_usr_id'], false, false);
		}

		if (empty($check['new'])) {
			ilUtil::sendFailure($this->plugin->txt('rewrite_not_found'), true);
			$this->ctrl->redirect($this, 'listUsers');
		}

		if (!empty($check['conflicts'])) {
			$message = sprintf($this->plugin->txt('rewrite_conflict'), $this->getUsers()->getUserDisplay($check['new']));
			foreach ($check['conflicts'] as $ref_id => $title) {
				$message .= '<br /><a href="' . ilLink::_getLink($ref_id). '">'. $title.'</a>';
			}
			ilUtil::sendFailure($message, true);
			$this->ctrl->redirect($this, 'listUsers');
		}

		$this->ctrl->saveParameter($this, 'usr_id');
		$this->ctrl->saveParameter($this, 'orig_usr_id');
		$this->ctrl->saveParameter($this, 'conn_usr_id');

		$this->prepareObjectOutput();
		$confGui = new ilConfirmationGUI();
		$confGui->setFormAction($this->ctrl->getFormAction($this));
		$confGui->setConfirm($this->plugin->txt('rewriteUser'), 'rewriteUserConfirmed');
		$confGui->setCancel($this->lng->txt('cancel'), 'listUsers');
		$confGui->setHeaderText(sprintf($this->plugin->txt('rewrite_transfer'),
				$this->getUsers()->getUserDisplay($check['orig']), $this->getUsers()->getUserDisplay($check['new']))
			.'<p class="small">'.$this->plugin->txt('rewrite_transfer_info').'</p>');

		$this->tpl->setContent($confGui->getHTML());
		$this->tpl->show();
	}

	/**
	 * Actually rewrite a user
	 * @throws Exception
	 */
	protected function rewriteUserConfirmed()
	{
		$this->ctrl->saveParameter($this, 'category');

		if ($_GET['usr_id']) {
			$check = $this->getUsers()->rewriteUser($_GET['orig_usr_id'], $_GET['usr_id'], true, true);
		}
		elseif ($_GET['conn_usr_id']) {
			$check = $this->getUsers()->rewriteUser($_GET['orig_usr_id'], $_GET['conn_usr_id'], false, true);
		}

		if (!$check['done']) {
			ilUtil::sendFailure(sprintf($this->plugin->txt('rewrite_failed'),
				$this->getUsers()->getUserDisplay($check['orig'])), true);
		}
		else {
			ilUtil::sendSuccess(sprintf($this->plugin->txt('rewrite_done'),
				$this->getUsers()->getUserDisplay($check['orig']), $this->getUsers()->getUserDisplay($check['new'])), true);
		}

		$this->ctrl->redirect($this, 'listUsers');

	}

    /**
     * Prepare the header, tabs etc.
     */
    protected function prepareObjectOutput()
    {
        global $DIC;

        /** @var ilLocatorGUI $ilLocator */
        $ilLocator = $DIC['ilLocator'];

        $ilLocator->addRepositoryItems($this->parent_obj->getRefId());
        $ilLocator->addItem($this->parent_obj->getTitle(), ilLink::_getLink($this->parent_ref_id, $this->parent_type));

        $this->tpl->getStandardTemplate();
        $this->tpl->setLocator();
        $this->tpl->setTitle($this->parent_obj->getPresentationTitle());
        $this->tpl->setDescription($this->parent_obj->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon('', 'big', $this->parent_type), $this->lng->txt('obj_'.$this->parent_type));
    }


    protected function getAutocompleteUrl()
    {
        return $this->ctrl->getLinkTargetByClass(['ilrepositorygui','ilobjcoursegui','ilcoursemembershipgui', 'ilrepositorysearchgui'],
            'doUserAutoComplete', '', true,false);
    }



}
