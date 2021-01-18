<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExamAdminBaseGUI.php');

/**
 * GUI for Exam administration in course object
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 *
 * @ilCtrl_IsCalledBy ilExamAdminMainGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilExamAdminMainGUI: ilExamAdminCourseUsersGUI
 */
class ilExamAdminMainGUI extends ilExamAdminBaseGUI
{
    /**
     * Course or group from which the tab is called (for controller)
     * @var  ilObjCourse|ilObjGroup $parent
     */
	protected $parent;

    /**
     * Course to which the functions relate (equal to parent or group's course)
     * @var ilObjCourse
     */
    protected $course;

    /**
     * @var ilCourseParticipants
     */
    protected $participants;

	/**
	 * constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->parent = ilObjectFactory::getInstanceByRefId($_GET['ref_id']);

		$course_ref_id = $this->plugin->getCourseRefId($this->parent->getRefId());
        if ($course_ref_id == $this->parent->getRefId()) {
            $this->course = $this->parent;
        }
        else {
            $this->course = ilObjectFactory::getInstanceByRefId($course_ref_id);
        }

        $this->participants = $this->course->getMembersObject();
    }

    /**
     * @return ilObjCourse
     */
    public function getCourse()
    {
        return $this->course;
    }


    /**
	* Handles all commands
	*/
	public function executeCommand()
	{
        $this->ctrl->saveParameter($this, 'ref_id');
        $this->setSubTabs();

		$next_class = $this->ctrl->getNextClass($this);
		switch ($next_class)
		{
			case 'ilexamadmincourseusersgui':
			    $this->tabs->activateSubTab('manage_users');
			    if ($this->canManageParticipants()) {
                    require_once(__DIR__ . '/class.ilExamAdminCourseUsersGUI.php');
                    $this->ctrl->forwardCommand(new ilExamAdminCourseUsersGUI($this));
                }
			    else {
                    $this->prepareObjectOutput();
                    ilUtil::sendInfo($this->plugin->txt('function not_available'),false);
                    $this->tpl->show();
                }
                break;

			default:
                $cmd = $this->ctrl->getCmd('redirectDefault');

				switch ($cmd)
				{
                    case 'redirectDefault':
                    case 'redirectFallback':
                        $this->$cmd();
                        break;

                    case 'editAdminData':
                    case 'saveAdminData':
                    case 'updateCourse':
                        if ($this->plugin->hasAdminAccess()) {
                            $this->$cmd();
                        }
                        break;

					default:
					    $this->prepareObjectOutput();
					    $this->tpl->setContent('invalid command:' . $cmd);
                        $this->tpl->show();
						break;
				}
		}
	}

    /**
     * Set the sub tabs for the exam tab
     */
	protected function setSubTabs()
    {
	    if ($this->canManageParticipants()) {
	        $this->tabs->addSubTab('manage_users', $this->plugin->txt('manage_users'),
            $this->ctrl->getLinkTargetByClass('ilExamAdminCourseUsersGUI'));
        }

	    if ($this->plugin->hasAdminAccess()) {
            $this->tabs->addSubTab('admin_data', $this->plugin->txt('admin_data'),
                $this->ctrl->getLinkTarget($this,'editAdminData'));
        }
    }

    /**
     * Set the toolbar for admin functions
     */
    protected function setAdminToolbar()
    {
        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this,'updateCourse'));
        $button->setCaption($this->plugin->txt('update_course'), false);
        $this->toolbar->addButtonInstance($button);
    }

    /**
     * Redirect to the default GUI
     */
	protected function redirectDefault()
    {
        if ($this->canManageParticipants()) {
            $this->ctrl->redirectByClass('ilExamAdminCourseUsersGUI');
        }
        $this->prepareObjectOutput();
        ilUtil::sendInfo($this->plugin->txt('function_not_available'),false);
        $this->tpl->show();
    }

    /**
     * Redurect to the fallback page
     */
	public function redirectFallback()
    {
        $fallback_url = "goto.php?target=".$this->parent->getType().'_'.$this->parent->getRefId();
        $this->ctrl->redirectToURL($fallback_url);
    }


    /**
     * Prepare the header, tabs etc.
     */
    public function prepareObjectOutput()
    {
        global $DIC;

        /** @var ilLocatorGUI $ilLocator */
        $ilLocator = $DIC['ilLocator'];

        $ilLocator->addRepositoryItems($this->parent->getRefId());
        //$ilLocator->addItem($this->parent->getTitle(), ilLink::_getLink($this->parent->getRefId(), $this->parent->getType()));

        $this->tpl->getStandardTemplate();
        $this->tpl->setLocator();
        $this->tpl->setTitle($this->parent->getPresentationTitle());
        $this->tpl->setDescription($this->parent->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->parent->getId(), 'big', $this->parent->getType()), $this->lng->txt('obj_'.$this->parent->getType()));
    }

    /**
     * Initialize the configuration form
     * @return ilPropertyFormGUI form object
     */
    protected function initAdminDataForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('admin_data'));
        $form->setFormAction($this->ctrl->getFormAction($this));

        $data = $this->plugin->getData($this->parent->getId());
        foreach($data->getParams() as $param) {
            $param->setValue($data->get($param->name));
            $form->addItem($param->getFormItem());
        }

        $form->addCommandButton("saveAdminData", $this->lng->txt("save"));
        return $form;
    }

    /**
     * Edit the administration data
     */
    protected function editAdminData()
    {
        $this->tabs->activateSubTab('admin_data');
        $this->setAdminToolbar();
        $form = $this->initAdminDataForm();
        $this->prepareObjectOutput();
        $this->tpl->setContent($form->getHtml());
        $this->tpl->show();
    }


    /**
     * Save the basic settings
     */
    protected function saveAdminData()
    {
        $form = $this->initAdminDataForm();
        if ($form->checkInput())
        {
            $form->setValuesByPost();

            $data = $this->plugin->getData($this->parent->getId());
            foreach ($data->getParams() as $param) {
                $data->set($param->name, $param->getFormValue($form));
            }
            $data->write();

            ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
            $this->ctrl->redirect($this, 'editAdminData');
        }
        else
        {
            $form->setValuesByPost();
            $this->prepareObjectOutput();
            $this->tpl->setContent($form->getHtml());
            $this->tpl->show();
        }
    }

    /**
     * Update the course fom its connected record
     */
    protected function UpdateCourse()
    {
        require_once (__DIR__ . '/orga/class.ilExamAdminOrgaRecord.php');
        $data = $this->plugin->getData($this->parent->getId());

        /** @var ilExamAdminOrgaRecord $record */
        $record = ilExamAdminOrgaRecord::find($data->get(ilExamAdminData::PARAM_ORGA_ID));
        if (isset($record)) {
            require_once (__DIR__ . '/class.ilExamAdminCronHandler.php');
            $handler = new ilExamAdminCronHandler($this->plugin);
            $handler->updateCourse($record, $this->parent->getRefId());
            ilUtil::sendSuccess($this->plugin->txt("course_updated"), true);
        }
        else {
            ilUtil::sendFailure($this->plugin->txt("orga_record_not_found"), true);
        }
        $this->ctrl->redirect($this, 'editAdminData');
    }

    /**
     * Check if the current user can manage
     */
    public function canManageParticipants()
    {
        return (
            $this->plugin->hasAdminAccess() ||
            $this->participants->isAdmin($this->user->getId())
        );
    }
}
