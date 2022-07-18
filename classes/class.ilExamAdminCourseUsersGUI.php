<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminBaseGUI.php');
require_once(__DIR__ . '/class.ilExamAdminCourseUsers.php');
require_once(__DIR__ . '/param/class.ilExamAdminData.php');

/**
 * GUI for Exam administration in group object
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilExamAdminCourseUsersGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilExamAdminCourseUsersGUI:
 */
class ilExamAdminCourseUsersGUI extends ilExamAdminBaseGUI
{
    /**
     * Course to which the functions relate (equal to parent or group's course)
     * @var ilObjCourse
     */
    protected $course;

    /**
     * Exam related specific data (bound to obj_id of course)
     * @var ilExamAdminData
     */
	protected $data;

    /**
     * User administration for the exam
     * @var ilExamAdminCourseUsers
     */
	protected $users;

    /**
     * ilExamAdminBaseGUI constructor
     * @param ilExamAdminMainGUI $mainGUI
     */
    public function __construct($mainGUI = null)
	{
		parent::__construct($mainGUI);

		$this->course = $this->mainGUI->getCourse();
        $this->data = new ilExamAdminData($this->plugin, $this->course->getId());
		$this->users = new ilExamAdminCourseUsers($this->plugin, $this->course);
    }


    /**
	* Handles all commands
	*/
	public function executeCommand()
	{
        $this->ctrl->saveParameter($this, 'ref_id');

		$next_class = $this->ctrl->getNextClass($this);
		switch ($next_class)
		{
//			case 'ilexamadminexample':
//				$this->mainGUI->prepareOutput();
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
                    case 'addParticipant':
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
                    case 'removeUser':
                    case 'removeUserConfirmed':
                        $this->$cmd();
                        break;

					default:
					    $this->mainGUI->prepareObjectOutput();
					    $this->tpl->setContent($cmd);
					    $this->tpl->printToStdout();
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
        $table->setData($this->users->getOverviewData());

        ilUtil::sendInfo($this->plugin->txt('list_users_info'));

        $this->mainGUI->prepareObjectOutput();
        $this->tpl->setContent($table->getHTML());
        $this->tpl->printToStdout();
    }


    /**
     * Show an overview screen of the exam
     */
    protected function listUsers()
    {
        $this->mainGUI->prepareObjectOutput();
        $this->addToolbarUserList();

        $this->ctrl->saveParameter($this, 'category');
        switch ($_GET['category'])
        {
            case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
                $this->addToolbarSearch($this->plugin->txt('add_lecturer'));
                break;
            case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
                $this->addToolbarSearch($this->plugin->txt('add_corrector'));
                break;
            case ilExamAdminUsers::CAT_LOCAL_MEMBER_TESTACCOUNT:
                $this->addToolbarSearch($this->plugin->txt('add_testaccount'));
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
                $this->addToolbarSearch($this->plugin->txt('add_member'));

                $this->toolbar->addSeparator();
                $button = ilLinkButton::getInstance();
                $button->setUrl($this->ctrl->getLinkTarget($this,'showUserImportForm'));
                $button->setCaption($this->plugin->txt('import_members_list'), false);
                $this->toolbar->addButtonInstance($button);
                break;
        }

        $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
        $table = new ilExamAdminUserListTableGUI($this, 'listUsers');
        $table->setFormAction($this->ctrl->getFormAction($this));
        $table->setTitle($this->plugin->txt($_GET['category']. ''));
        $table->setDescription($this->plugin->txt($_GET['category'] . '_info'));
        $table->setData($this->users->getCategoryUserData($_GET['category']));
        $table->setRowCommands($this->users->getUserCommands($_GET['category']));
        $table->setLinkUser($this->plugin->hasAdminAccess());
        $table->setIdParameter('usr_id');
        $table->setShowCheckboxes(true);
        $table->addMultiCommand('removeUser', $this->plugin->txt('removeUser'));
        $this->ctrl->saveParameter($this, 'category');

        $explanation = '';
        if ($_GET['category'] != ilExamAdminUsers::CAT_LOCAL_MEMBER_REGISTERED) {
            $tpl = $this->plugin->getTemplate('tpl.il_exam_admin_user_list_explanation.html');
            $tpl->setVariable('EXPLAIN_USER_ADMIN', $this->plugin->txt('explain_user_admin'));
            $tpl->setVariable('EXPLAIN_AUTO_TESTACCOUNTS', $this->plugin->txt('explain_auto_testaccounts'));
            $tpl->setVariable('EXPLAIN_USER_ACTIONS', $this->plugin->txt('explain_user_actions'));
            $explanation = $tpl->get();
        }

        $this->tpl->setContent($explanation . $table->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Add the tollbar items for a user list
     */
    protected function addToolbarUserList()
    {
        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this,'showOverview'));
        $button->setCaption('« ' . $this->plugin->txt('overview'), false);
        $this->toolbar->addButtonInstance($button);
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
        $button->setCaption($this->plugin->txt('search_and_add'), false);
        $this->toolbar->addButtonInstance($button);
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
        $form->setDescription($this->plugin->txt('search_pattern'));

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
        $this->mainGUI->prepareObjectOutput();
        $this->addToolbarUserList();

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
                $withTestAccounts = false;
                $form = $this->initSearchForm($this->plugin->txt('addLecturer'), $pattern);
                $content = $form->getHTML();
                $commands = ['addParticipant'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
                $searchCategory = ilExamAdminUsers::CAT_GLOBAL_LECTURER;
                $withTestAccounts = false;
                $form = $this->initSearchForm($this->plugin->txt('addCorrector'), $pattern);
                $content = $form->getHTML();
                $commands = ['addParticipant'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_TESTACCOUNT:
                $searchCategory = ilExamAdminUsers::CAT_GLOBAL_LECTURER;
                $withTestAccounts = true;
                $form = $this->initSearchForm($this->plugin->txt('addTestaccount'), $pattern);
                $content = $form->getHTML();
                $commands = ['addParticipant'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
				$searchCategory = ilExamAdminUsers::CAT_GLOBAL_PARTICIPANT;
                $withTestAccounts = false;
                $form = $this->initSearchForm($this->plugin->txt('addMember'), $pattern);
                $content = $form->getHTML();
                $commands = ['addParticipant'];
                break;

			case ilExamAdminUsers::CAT_LOCAL_MEMBER_REGISTERED:
				$searchCategory = ilExamAdminUsers::CAT_GLOBAL_PARTICIPANT;
                $withTestAccounts = false;
				$this->ctrl->saveParameter($this, 'orig_usr_id');
				$user = $this->users->getSingleUserDataById($_GET['orig_usr_id']);
				$title = sprintf($this->plugin->txt('rewrite_user_x'), $this->users->getUserDisplay($user));
				$form = $this->initSearchForm($title, $pattern);
				$content = $form->getHTML();
				$commands = ['rewriteUserConfirm'];
				break;
		}

        if ($pattern)
        {
            $internal = $this->users->getUserDataByPattern($pattern, $withTestAccounts, $searchCategory);

            // only one user found: add directly
//            if (count($internal) == 1)
//            {
//				switch ($_GET['category']) {
//					case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
//                    case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
//					case  ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
//                    case  ilExamAdminUsers::CAT_LOCAL_MEMBER_TESTACCOUNT:
//                        $this->ctrl->setParameter($this, 'usr_id', $internal[0]['usr_id']);
//                        $this->ctrl->redirect($this, 'addParticipant');
//                        break;
//				}
//            }

            // show results of internal search
            $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
            $table1 = new ilExamAdminUserListTableGUI($this, 'showUserSearch');
            $table1->setTitle($this->plugin->txt('internal'));
            $table1->setDescription($this->plugin->txt('found_users_internal'));
            $table1->setData($internal);
            $table1->setIdParameter('usr_id');
            $table1->setRowCommands($commands);
            $table1->setLinkUser($this->plugin->hasAdminAccess());
            $content .= $table1->getHTML();

            // show results of external search
			// clear the usr_id parmeter of the previous table
			$this->ctrl->setParameter($this, 'usr_id', '');
            $connObj = $this->plugin->getConnector();
            $external = $connObj->getUserDataByPattern($pattern, $withTestAccounts);
            $table2 = new ilExamAdminUserListTableGUI($this, 'showUserSearch');
            $table2->setDescription($this->plugin->txt('found_users_external'));
            $table2->setTitle($this->plugin->txt('external'));
            $table2->setData($external);
            $table2->setIdParameter('conn_usr_id');
            $table2->setRowCommands($commands);
            $content .= $table2->getHTML();
        }

        $this->tpl->setContent($content);
        $this->tpl->printToStdout();
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
        $source->setValue($this->data->get(ilExamAdminData::PARAM_IMPORT_SOURCE_TYPE));

        require_once(__DIR__ . '/orga/class.ilExamAdminOrgaCampusExamsInputGUI.php');
        require_once(__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');
        $orga_id = (int) $this->data->get(ilExamAdminData::PARAM_ORGA_ID);
        if (empty($exam_ids = $_SESSION['ilExamAdminExamIds_' . $orga_id])) {
            /** @var  ilExamAdminOrgaRecord $record */
            $record = ilExamAdminOrgaRecord::findOrGetInstance($this->data->get(ilExamAdminData::PARAM_ORGA_ID));
            $exam_ids = $record->exam_ids;
        }

        $se = new ilRadioOption($this->plugin->txt('source_exams'), 'exam_ids');
        $examsgui = new ilExamAdminOrgaCampusExamsInputGUI('', 'exam_ids');
        $examsgui->setInfo($this->plugin->txt('exam_ids_desc'));
        $examsgui->setValueByArray(['exam_ids' => ilExamAdminOrgaExamsInputGUI::_getArray($exam_ids)]);
        $examsgui->setAutocomplete($this->plugin->getConfig()->getCampusSemester());
        $se->addSubItem($examsgui);

        $save_exam_ids = new ilCheckboxInputGUI($this->plugin->txt('save_exam_ids'), 'save_exam_ids');
        $save_exam_ids->setInfo($this->plugin->txt('save_exam_ids_info'));
        $save_exam_ids->setChecked(true);
        if (isset($_SESSION['ilExamAdminSaveExamIds_' . $orga_id])) {
            $save_exam_ids->setChecked($_SESSION['ilExamAdminSaveExamIds_' . $orga_id]);
        }
        $se->addSubItem($save_exam_ids);
        $source->addOption($se);

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
        $ref_id->setValue($this->data->get(ilExamAdminData::PARAM_IMPORT_SOURCE_REF_ID));
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
        $this->mainGUI->prepareObjectOutput();
        $this->ctrl->saveParameter($this, 'category');

        $form = $this->initUserImportForm();
        $this->tpl->setContent($form->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Show the list of users
     * @throws ilDatabaseException
     */
    protected function showUserImportList()
    {
        $this->mainGUI->prepareObjectOutput();
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
            case 'exam_ids':
                $orga_id = (int) $this->data->get(ilExamAdminData::PARAM_ORGA_ID);
                $this->data->set(ilExamAdminData::PARAM_IMPORT_SOURCE_TYPE, 'exam_ids');
                $this->data->write();

                $exam_ids = ilExamAdminOrgaCampusExamsInputGUI::_getString((array) $_POST['exam_ids']);
                $exam_ids_array = explode(', ', $exam_ids);

                $save_exam_ids = (bool)  $_POST['save_exam_ids'];
                $_SESSION['ilExamAdminExamIds_' . $orga_id] = $exam_ids_array;
                $_SESSION['ilExamAdminSaveExamIds_' . $orga_id] = $save_exam_ids;

                if ($save_exam_ids) {
                    require_once(__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');
                    /** @var  ilExamAdminOrgaRecord $record */
                    $record = ilExamAdminOrgaRecord::find($this->data->get(ilExamAdminData::PARAM_ORGA_ID));
                    if (isset($record) && isset($record->obj_id)) {
                        $record->exam_ids = $exam_ids;
                        $record->save();
                    }
                }

                require_once (__DIR__ . '/class.ilExamAdminCampusParticipants.php');
                $matriculations = [];
                foreach ($exam_ids_array as $id) {
                    if (!empty($id)) {
                        $campus = new ilExamAdminCampusParticipants();
                        $campus->fetchParticipants($this->plugin, $id);
                        $matriculations = array_merge($matriculations, $campus->getActiveMatriculations());
                    }
                }
                $external = $connObj->getUserDataByMatriculationList($matriculations);
                break;

            case 'matriculations':
                $this->data->set(ilExamAdminData::PARAM_IMPORT_SOURCE_TYPE, 'matriculations');
                $this->data->write();

                $list = $connObj->getArrayFromListInput($_POST['matriculations']);
                $info[] = sprintf($this->plugin->txt('x_matriculations_searched'), count($list));
                $external = $connObj->getUserDataByMatriculationList($list);
                break;

            case 'ref_id':
                $ref_id = (int) $_POST['ref_id'];
                $this->data->set(ilExamAdminData::PARAM_IMPORT_SOURCE_TYPE, 'ref_id');
                $this->data->set(ilExamAdminData::PARAM_IMPORT_SOURCE_REF_ID, $ref_id);
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
        $this->tpl->printToStdout();
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
			$added = $this->users->addParticipants($_POST['usr_id'], false, $_GET['category']);
        }

        if ($added)
        {
            ilUtil::sendSuccess($this->plugin->txt('members_added_to_course'). '<br />' . implode('<br />', $added), true);
        }

        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Add a participant
     * @throws Exception
     */
    protected function addParticipant()
    {
        $added = [];
        if ($_GET['usr_id']) {
            $added = $this->users->addParticipants([$_GET['usr_id']], true, $_GET['category']);
        }
        elseif ($_GET['conn_usr_id']) {
            $added = $this->users->addParticipants([$_GET['conn_usr_id']], false, $_GET['category']);
        }

        if (!empty($added)) {
            switch ($_GET['category']) {
                case ilExamAdminUsers::CAT_LOCAL_ADMIN_LECTURER:
                    $info = $this->plugin->txt('lecturer_added_to_course');
                    break;
                case ilExamAdminUsers::CAT_LOCAL_TUTOR_CORRECTOR:
                    $info = $this->plugin->txt('tutor_added_to_course');
                    break;
                case  ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
                    $info = $this->plugin->txt('member_added_to_course');
                    break;
                case  ilExamAdminUsers::CAT_LOCAL_MEMBER_TESTACCOUNT:
                    $info = $this->plugin->txt('testaccount_added_to_course');
                    break;
            }
            ilUtil::sendSuccess($info. '<br />' . implode('<br />', $added), true);
        }
        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Activate the users of a category
     */
    protected function activateUsers()
    {
        $this->users->setActiveByCategory($_GET['category'], 1);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Activate the users of a category
     */
    protected function activateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->users->setActiveByUserId($_GET['usr_id'], 1);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Deactivate the users of a category
     */
    protected function deactivateUsers()
    {
        $this->users->setActiveByCategory($_GET['category'], 0);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Deactivate the users of a category
     */
    protected function deactivateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->users->setActiveByUserId($_GET['usr_id'], 0);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Synchronize the users of a category
     * @throws ilException
     */
    protected function synchronizeUsers()
    {
        $count = $this->users->synchronizeByCategory($_GET['category']);
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
        $this->users->synchronizeByUserId($_GET['usr_id']);
        $this->ctrl->redirect($this, 'listUsers');
    }

	/**
	 * Rename a user
	 */
    protected function rewriteUser()
	{
		$pattern = '';
		$user =$this->users->getSingleUserDataById( $_GET['usr_id']);
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
			$check = $this->users->rewriteUser($_GET['orig_usr_id'], $_GET['usr_id'], true, false);
		}
		elseif ($_GET['conn_usr_id']) {
			$check = $this->users->rewriteUser($_GET['orig_usr_id'], $_GET['conn_usr_id'], false, false);
		}

		if (empty($check['new'])) {
			ilUtil::sendFailure($this->plugin->txt('rewrite_not_found'), true);
			$this->ctrl->redirect($this, 'listUsers');
		}

		if (!empty($check['conflicts'])) {
			$message = sprintf($this->plugin->txt('rewrite_conflict'), $this->users->getUserDisplay($check['new']));
			foreach ($check['conflicts'] as $ref_id => $title) {
				$message .= '<br /><a href="' . ilLink::_getLink($ref_id). '">'. $title.'</a>';
			}
			ilUtil::sendFailure($message, true);
			$this->ctrl->redirect($this, 'listUsers');
		}

		$this->ctrl->saveParameter($this, 'usr_id');
		$this->ctrl->saveParameter($this, 'orig_usr_id');
		$this->ctrl->saveParameter($this, 'conn_usr_id');

		$this->mainGUI->prepareObjectOutput();
		$confGui = new ilConfirmationGUI();
		$confGui->setFormAction($this->ctrl->getFormAction($this));
		$confGui->setConfirm($this->plugin->txt('rewriteUser'), 'rewriteUserConfirmed');
		$confGui->setCancel($this->lng->txt('cancel'), 'listUsers');
		$confGui->setHeaderText(sprintf($this->plugin->txt('rewrite_transfer'),
				$this->users->getUserDisplay($check['orig']), $this->users->getUserDisplay($check['new']))
			.'<p class="small">'.$this->plugin->txt('rewrite_transfer_info').'</p>');

		$this->tpl->setContent($confGui->getHTML());
		$this->tpl->printToStdout();
	}

	/**
	 * Actually rewrite a user
	 * @throws Exception
	 */
	protected function rewriteUserConfirmed()
	{
		$this->ctrl->saveParameter($this, 'category');

		if ($_GET['usr_id']) {
			$check = $this->users->rewriteUser($_GET['orig_usr_id'], $_GET['usr_id'], true, true);
		}
		elseif ($_GET['conn_usr_id']) {
			$check = $this->users->rewriteUser($_GET['orig_usr_id'], $_GET['conn_usr_id'], false, true);
		}

		if (!$check['done']) {
			ilUtil::sendFailure(sprintf($this->plugin->txt('rewrite_failed'),
				$this->users->getUserDisplay($check['orig'])), true);
		}
		else {
			ilUtil::sendSuccess(sprintf($this->plugin->txt('rewrite_done'),
				$this->users->getUserDisplay($check['orig']), $this->users->getUserDisplay($check['new'])), true);
		}

		$this->ctrl->redirect($this, 'listUsers');

	}

    /**
     * Show confirmation to remove users
     */
	protected function removeUser()
    {
        $this->ctrl->saveParameter($this, 'category');

        $usr_ids = (array) $_REQUEST['usr_id'];
        if (empty($usr_ids)) {
            ilUtil::sendFailure($this->plugin->txt('failure_no_entry_selected'), true);
            $this->ctrl->redirect($this, 'listUsers');
        }
        if (in_array($this->user->getId(), $usr_ids)) {
            ilUtil::sendFailure($this->plugin->txt('failure_remove_self'), true);
            $this->ctrl->redirect($this, 'listUsers');
        }

        $this->mainGUI->prepareObjectOutput();
		$confGui = new ilConfirmationGUI();
		$confGui->setFormAction($this->ctrl->getFormAction($this));
		$confGui->setConfirm($this->plugin->txt('removeUser'), 'removeUserConfirmed');
		$confGui->setCancel($this->lng->txt('cancel'), 'listUsers');
		$confGui->setHeaderText($this->plugin->txt('removeUserConfirmation'));

		foreach ($this->users->getUserDataByIds($usr_ids) as $user) {
            $confGui->addItem('usr_id[]', $user['usr_id'], $user['name']);
            foreach($this->users->getTestaccountData($user['login']) as $test) {
                $confGui->addItem('usr_id[]', $test['usr_id'], $test['name']);
            }
        }

		$this->tpl->setContent($confGui->getHTML());
		$this->tpl->printToStdout();
    }


    /**
     * Remove users after confirmation
     */
    protected function removeUserConfirmed()
    {
        $this->ctrl->saveParameter($this, 'category');

        $usr_ids = (array) $_REQUEST['usr_id'];
        if (empty($usr_ids)) {
            ilUtil::sendFailure($this->plugin->txt('failure_no_entry_selected'), true);
            $this->ctrl->redirect($this, 'listUsers');
        }
        if (in_array($this->user->getId(), $usr_ids)) {
            ilUtil::sendFailure($this->plugin->txt('failure_remove_self'), true);
            $this->ctrl->redirect($this, 'listUsers');
        }

        $removed = $this->users->removeParticipants($usr_ids);
        if (!empty($removed)) {
            $info = $this->plugin->txt('participants_removed_from_course');
            ilUtil::sendSuccess($info. '<br />' . implode('<br />', $removed), true);
        }
        $this->ctrl->redirect($this, 'listUsers');

    }


    /**
     * Get the URL for user search auto complete
     * @return string
     */
    protected function getAutocompleteUrl()
    {
        return $this->ctrl->getLinkTargetByClass(['ilrepositorygui','ilobjcoursegui','ilcoursemembershipgui', 'ilrepositorysearchgui'],
            'doUserAutoComplete', '', true,false);
    }
}
