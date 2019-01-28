<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminBaseGUI.php');

/**
 * GUI for Exam administration in group object
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilExamAdminGroupGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilExamAdminGroupGUI:
 */
class ilExamAdminGroupGUI extends ilExamAdminBaseGUI
{

	/** @var  int parent object ref_id */
	protected $parent_ref_id;

	/** @var  string parent object type */
	protected $parent_type;

	/** @var  string parent gui class */
	protected $parent_gui_class;

	/** @var  ilObjGroup $parent_obj */
	protected $parent_obj;


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

        $this->plugin->includeClass('class.ilExamAdminGroupUsers.php');
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
	* Handles all commands
	*/
	public function executeCommand()
	{
		$fallback_url = "goto.php?target=".$this->parent_type.'_'.$this->parent_ref_id;

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
                    case 'addMember':
                    case 'activateUser':
                    case 'activateUsers':
                    case 'deactivateUser':
                    case 'deactivateUsers':
                    case 'synchronizeUser':
                    case 'synchronizeUsers':
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
        $table->setData($this->getUsersObj()->getOverviewData());

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

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
                $this->addToolbarSearch($this->plugin->txt('addMember'));
                break;
        }

        $usersObj = $this->getUsersObj();
        $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
        $table = new ilExamAdminUserListTableGUI($this, 'listUsers');
        $table->setTitle($this->plugin->txt($_GET['category']));
        $table->setData($usersObj->getCategoryUserData($_GET['category']));
        $table->setRowCommands($usersObj->getUserCommands($_GET['category']));
        $this->ctrl->saveParameter($this, 'category');

        $this->tpl->setContent($table->getHTML());
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
    }


    /**
     * Initialize the search form for lecturers
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
                $form = $this->initSearchForm($this->plugin->txt('addLecturer'), $pattern);
                $content = $form->getHTML();
                $commands = ['addLecturer'];
                break;

            case ilExamAdminUsers::CAT_LOCAL_MEMBER_STANDARD:
                $form = $this->initSearchForm($this->plugin->txt('addMember'), $pattern);
                $content = $form->getHTML();
                $commands = ['addMember'];
                break;
        }

        if ($pattern)
        {
            $usersObj = $this->getUsersObj();
            $internal = $usersObj->getUserDataByPattern($pattern, false, $_GET['category']);

            // only one user found: add directly
            if (count($internal) == 1)
            {
                $this->ctrl->setParameter($this, 'usr_id', $internal[0]['usr_id']);
                $this->ctrl->redirect($this, 'addLecturer');
            }

            $this->plugin->includeClass('tables/class.ilExamAdminUserListTableGUI.php');
            $table1 = new ilExamAdminUserListTableGUI($this, 'showUserSearch');
            $table1->setTitle($this->plugin->txt('internal'));
            $table1->setData($internal);
            $table1->setIdParameter('usr_id');
            $table1->setRowCommands($commands);
            $content .= $table1->getHTML();

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
     * Addd a lecturer
     * @throws Exception
     */
    protected function addLecturer()
    {
        $added = [];

        if ($_GET['usr_id'])
        {
            $usersObj = $this->getUsersObj();
            $user = $usersObj->getSingleUserDataById((int) $_GET['usr_id']);
            if ($user)
            {
                $this->parent_obj->getMembersObject()->add($user['usr_id'], IL_GRP_ADMIN);
                $added[] = $user['login'];
                foreach ($usersObj->getTestaccountData($user['login']) as $test)
                {
                    $this->parent_obj->getMembersObject()->add($test['usr_id'], IL_GRP_MEMBER);
                    $added[] = $test['login'];
                }
            }
        }
        elseif ($_GET['conn_usr_id'])
        {
            $usersObj = $this->getUsersObj();
            $connObj = $this->plugin->getConnector();
            $user = $connObj->getSingleUserDataById((int) $_GET['conn_usr_id']);
            if ($user)
            {
                $user = $usersObj->getMatchingUser($user, true, $this->plugin->getConfig()->get('global_lecturer_role'));
                $this->parent_obj->getMembersObject()->add($user['usr_id'], IL_GRP_ADMIN);
                $added[] = $user['login'];
                foreach ($connObj->getTestaccountData($user['login']) as $test)
                {
                    $test = $usersObj->getMatchingUser($test, true, $this->plugin->getConfig()->get('global_lecturer_role'));
                    $this->parent_obj->getMembersObject()->add($test['usr_id'], IL_GRP_MEMBER);
                    $added[] = $test['login'];
                }
            }
        }

        if ($added)
        {
            ilUtil::sendSuccess($this->plugin->txt('lecturer_added_to_group'). '<br />' . implode('<br />', $added), true);
        }
        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Addd a member
     * @throws Exception
     */
    protected function addMember()
    {
        $added = [];

        if ($_GET['usr_id'])
        {
            $usersObj = $this->getUsersObj();
            $user = $usersObj->getSingleUserDataById((int) $_GET['usr_id']);
            if ($user)
            {
                $this->parent_obj->getMembersObject()->add($user['usr_id'], IL_GRP_MEMBER);
                $added[] = $user['login'];
            }
        }
        elseif ($_GET['conn_usr_id'])
        {
            $usersObj = $this->getUsersObj();
            $connObj = $this->plugin->getConnector();
            $user = $connObj->getSingleUserDataById((int) $_GET['conn_usr_id']);
            if ($user)
            {
                $user = $usersObj->getMatchingUser($user, true, $this->plugin->getConfig()->get('global_participant_role'));
                $this->parent_obj->getMembersObject()->add($user['usr_id'], IL_GRP_MEMBER);
                $added[] = $user['login'];
            }
        }

        if ($added)
        {
            ilUtil::sendSuccess($this->plugin->txt('member_added_to_group'). '<br />' . implode('<br />', $added), true);
        }

        $this->ctrl->saveParameter($this, 'category');
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Activate the users of a category
     */
    protected function activateUsers()
    {
        $this->getUsersObj()->setActiveByCategory($_GET['category'], 1);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Activate the users of a category
     */
    protected function activateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsersObj()->setActiveByUserId($_GET['usr_id'], 1);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Deactivate the users of a category
     */
    protected function deactivateUsers()
    {
        $this->getUsersObj()->setActiveByCategory($_GET['category'], 0);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Deativate the users of a category
     */
    protected function deactivateUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsersObj()->setActiveByUserId($_GET['usr_id'], 0);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Synchronize the users of a category
     * @throws ilException
     */
    protected function synchronizeUsers()
    {
        $this->getUsersObj()->synchronizeByCategory($_GET['category']);
        $this->ctrl->redirect($this, 'showOverview');
    }

    /**
     * Synchronize a single user
     * @throws ilException
     */
    protected function synchronizeUser()
    {
        $this->ctrl->saveParameter($this, 'category');
        $this->getUsersObj()->synchronizeByUserId($_GET['usr_id']);
        $this->ctrl->redirect($this, 'listUsers');
    }


    /**
     * Get the users object
     * @return ilExamAdminGroupUsers
     */
    protected function getUsersObj()
    {
        $usersObj =  new ilExamAdminGroupUsers($this->plugin, $this->parent_obj);
        return $usersObj;
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
        return $this->ctrl->getLinkTargetByClass(['ilrepositorygui','ilobjgroupgui','ilgroupmembershipgui', 'ilrepositorysearchgui'],
            'doUserAutoComplete', '', true,false);
    }



}
