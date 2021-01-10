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
			    $this->tabs->activateSubTab('users');
			    if ($this->canManageParticipants()) {
                    require_once(__DIR__ . '/class.ilExamAdminCourseUsersGUI.php');
                    $this->ctrl->forwardCommand(new ilExamAdminCourseUsersGUI($this));
                }
			    else {
                    $this->prepareObjectOutput();
                    ilUtil::sendFailure($this->lng->txt("permission_denied"), false);
                }
                break;

			default:
                $cmd = $this->ctrl->getCmd('redirectDefault');

				switch ($cmd)
				{
                    case 'redirectDefault':
                        $this->$cmd();
                        break;

					default:
					    $this->prepareObjectOutput();
					    $this->tpl->setContent('invalid command:' . $cmd);
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
	        $this->tabs->addSubTab('users', $this->plugin->txt('manage_user'),
            $this->ctrl->getLinkTargetByClass('ilExamAdminCourseUsersGUI'));
        }
    }

    /**
     * Redirect to the default GUI
     */
	protected function redirectDefault()
    {
        if ($this->canManageParticipants()) {
            $this->ctrl->redirectByClass('ilExamAdminCourseUsersGUI');
        }
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
        $ilLocator->addItem($this->parent->getTitle(), ilLink::_getLink($this->parent->getRefId(), $this->parent->getType()));

        $this->tpl->getStandardTemplate();
        $this->tpl->setLocator();
        $this->tpl->setTitle($this->parent->getPresentationTitle());
        $this->tpl->setDescription($this->parent->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->parent->getId(), 'big', $this->parent->getType()), $this->lng->txt('obj_'.$this->parent->getType()));
    }

    /**
     * Check if the current user can manage
     */
    public function canManageParticipants()
    {
        return ($this->participants->isAdmin($this->user->getId()) ||
            $this->participants->isTutor($this->user->getId()));
    }
}
